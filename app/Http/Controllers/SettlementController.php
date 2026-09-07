<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Settlement;
use App\Services\SettlementService;
use Exception;
use Illuminate\Support\Facades\Auth;

class SettlementController extends Controller
{
    public function __construct(
        protected SettlementService $settlementService
    ) {}

    public function index(Request $request)
    {
        // Unsettled orders that have been received
        $unsettledOrders = Order::with(['requestingOrganization', 'shipments', 'receivings'])
            ->where('status', 'RECEIVED')
            ->whereDoesntHave('settlements')
            ->get();

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 15;
        $settlements = Settlement::with(['order.requestingOrganization', 'debitOrganization', 'creditOrganization', 'creator', 'approver'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('finance.settlements.index', compact('unsettledOrders', 'settlements'));
    }

    public function createFromOrder($orderId)
    {
        $order = Order::findOrFail($orderId);
        try {
            $settlement = $this->settlementService->createSettlementForOrder($order, Auth::user());

            return redirect()->route('finance.settlements.index')
                ->with('success', "Draft settlement {$settlement->settlement_number} berhasil dibuat dan menunggu approval finance.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approveAndPost($id)
    {
        $settlement = Settlement::findOrFail($id);
        try {
            $this->settlementService->approveAndPostSettlement($settlement, Auth::user());

            return redirect()->back()->with('success', "Settlement {$settlement->settlement_number} telah disetujui, diposting ke realisasi anggaran, dan order ditutup (COMPLETED).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
