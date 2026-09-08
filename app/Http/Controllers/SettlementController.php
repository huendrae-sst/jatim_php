<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Organization;
use App\Models\Settlement;
use App\Services\SettlementService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettlementController extends Controller
{
    public function __construct(
        protected SettlementService $settlementService
    ) {}

    public function index(Request $request)
    {
        $search = $request->get('search');
        $tab = $request->get('tab', 'waiting_approval');
        $organizationId = $request->get('organization_id');
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;

        // Unsettled orders query (orders received but not yet settled)
        $unsettledOrdersQuery = Order::with(['requestingOrganization', 'shipments', 'receivings', 'items.item'])
            ->where('status', 'RECEIVED')
            ->whereDoesntHave('settlements');

        if ($isBranch) {
            $unsettledOrdersQuery->where('requesting_organization_id', $user->organization_id);
        } elseif ($organizationId && $organizationId !== 'ALL') {
            $unsettledOrdersQuery->where('requesting_organization_id', $organizationId);
        }

        if ($search) {
            $unsettledOrdersQuery->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('requestingOrganization', fn ($oq) => $oq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        $unsettledOrders = $unsettledOrdersQuery->latest()->get();

        // Settlements Query
        $query = Settlement::with([
            'order.requestingOrganization',
            'order.items.item',
            'order.shipments',
            'debitOrganization',
            'creditOrganization',
            'creator',
            'approver',
        ]);

        if ($isBranch) {
            $query->where(function ($q) use ($user) {
                $q->where('debit_organization_id', $user->organization_id)
                    ->orWhere('credit_organization_id', $user->organization_id);
            });
            $organizationId = (string) $user->organization_id;
        } elseif ($organizationId && $organizationId !== 'ALL') {
            $query->where(function ($q) use ($organizationId) {
                $q->where('debit_organization_id', $organizationId)
                    ->orWhere('credit_organization_id', $organizationId);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('settlement_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('debitOrganization', fn ($dq) => $dq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('creditOrganization', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        // Apply Tab Filter
        match ($tab) {
            'posted' => $query->where('status', 'POSTED'),
            'all' => null,
            default => $query->where('status', 'WAITING_APPROVAL'),
        };

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $settlements = $query->latest()->paginate($perPage)->withQueryString();

        // KPI Counts (scoped to branch if branch user)
        $kpiQuery = Settlement::query();
        if ($isBranch) {
            $kpiQuery->where(function ($q) use ($user) {
                $q->where('debit_organization_id', $user->organization_id)
                    ->orWhere('credit_organization_id', $user->organization_id);
            });
        }

        $waitingApprovalCount = (clone $kpiQuery)->where('status', 'WAITING_APPROVAL')->count();
        $postedCount = (clone $kpiQuery)->where('status', 'POSTED')->count();
        $allCount = (clone $kpiQuery)->count();
        $totalAmount = (float) (clone $kpiQuery)->sum('total_amount');
        $waitingAmount = (float) (clone $kpiQuery)->where('status', 'WAITING_APPROVAL')->sum('total_amount');

        $unsettledKpiQuery = Order::where('status', 'RECEIVED')->whereDoesntHave('settlements');
        if ($isBranch) {
            $unsettledKpiQuery->where('requesting_organization_id', $user->organization_id);
        }
        $unsettledCount = $unsettledKpiQuery->count();

        $organizations = $isBranch
            ? Organization::where('id', $user->organization_id)->get(['id', 'name', 'code'])
            : Organization::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return view('finance.settlements.index', compact(
            'unsettledOrders',
            'settlements',
            'tab',
            'search',
            'organizationId',
            'organizations',
            'perPage',
            'unsettledCount',
            'waitingApprovalCount',
            'postedCount',
            'allCount',
            'totalAmount',
            'waitingAmount'
        ));
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
