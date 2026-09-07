<?php

namespace App\Http\Controllers;

use App\Models\SwitchingStock;
use App\Services\SwitchingStockService;
use Exception;
use Illuminate\Support\Facades\Auth;

class SwitchingStockController extends Controller
{
    public function __construct(
        protected SwitchingStockService $switchingStockService
    ) {}

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 15;
        $switchings = SwitchingStock::with([
            'order.requestingOrganization',
            'item',
            'sourceOrganization',
            'sourceWarehouse',
            'destinationOrganization',
            'destinationWarehouse',
            'proposer',
            'approver',
        ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('inventory.switching.index', compact('switchings'));
    }

    public function approve($id)
    {
        $switching = SwitchingStock::findOrFail($id);
        try {
            $this->switchingStockService->approveSwitching($switching, Auth::user());

            return redirect()->back()->with('success', "Switching stock untuk order {$switching->order->order_number} berhasil disetujui & stok unit sumber direservasi.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
