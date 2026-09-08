<?php

namespace App\Http\Controllers;

use App\Models\Discrepancy;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Receiving;
use App\Models\Shipment;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\OrderFulfillmentService;
use App\Services\ProcurementService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReceivingController extends Controller
{
    public function __construct(
        protected OrderFulfillmentService $orderFulfillmentService,
        protected ProcurementService $procurementService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;

        $shipmentsQuery = Shipment::with(['order.requestingOrganization', 'courier'])
            ->whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY']);

        $receivingsQuery = Receiving::with(['order.requestingOrganization', 'shipment.courier', 'receiver', 'discrepancies']);

        if ($isBranch) {
            $shipmentsQuery->whereHas('order', fn ($q) => $q->where('organization_id', $user->organization_id));
            $receivingsQuery->whereHas('order', fn ($q) => $q->where('organization_id', $user->organization_id));
        }

        $incomingShipments = $shipmentsQuery->latest()->get();

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 15;
        $receivings = $receivingsQuery->latest()->paginate($perPage)->withQueryString();

        return view('receiving.index', compact('incomingShipments', 'receivings'));
    }

    public function createReceiptForm($shipmentId)
    {
        $shipment = Shipment::with(['order.items.item', 'courier', 'originWarehouse'])->findOrFail($shipmentId);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $shipment->order && $shipment->order->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak berwenang menerima pengiriman untuk unit kerja lain.');
        }

        return view('receiving.confirm', compact('shipment'));
    }

    public function confirmReceipt(Request $request, $shipmentId)
    {
        $shipment = Shipment::with('order.items')->findOrFail($shipmentId);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $shipment->order && $shipment->order->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak berwenang menerima pengiriman untuk unit kerja lain.');
        }

        $request->validate([
            'pod_signature' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.qty_good' => 'required|integer|min:0',
            'items.*.qty_damaged' => 'nullable|integer|min:0',
            'items.*.qty_missing' => 'nullable|integer|min:0',
        ]);

        try {
            $rcv = $this->orderFulfillmentService->processReceiving(
                $shipment,
                $request->items,
                $request->pod_signature,
                $request->notes ?? '',
                Auth::user()
            );

            return redirect()->route('receiving.index')
                ->with('success', "Konfirmasi penerimaan barang {$rcv->receiving_number} berhasil dicatat & stok unit telah diperbarui.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function discrepancies(Request $request)
    {
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 15;
        $discrepancies = Discrepancy::with(['receiving.order.requestingOrganization', 'item'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('receiving.discrepancies', compact('discrepancies'));
    }

    public function poIndex(Request $request)
    {
        $tab = $request->get('tab', 'queue');
        $search = $request->get('search');
        $status = $request->get('status');
        $vendorId = $request->get('vendor_id');
        $warehouseId = $request->get('warehouse_id');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;

        // Query POs
        $poQuery = PurchaseOrder::with(['vendor', 'warehouse', 'creator', 'items.item.category', 'goodsReceipts.receiver']);

        if ($status && $status !== 'ALL') {
            $poQuery->where('status', $status);
        } else {
            if ($tab === 'queue') {
                $poQuery->whereIn('status', ['ISSUED', 'VENDOR_PROCESS', 'IN_DELIVERY', 'PARTIAL_RECEIVED']);
            }
        }

        if ($vendorId && $vendorId !== 'ALL') {
            $poQuery->where('vendor_id', $vendorId);
        }

        if ($warehouseId && $warehouseId !== 'ALL') {
            $poQuery->where('warehouse_id', $warehouseId);
        }

        if ($search) {
            $poQuery->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('items.item', fn ($i) => $i->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        $pos = $poQuery->latest('order_date')->paginate($perPage, ['*'], 'pos_page')->withQueryString();

        // Query GRNs
        $grnQuery = GoodsReceipt::with(['purchaseOrder.vendor', 'warehouse', 'receiver', 'items.item.category']);

        if ($warehouseId && $warehouseId !== 'ALL') {
            $grnQuery->where('warehouse_id', $warehouseId);
        }

        if ($search) {
            $grnQuery->where(function ($q) use ($search) {
                $q->where('grn_number', 'like', "%{$search}%")
                    ->orWhere('vendor_delivery_note_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', fn ($p) => $p->where('po_number', 'like', "%{$search}%"))
                    ->orWhereHas('purchaseOrder.vendor', fn ($v) => $v->where('name', 'like', "%{$search}%"));
            });
        }

        $goodsReceipts = $grnQuery->latest('receipt_date')->paginate($perPage, ['*'], 'grn_page')->withQueryString();

        $vendors = Vendor::where('is_active', true)->get();
        $warehouses = Warehouse::where('is_active', true)->get();

        return view('receiving.po_index', compact(
            'tab',
            'pos',
            'goodsReceipts',
            'vendors',
            'warehouses',
            'search',
            'status',
            'vendorId',
            'warehouseId',
            'perPage'
        ));
    }

    public function poReceiveStore(Request $request, $id)
    {
        $po = PurchaseOrder::with('items.item')->findOrFail($id);

        if (! in_array($po->status, ['ISSUED', 'VENDOR_PROCESS', 'IN_DELIVERY', 'PARTIAL_RECEIVED'], true)) {
            return back()->with('error', "PO {$po->po_number} tidak dapat diproses penerimaannya karena berstatus {$po->status}.");
        }

        $request->validate([
            'vendor_delivery_note_number' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.po_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.qty_accepted' => 'required|integer|min:0',
            'items.*.qty_rejected' => 'nullable|integer|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        $totalReceived = collect($request->items)->sum(fn ($i) => ((int) $i['qty_accepted']) + ((int) ($i['qty_rejected'] ?? 0)));
        if ($totalReceived <= 0) {
            return back()->withInput()->with('error', 'Jumlah barang yang diterima atau ditolak harus lebih dari 0.');
        }

        foreach ($request->items as $itemInput) {
            $poItem = $po->items->firstWhere('id', (int) $itemInput['po_item_id']);
            if (! $poItem) {
                return back()->withInput()->with('error', 'Item PO tidak valid.');
            }
            $accepted = (int) $itemInput['qty_accepted'];
            $rejected = (int) ($itemInput['qty_rejected'] ?? 0);
            if ($accepted + $rejected > $poItem->outstanding_qty) {
                return back()->withInput()->with('error', "Total kuantitas terima/tolak untuk barang {$poItem->item->name} melebihi sisa pesanan ({$poItem->outstanding_qty} unit).");
            }
        }

        try {
            $grn = $this->procurementService->processGoodsReceipt(
                $po,
                $request->items,
                $request->vendor_delivery_note_number,
                Auth::user()
            );

            return redirect()->route('receiving.po.index', ['tab' => 'history'])
                ->with('success', "Penerimaan barang vendor berhasil diposting ke Stock Ledger (GRN: {$grn->grn_number}).");
        } catch (Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses penerimaan barang: '.$e->getMessage());
        }
    }
}
