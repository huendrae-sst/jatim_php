<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PocCoreItemsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $admin = User::where('role', 'SUPER_ADMIN')->first() ?? User::first();
        $adminId = $admin?->id;

        $catData = [
            ['code' => 'CAT-ATM', 'name' => 'Kartu ATM & Debet/Kredit', 'description' => 'Persediaan blank card, kartu ATM instan, dan kartu bernama Bank Jatim'],
            ['code' => 'CAT-TKN', 'name' => 'Hard Token & Security Device', 'description' => 'Perangkat token otentikasi perbankan / token PIN nasabah & corporate'],
            ['code' => 'CAT-KUE', 'name' => 'Kartu Uang Elektronik (KUE)', 'description' => 'Kartu contactless prepaid e-money / Flazz / Brizzi co-branding Bank Jatim'],
        ];

        $categories = [];
        foreach ($catData as $cd) {
            $categories[$cd['code']] = Category::firstOrCreate(['code' => $cd['code']], $cd);
        }

        $itemsData = [
            [
                'category_id' => $categories['CAT-ATM']->id,
                'sku' => 'ATM-INST-001',
                'barcode' => '8991001021',
                'name' => 'Kartu ATM Instan Chip GPN Bank Jatim',
                'specification' => 'Blank card chip hybrid EMV contactless GPN instan',
                'uom' => 'PCS',
                'min_stock' => 500,
                'safety_stock' => 1000,
                'reorder_point' => 1500,
                'lead_time_days' => 14,
                'estimated_unit_price' => 15000,
                'is_active' => true,
            ],
            [
                'category_id' => $categories['CAT-ATM']->id,
                'sku' => 'ATM-NAME-001',
                'barcode' => '8991001022',
                'name' => 'Kartu ATM Bernama Mastercard Platinum',
                'specification' => 'Kartu debit personalisasi bernama chip EMV emboss',
                'uom' => 'PCS',
                'min_stock' => 200,
                'safety_stock' => 500,
                'reorder_point' => 800,
                'lead_time_days' => 21,
                'estimated_unit_price' => 18500,
                'is_active' => true,
            ],
            [
                'category_id' => $categories['CAT-TKN']->id,
                'sku' => 'TKN-HRD-001',
                'barcode' => '8991001023',
                'name' => 'Hard Token Internet Banking Digipass',
                'specification' => 'Hardware OTP token One-Time Password Digipass Vasco',
                'uom' => 'UNIT',
                'min_stock' => 100,
                'safety_stock' => 250,
                'reorder_point' => 400,
                'lead_time_days' => 30,
                'estimated_unit_price' => 125000,
                'is_active' => true,
            ],
            [
                'category_id' => $categories['CAT-KUE']->id,
                'sku' => 'KUE-FLZ-001',
                'barcode' => '8991001024',
                'name' => 'Kartu Uang Elektronik (KUE) Co-Branding',
                'specification' => 'Kartu prabayar contactless NFC co-branding Bank Jatim',
                'uom' => 'PCS',
                'min_stock' => 300,
                'safety_stock' => 600,
                'reorder_point' => 1000,
                'lead_time_days' => 14,
                'estimated_unit_price' => 25000,
                'is_active' => true,
            ],
        ];

        $items = [];
        foreach ($itemsData as $id) {
            $items[$id['sku']] = Item::firstOrCreate(['sku' => $id['sku']], $id);
        }

        // Setup stock balances for Central Warehouse and Branches
        $whCentral = Warehouse::where('code', 'GD-RKT')->first();
        if ($whCentral) {
            foreach ($items as $sku => $it) {
                $onHandCentral = match ($sku) {
                    'ATM-INST-001', 'KUE-FLZ-001' => 1000,
                    'TKN-HRD-001', 'ATM-NAME-001' => 500,
                    default => 100,
                };
                $reservedCentral = match ($sku) {
                    'ATM-INST-001' => 50,
                    default => 0,
                };

                StockBalance::firstOrCreate(
                    [
                        'warehouse_id' => $whCentral->id,
                        'item_id' => $it->id,
                    ],
                    [
                        'on_hand' => $onHandCentral,
                        'reserved' => $reservedCentral,
                        'hold' => 0,
                        'damaged' => 0,
                    ]
                );

                StockLedger::firstOrCreate(
                    [
                        'warehouse_id' => $whCentral->id,
                        'item_id' => $it->id,
                        'reference_number' => 'INIT-2026-'.$it->sku,
                    ],
                    [
                        'transaction_type' => 'STOCK_INITIAL',
                        'qty_in' => $onHandCentral,
                        'qty_out' => 0,
                        'unit_cost' => $it->estimated_unit_price,
                        'total_value' => $it->estimated_unit_price * $onHandCentral,
                        'balance_after' => $onHandCentral,
                        'notes' => 'Saldo awal migrasi persediaan Bank Jatim TA 2026',
                        'created_by_user_id' => $adminId,
                        'created_at' => $now->copy()->subMonths(2),
                    ]
                );
            }
        }

        // Branch warehouses (KC-SBY, KC-MLG, KC-KDR)
        $branchWarehouses = Warehouse::whereIn('code', ['KC-SBY', 'KC-MLG', 'KC-KDR'])->get();
        foreach ($branchWarehouses as $whBranch) {
            foreach ($items as $sku => $it) {
                $onHandBranch = 25;
                StockBalance::firstOrCreate(
                    [
                        'warehouse_id' => $whBranch->id,
                        'item_id' => $it->id,
                    ],
                    [
                        'on_hand' => $onHandBranch,
                        'reserved' => 0,
                        'hold' => 0,
                        'damaged' => 0,
                    ]
                );

                StockLedger::firstOrCreate(
                    [
                        'warehouse_id' => $whBranch->id,
                        'item_id' => $it->id,
                        'reference_number' => 'INIT-'.$whBranch->code.'-'.$it->sku,
                    ],
                    [
                        'transaction_type' => 'STOCK_INITIAL',
                        'qty_in' => $onHandBranch,
                        'qty_out' => 0,
                        'unit_cost' => $it->estimated_unit_price,
                        'total_value' => $it->estimated_unit_price * $onHandBranch,
                        'balance_after' => $onHandBranch,
                        'notes' => "Saldo awal cabang {$whBranch->code}",
                        'created_by_user_id' => $adminId,
                        'created_at' => $now->copy()->subMonths(2),
                    ]
                );
            }
        }
    }
}
