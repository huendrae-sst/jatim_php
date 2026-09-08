<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function createSettlementForOrder(Order $order, User $user): Settlement
    {
        return DB::transaction(function () use ($order, $user) {
            $settlNumber = 'SETTL/'.date('Y/m').'/'.sprintf('%04d', Settlement::count() + 1);
            $headOffice = Organization::where('type', 'HEAD_OFFICE')->first() ?: Organization::first();
            $shipment = $order->shipments->first();

            $itemAmount = 0;
            foreach ($order->items as $item) {
                $itemAmount += ($item->qty_received * $item->unit_price_ref);
            }
            $shippingAmount = $shipment ? (float) $shipment->shipping_cost : 0;
            $totalAmount = $itemAmount + $shippingAmount;

            $settlement = Settlement::create([
                'settlement_number' => $settlNumber,
                'order_id' => $order->id,
                'debit_organization_id' => $order->requesting_organization_id,
                'credit_organization_id' => $headOffice->id,
                'debit_cost_center' => $order->requestingOrganization->cost_center_code ?: 'CC-BRANCH',
                'credit_cost_center' => $headOffice->cost_center_code ?: 'CC-KP-LOG',
                'item_amount' => $itemAmount,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
                'status' => 'WAITING_APPROVAL',
                'created_by_user_id' => $user->id,
            ]);

            AuditTrailService::log('CREATE_SETTLEMENT', $settlement, null, $settlement->toArray(), $user);

            NotificationService::sendActionRequired(
                "Approval Settlement Finansial {$settlement->settlement_number}",
                'Jurnal inter-unit settlement sebesar Rp '.number_format($totalAmount, 0, ',', '.').' menunggu persetujuan dan posting.',
                'FINANCE_APPROVER',
                null,
                'SETTLEMENT',
                $settlement->id,
                '/finance/settlements'
            );

            return $settlement;
        });
    }

    public function approveAndPostSettlement(Settlement $settlement, User $approver): Settlement
    {
        return DB::transaction(function () use ($settlement, $approver) {
            $settlement->status = 'POSTED';
            $settlement->approved_by_user_id = $approver->id;
            $settlement->posted_at = now();
            $settlement->save();

            // Convert Budget Commitment into Realization for debit unit
            $order = $settlement->order;
            $currentYear = (int) date('Y');
            $budget = Budget::where('organization_id', $order->requesting_organization_id)->where('year', $currentYear)->first();
            if ($budget) {
                $budget->committed_amount = max(0, $budget->committed_amount - $settlement->total_amount);
                $budget->realized_amount += $settlement->total_amount;
                $budget->save();
            }

            // Close order to COMPLETED
            $order->status = 'COMPLETED';
            $order->completed_at = now();
            $order->save();

            AuditTrailService::log('POST_SETTLEMENT', $settlement, null, ['status' => 'POSTED'], $approver);

            NotificationService::sendUser(
                $order->created_by_user_id,
                "Siklus Order {$order->order_number} Selesai Penuh (COMPLETED)",
                "Settlement pembukuan antarunit (No: {$settlement->settlement_number}) telah disetujui dan diposting. Order Anda resmi selesai.",
                'INFORMATION',
                'INFO',
                'ORDER',
                $order->id,
                "/orders/{$order->id}"
            );

            NotificationService::sendRole(
                'FINANCE_OFFICER',
                'Settlement Diposting ke Realisasi Anggaran',
                "Settlement {$settlement->settlement_number} senilai Rp ".number_format($settlement->total_amount, 0, ',', '.').' telah disetujui dan diposting ke realisasi anggaran.',
                null,
                'INFORMATION',
                'INFO',
                'SETTLEMENT',
                $settlement->id,
                '/finance/settlements'
            );

            return $settlement;
        });
    }
}
