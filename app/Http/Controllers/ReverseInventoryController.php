<?php

namespace App\Http\Controllers;

use App\Models\InventoryReturn;
use App\Models\Item;
use App\Models\Order;
use App\Models\StockDestruction;
use App\Models\Warehouse;
use App\Services\ReverseInventoryService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReverseInventoryController extends Controller
{
    public function __construct(
        protected ReverseInventoryService $reverseService
    ) {}

    // ==========================================
    // 1. RETUR BARANG (POC-41)
    // ==========================================

    public function returnsIndex(Request $request)
    {
        $status = $request->get('status');
        $search = $request->get('search');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;

        $query = InventoryReturn::with(['originWarehouse', 'destinationWarehouse', 'requester', 'items.item']);

        if ($status && $status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $returns = $query->latest()->paginate($perPage)->withQueryString();

        $metrics = [
            'total' => InventoryReturn::count(),
            'requested' => InventoryReturn::where('status', 'REQUESTED')->count(),
            'shipped' => InventoryReturn::where('status', 'SHIPPED')->count(),
            'received' => InventoryReturn::where('status', 'RECEIVED')->count(),
        ];

        $warehouses = Warehouse::where('is_active', true)->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();
        $orders = Order::latest()->limit(50)->get();

        return view('returns.index', compact('returns', 'metrics', 'status', 'search', 'perPage', 'warehouses', 'items', 'orders'));
    }

    public function returnsCreate(Request $request)
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();
        $orders = Order::latest()->limit(50)->get();

        return view('returns.create', compact('warehouses', 'items', 'orders'));
    }

    public function returnsStore(Request $request)
    {
        $request->validate([
            'origin_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:origin_warehouse_id',
            'reason' => 'required|string',
            'reason_details' => 'nullable|string|max:500',
            'order_id' => 'nullable|exists:orders,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty_returned' => 'required|integer|min:1',
            'items.*.condition' => 'required|in:DAMAGED,DEFECTIVE,GOOD',
        ], [
            'destination_warehouse_id.different' => 'Gudang tujuan retur harus berbeda dengan gudang asal.',
            'items.required' => 'Minimal satu barang harus dipilih untuk diretur.',
        ]);

        try {
            $originWh = Warehouse::findOrFail($request->origin_warehouse_id);
            $destWh = Warehouse::findOrFail($request->destination_warehouse_id);
            $order = $request->order_id ? Order::find($request->order_id) : null;

            $return = $this->reverseService->createReturn(
                $originWh,
                $destWh,
                $request->items,
                $request->reason,
                Auth::user(),
                $request->reason_details,
                $order
            );

            return redirect()->route('returns.show', $return->id)
                ->with('success', "Permohonan retur {$return->return_number} berhasil diajukan.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function returnsShow($id)
    {
        $return = InventoryReturn::with([
            'originWarehouse.organization',
            'destinationWarehouse.organization',
            'order',
            'requester',
            'approver',
            'receiver',
            'items.item.category',
        ])->findOrFail($id);

        return view('returns.show', compact('return'));
    }

    public function returnsApprove(Request $request, $id)
    {
        $return = InventoryReturn::findOrFail($id);

        try {
            $this->reverseService->approveReturn($return, Auth::user(), $request->notes);

            return back()->with('success', "Permohonan retur {$return->return_number} telah disetujui.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function returnsReject(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string|max:500']);
        $return = InventoryReturn::findOrFail($id);

        try {
            $this->reverseService->rejectReturn($return, $request->rejection_reason, Auth::user());

            return back()->with('success', "Permohonan retur {$return->return_number} telah ditolak.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function returnsShip(Request $request, $id)
    {
        $request->validate([
            'courier_name' => 'required|string|max:100',
            'tracking_number' => 'required|string|max:100',
        ]);

        $return = InventoryReturn::findOrFail($id);

        try {
            $this->reverseService->shipReturn($return, $request->courier_name, $request->tracking_number, Auth::user());

            return back()->with('success', "Pengiriman retur {$return->return_number} berhasil dicatat (Stok cabang telah dimutasi).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function returnsReceive(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.return_item_id' => 'required|exists:inventory_return_items,id',
            'items.*.qty_good' => 'required|integer|min:0',
            'items.*.qty_damaged' => 'required|integer|min:0',
        ]);

        $return = InventoryReturn::findOrFail($id);

        try {
            $this->reverseService->receiveReturn($return, $request->items, Auth::user());

            return back()->with('success', "Penerimaan retur {$return->return_number} selesai diproses (Stok gudang pusat telah bertambah).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function returnsUpdate(Request $request, $id)
    {
        $return = InventoryReturn::findOrFail($id);
        $request->validate([
            'reason' => 'required|string',
            'reason_details' => 'nullable|string|max:500',
        ]);

        $return->update($request->only(['reason', 'reason_details']));

        return redirect()->route('returns.index')->with('success', "Permohonan retur {$return->return_number} berhasil diperbarui.");
    }

    public function returnsDestroy($id)
    {
        $return = InventoryReturn::findOrFail($id);

        if (in_array($return->status, ['SHIPPED', 'RECEIVED'], true)) {
            return redirect()->route('returns.index')->with('error', 'Permohonan retur yang telah dikirim atau diterima tidak dapat dihapus.');
        }

        $num = $return->return_number;
        $return->items()->delete();
        $return->delete();

        return redirect()->route('returns.index')->with('success', "Permohonan retur {$num} berhasil dihapus.");
    }

    // ==========================================
    // 2. PEMUSNAHAN BARANG & BERITA ACARA (POC-43)
    // ==========================================

    public function destructionsIndex(Request $request)
    {
        $status = $request->get('status');
        $search = $request->get('search');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;

        $query = StockDestruction::with(['warehouse', 'requester', 'approver', 'items.item']);

        if ($status && $status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('destruction_number', 'like', "%{$search}%")
                    ->orWhere('berita_acara_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $destructions = $query->latest()->paginate($perPage)->withQueryString();

        $metrics = [
            'total' => StockDestruction::count(),
            'requested' => StockDestruction::where('status', 'REQUESTED')->count(),
            'executed' => StockDestruction::where('status', 'EXECUTED')->count(),
            'total_loss' => (float) StockDestruction::where('status', 'EXECUTED')->with('items')->get()->sum('total_loss_value'),
        ];

        $warehouses = Warehouse::where('is_active', true)->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();

        return view('destructions.index', compact('destructions', 'metrics', 'status', 'search', 'perPage', 'warehouses', 'items'));
    }

    public function destructionsCreate()
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();

        return view('destructions.create', compact('warehouses', 'items'));
    }

    public function destructionsStore(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'reason' => 'required|string',
            'reason_details' => 'nullable|string|max:500',
            'witness_name_1' => 'required|string|max:100',
            'witness_title_1' => 'required|string|max:100',
            'witness_name_2' => 'required|string|max:100',
            'witness_title_2' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
        ], [
            'witness_name_1.required' => 'Nama Saksi 1 wajib diisi.',
            'witness_name_2.required' => 'Nama Saksi 2 wajib diisi.',
            'items.required' => 'Minimal satu barang harus dipilih untuk dimusnahkan.',
        ]);

        try {
            $warehouse = Warehouse::findOrFail($request->warehouse_id);

            $destruction = $this->reverseService->createDestruction(
                $warehouse,
                $request->items,
                $request->reason,
                $request->witness_name_1,
                $request->witness_title_1,
                $request->witness_name_2,
                $request->witness_title_2,
                Auth::user(),
                $request->reason_details
            );

            return redirect()->route('destructions.show', $destruction->id)
                ->with('success', "Pengajuan pemusnahan {$destruction->destruction_number} berhasil dibuat.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destructionsShow($id)
    {
        $destruction = StockDestruction::with([
            'warehouse.organization',
            'requester',
            'approver',
            'executor',
            'items.item.category',
        ])->findOrFail($id);

        return view('destructions.show', compact('destruction'));
    }

    public function destructionsApprove(Request $request, $id)
    {
        $destruction = StockDestruction::findOrFail($id);

        try {
            $this->reverseService->approveDestruction($destruction, Auth::user());

            return back()->with('success', "Pengajuan pemusnahan {$destruction->destruction_number} telah disetujui.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destructionsExecute(Request $request, $id)
    {
        $destruction = StockDestruction::findOrFail($id);

        try {
            $this->reverseService->executeDestruction($destruction, Auth::user(), $request->execution_notes);

            return back()->with('success', "Pemusnahan barang {$destruction->destruction_number} selesai dieksekusi (Stok telah dimutasi DESTROYED).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destructionsBeritaAcara($id)
    {
        $destruction = StockDestruction::with([
            'warehouse.organization',
            'requester',
            'approver',
            'executor',
            'items.item.category',
        ])->findOrFail($id);

        return view('destructions.berita_acara', compact('destruction'));
    }

    public function destructionsUpdate(Request $request, $id)
    {
        $destruction = StockDestruction::findOrFail($id);
        $request->validate([
            'reason' => 'required|string',
            'reason_details' => 'nullable|string|max:500',
            'witness_name_1' => 'required|string|max:100',
            'witness_title_1' => 'required|string|max:100',
            'witness_name_2' => 'required|string|max:100',
            'witness_title_2' => 'required|string|max:100',
        ]);

        $destruction->update($request->only([
            'reason', 'reason_details',
            'witness_name_1', 'witness_title_1',
            'witness_name_2', 'witness_title_2',
        ]));

        return redirect()->route('destructions.index')->with('success', "Pengajuan pemusnahan {$destruction->destruction_number} berhasil diperbarui.");
    }

    public function destructionsDestroy($id)
    {
        $destruction = StockDestruction::findOrFail($id);

        if ($destruction->status === 'EXECUTED') {
            return redirect()->route('destructions.index')->with('error', 'Pemusnahan barang yang telah dieksekusi tidak dapat dihapus.');
        }

        $num = $destruction->destruction_number;
        $destruction->items()->delete();
        $destruction->delete();

        return redirect()->route('destructions.index')->with('success', "Pengajuan pemusnahan {$num} berhasil dibatalkan dan dihapus.");
    }
}
