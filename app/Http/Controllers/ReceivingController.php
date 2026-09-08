<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use App\Models\Discrepancy;
use App\Models\GoodsReceipt;
use App\Models\Organization;
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
        $tab = $request->get('tab', 'incoming');
        $search = $request->get('search');
        $status = $request->get('status');
        $courierId = $request->get('courier_id');
        $organizationId = $request->get('organization_id');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;

        // Query Incoming In-Transit Shipments
        $shipmentsQuery = Shipment::with(['order.requestingOrganization', 'order.items.item', 'courier', 'originWarehouse'])
            ->whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY']);

        if ($isBranch) {
            $shipmentsQuery->whereHas('order', fn ($q) => $q->where('organization_id', $user->organization_id));
        } elseif ($organizationId && $organizationId !== 'ALL') {
            $shipmentsQuery->whereHas('order', fn ($q) => $q->where('organization_id', $organizationId));
        }

        if ($courierId && $courierId !== 'ALL') {
            $shipmentsQuery->where('courier_id', $courierId);
        }

        if ($status && $status !== 'ALL' && $tab === 'incoming') {
            $shipmentsQuery->where('status', $status);
        }

        if ($search && $tab === 'incoming') {
            $shipmentsQuery->where(function ($q) use ($search) {
                $q->where('manifest_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%")->orWhereHas('requestingOrganization', fn ($org) => $org->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                    ->orWhereHas('courier', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $incomingShipments = $shipmentsQuery->latest()->paginate($perPage, ['*'], 'shipments_page')->withQueryString();

        // Query Receivings History
        $receivingsQuery = Receiving::with(['order.requestingOrganization', 'order.items.item', 'shipment.courier', 'receiver', 'discrepancies.item']);

        if ($isBranch) {
            $receivingsQuery->whereHas('order', fn ($q) => $q->where('organization_id', $user->organization_id));
        } elseif ($organizationId && $organizationId !== 'ALL') {
            $receivingsQuery->whereHas('order', fn ($q) => $q->where('organization_id', $organizationId));
        }

        if ($status && $status !== 'ALL' && $tab === 'history') {
            $receivingsQuery->where('status', $status);
        }

        if ($search && $tab === 'history') {
            $receivingsQuery->where(function ($q) use ($search) {
                $q->where('receiving_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('shipment', fn ($s) => $s->where('manifest_number', 'like', "%{$search}%")->orWhere('tracking_number', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%")->orWhereHas('requestingOrganization', fn ($org) => $org->where('name', 'like', "%{$search}%")))
                    ->orWhereHas('receiver', fn ($r) => $r->where('name', 'like', "%{$search}%"));
            });
        }

        $receivings = $receivingsQuery->latest('receipt_date')->paginate($perPage, ['*'], 'receivings_page')->withQueryString();

        $couriers = Courier::where('is_active', true)->orderBy('name')->get();
        $organizations = Organization::whereIn('type', ['MAIN_BRANCH', 'SUB_BRANCH'])->orderBy('name')->get();

        return view('receiving.index', compact(
            'tab',
            'incomingShipments',
            'receivings',
            'couriers',
            'organizations',
            'search',
            'status',
            'courierId',
            'organizationId',
            'perPage',
            'isBranch'
        ));
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
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;
        $search = $request->get('search');
        $discrepancyType = $request->get('discrepancy_type');
        $resolutionStatus = $request->get('resolution_status');
        $organizationId = $request->get('organization_id');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;

        $baseQuery = Discrepancy::with(['receiving.order.requestingOrganization', 'receiving.shipment.originWarehouse', 'receiving.receiver', 'item.category']);

        if ($isBranch) {
            $baseQuery->whereHas('receiving.order', fn ($q) => $q->where('organization_id', $user->organization_id));
        } elseif ($organizationId && $organizationId !== 'ALL') {
            $baseQuery->whereHas('receiving.order', fn ($q) => $q->where('organization_id', $organizationId));
        }

        // Summary KPI Metrics
        $kpiItems = (clone $baseQuery)->get();
        $totalCount = $kpiItems->count();
        $pendingCount = $kpiItems->whereIn('resolution_status', ['REPORTED', 'UNDER_REVIEW', 'IN_REVIEW'])->count();
        $resolvedCount = $kpiItems->whereIn('resolution_status', ['RESOLVED', 'CLAIMED'])->count();
        $totalDamagedQty = (int) $kpiItems->sum('qty_damaged');

        $query = clone $baseQuery;

        if ($discrepancyType && $discrepancyType !== 'ALL') {
            $query->where('discrepancy_type', $discrepancyType);
        }

        if ($resolutionStatus && $resolutionStatus !== 'ALL') {
            $query->where('resolution_status', $resolutionStatus);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('receiving', fn ($r) => $r->where('receiving_number', 'like', "%{$search}%"))
                    ->orWhereHas('receiving.order', fn ($o) => $o->where('order_number', 'like', "%{$search}%")->orWhereHas('requestingOrganization', fn ($org) => $org->where('name', 'like', "%{$search}%")))
                    ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                    ->orWhere('resolution_notes', 'like', "%{$search}%");
            });
        }

        $discrepancies = $query->latest()
            ->paginate($perPage)
            ->withQueryString();

        $organizations = Organization::whereIn('type', ['MAIN_BRANCH', 'SUB_BRANCH'])->orderBy('name')->get();

        return view('receiving.discrepancies', compact(
            'discrepancies',
            'totalCount',
            'pendingCount',
            'resolvedCount',
            'totalDamagedQty',
            'search',
            'discrepancyType',
            'resolutionStatus',
            'organizationId',
            'organizations',
            'perPage',
            'isBranch'
        ));
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
