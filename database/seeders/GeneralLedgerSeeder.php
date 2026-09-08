<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class GeneralLedgerSeeder extends Seeder
{
    public function run(): void
    {
        if (GeneralLedgerEntry::count() > 0) {
            return;
        }

        // Ensure master accounting is seeded
        $this->call(AccountingMasterSeeder::class);

        $superAdmin = User::where('role', 'SUPER_ADMIN')->first() ?: User::first();
        $headOffice = Organization::where('type', 'HEAD_OFFICE')->first() ?: Organization::first();
        $kcSurabaya = Organization::where('code', 'KC-SBY')->first();
        $kcMalang = Organization::where('code', 'KC-MLG')->first();
        $kcKediri = Organization::where('code', 'KC-KDR')->first();
        $kcpGubeng = Organization::where('code', 'KCP-GBG')->first();

        // Chart of Accounts lookup
        $atkInventory = ChartOfAccount::where('account_code', '11301')->first();
        $printInventory = ChartOfAccount::where('account_code', '11302')->first();
        $itInventory = ChartOfAccount::where('account_code', '11303')->first();
        $vendorPayable = ChartOfAccount::where('account_code', '21101')->first();
        $courierPayable = ChartOfAccount::where('account_code', '21102')->first();
        $rakLogistik = ChartOfAccount::where('account_code', '31101')->first();
        $atkExpense = ChartOfAccount::where('account_code', '51201')->first();
        $printExpense = ChartOfAccount::where('account_code', '51202')->first();
        $itExpense = ChartOfAccount::where('account_code', '51203')->first();
        $shippingExpense = ChartOfAccount::where('account_code', '51205')->first();
        $lossExpense = ChartOfAccount::where('account_code', '51206')->first();

        $entries = [
            // 1. Initial Stock Balances (Kantor Pusat)
            [
                'journal_number' => 'JRN/2026/08/0001',
                'transaction_date' => Carbon::parse('2026-08-01'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $atkInventory->id,
                'account_code' => $atkInventory->account_code,
                'account_name' => $atkInventory->account_name,
                'reference_type' => 'INITIAL_BALANCE',
                'reference_number' => 'INIT-STK-001',
                'description' => 'Saldo awal persediaan fisik Alat Tulis Kantor Gudang Logistik Pusat',
                'debit' => 85000000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-08-01 08:00:00'),
            ],
            [
                'journal_number' => 'JRN/2026/08/0001',
                'transaction_date' => Carbon::parse('2026-08-01'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $rakLogistik->id,
                'account_code' => $rakLogistik->account_code,
                'account_name' => $rakLogistik->account_name,
                'reference_type' => 'INITIAL_BALANCE',
                'reference_number' => 'INIT-STK-001',
                'description' => 'Kontra saldo ekuitas persediaan logistik pusat',
                'debit' => 0,
                'credit' => 85000000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-08-01 08:00:00'),
            ],

            // 2. Penerimaan Pengadaan Cetakan PO (Kantor Pusat)
            [
                'journal_number' => 'JRN/2026/08/0002',
                'transaction_date' => Carbon::parse('2026-08-15'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $printInventory->id,
                'account_code' => $printInventory->account_code,
                'account_name' => $printInventory->account_name,
                'reference_type' => 'GOODS_RECEIPT',
                'reference_number' => 'PO/2026/08/0010',
                'description' => 'Penerimaan barang pengadaan warkat bilyet giro dan formulir bank dari vendor',
                'debit' => 42000000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-08-15 11:30:00'),
            ],
            [
                'journal_number' => 'JRN/2026/08/0002',
                'transaction_date' => Carbon::parse('2026-08-15'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $vendorPayable->id,
                'account_code' => $vendorPayable->account_code,
                'account_name' => $vendorPayable->account_name,
                'reference_type' => 'GOODS_RECEIPT',
                'reference_number' => 'PO/2026/08/0010',
                'description' => 'Hutang usaha rekanan vendor atas pengadaan warkat bilyet giro',
                'debit' => 0,
                'credit' => 42000000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-08-15 11:30:00'),
            ],

            // 3. Settlement Cabang KC Surabaya
            [
                'journal_number' => 'JRN/2026/08/0003',
                'transaction_date' => Carbon::parse('2026-08-25'),
                'organization_id' => $kcSurabaya?->id,
                'cost_center_code' => $kcSurabaya?->cost_center_code ?: 'CC-KC-SBY',
                'chart_of_account_id' => $atkExpense->id,
                'account_code' => $atkExpense->account_code,
                'account_name' => $atkExpense->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/08/0001',
                'description' => 'Pembebanan logistik atas Order ORD/2026/08/0012 - KC Surabaya',
                'debit' => 4500000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-08-25 14:00:00'),
            ],
            [
                'journal_number' => 'JRN/2026/08/0003',
                'transaction_date' => Carbon::parse('2026-08-25'),
                'organization_id' => $kcSurabaya?->id,
                'cost_center_code' => $kcSurabaya?->cost_center_code ?: 'CC-KC-SBY',
                'chart_of_account_id' => $shippingExpense->id,
                'account_code' => $shippingExpense->account_code,
                'account_name' => $shippingExpense->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/08/0001',
                'description' => 'Beban ongkos kirim ekspedisi Order ORD/2026/08/0012 - KC Surabaya',
                'debit' => 150000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-08-25 14:00:00'),
            ],
            [
                'journal_number' => 'JRN/2026/08/0003',
                'transaction_date' => Carbon::parse('2026-08-25'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $atkInventory->id,
                'account_code' => $atkInventory->account_code,
                'account_name' => $atkInventory->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/08/0001',
                'description' => 'Pengeluaran stok ATK pemenuhan cabang KC Surabaya',
                'debit' => 0,
                'credit' => 4500000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-08-25 14:00:00'),
            ],
            [
                'journal_number' => 'JRN/2026/08/0003',
                'transaction_date' => Carbon::parse('2026-08-25'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $courierPayable->id,
                'account_code' => $courierPayable->account_code,
                'account_name' => $courierPayable->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/08/0001',
                'description' => 'Hutang biaya kurir ekspedisi pengiriman ke KC Surabaya',
                'debit' => 0,
                'credit' => 150000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-08-25 14:00:00'),
            ],

            // 4. Settlement Cabang KC Malang
            [
                'journal_number' => 'JRN/2026/09/0001',
                'transaction_date' => Carbon::parse('2026-09-02'),
                'organization_id' => $kcMalang?->id,
                'cost_center_code' => $kcMalang?->cost_center_code ?: 'CC-KC-MLG',
                'chart_of_account_id' => $atkExpense->id,
                'account_code' => $atkExpense->account_code,
                'account_name' => $atkExpense->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/09/0001',
                'description' => 'Pembebanan logistik atas Order ORD/2026/09/0004 - KC Malang',
                'debit' => 6200000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-02 10:15:00'),
            ],
            [
                'journal_number' => 'JRN/2026/09/0001',
                'transaction_date' => Carbon::parse('2026-09-02'),
                'organization_id' => $kcMalang?->id,
                'cost_center_code' => $kcMalang?->cost_center_code ?: 'CC-KC-MLG',
                'chart_of_account_id' => $shippingExpense->id,
                'account_code' => $shippingExpense->account_code,
                'account_name' => $shippingExpense->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/09/0001',
                'description' => 'Beban ongkos kirim ekspedisi Order ORD/2026/09/0004 - KC Malang',
                'debit' => 200000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-02 10:15:00'),
            ],
            [
                'journal_number' => 'JRN/2026/09/0001',
                'transaction_date' => Carbon::parse('2026-09-02'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $atkInventory->id,
                'account_code' => $atkInventory->account_code,
                'account_name' => $atkInventory->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/09/0001',
                'description' => 'Pengeluaran stok ATK pemenuhan cabang KC Malang',
                'debit' => 0,
                'credit' => 6200000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-02 10:15:00'),
            ],
            [
                'journal_number' => 'JRN/2026/09/0001',
                'transaction_date' => Carbon::parse('2026-09-02'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $courierPayable->id,
                'account_code' => $courierPayable->account_code,
                'account_name' => $courierPayable->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/09/0001',
                'description' => 'Hutang biaya kurir ekspedisi pengiriman ke KC Malang',
                'debit' => 0,
                'credit' => 200000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-02 10:15:00'),
            ],

            // 5. Settlement Cabang KC Kediri (Warkat & Formulir)
            [
                'journal_number' => 'JRN/2026/09/0002',
                'transaction_date' => Carbon::parse('2026-09-05'),
                'organization_id' => $kcKediri?->id,
                'cost_center_code' => $kcKediri?->cost_center_code ?: 'CC-KC-KDR',
                'chart_of_account_id' => $printExpense->id,
                'account_code' => $printExpense->account_code,
                'account_name' => $printExpense->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/09/0002',
                'description' => 'Pembebanan formulir warkat bilyet atas Order ORD/2026/09/0007 - KC Kediri',
                'debit' => 3850000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-05 13:45:00'),
            ],
            [
                'journal_number' => 'JRN/2026/09/0002',
                'transaction_date' => Carbon::parse('2026-09-05'),
                'organization_id' => $kcKediri?->id,
                'cost_center_code' => $kcKediri?->cost_center_code ?: 'CC-KC-KDR',
                'chart_of_account_id' => $shippingExpense->id,
                'account_code' => $shippingExpense->account_code,
                'account_name' => $shippingExpense->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/09/0002',
                'description' => 'Beban ongkos kirim ekspedisi Order ORD/2026/09/0007 - KC Kediri',
                'debit' => 120000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-05 13:45:00'),
            ],
            [
                'journal_number' => 'JRN/2026/09/0002',
                'transaction_date' => Carbon::parse('2026-09-05'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $printInventory->id,
                'account_code' => $printInventory->account_code,
                'account_name' => $printInventory->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/09/0002',
                'description' => 'Pengeluaran warkat cetakan bilyet dari gudang pusat ke KC Kediri',
                'debit' => 0,
                'credit' => 3850000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-05 13:45:00'),
            ],
            [
                'journal_number' => 'JRN/2026/09/0002',
                'transaction_date' => Carbon::parse('2026-09-05'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $courierPayable->id,
                'account_code' => $courierPayable->account_code,
                'account_name' => $courierPayable->account_name,
                'reference_type' => 'SETTLEMENT',
                'reference_number' => 'SETTL/2026/09/0002',
                'description' => 'Hutang biaya kurir ekspedisi pengiriman ke KC Kediri',
                'debit' => 0,
                'credit' => 120000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-05 13:45:00'),
            ],

            // 6. Penyesuaian Selisih Rusak / Loss (Stock Opname)
            [
                'journal_number' => 'JRN/2026/09/0003',
                'transaction_date' => Carbon::parse('2026-09-07'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $lossExpense->id,
                'account_code' => $lossExpense->account_code,
                'account_name' => $lossExpense->account_name,
                'reference_type' => 'STOCK_OPNAME',
                'reference_number' => 'SO/2026/09/0001',
                'description' => 'Beban kerugian barang rusak fisik pada pelaksanaan stock opname gudang',
                'debit' => 420000,
                'credit' => 0,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-07 16:00:00'),
            ],
            [
                'journal_number' => 'JRN/2026/09/0003',
                'transaction_date' => Carbon::parse('2026-09-07'),
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $atkInventory->id,
                'account_code' => $atkInventory->account_code,
                'account_name' => $atkInventory->account_name,
                'reference_type' => 'STOCK_OPNAME',
                'reference_number' => 'SO/2026/09/0001',
                'description' => 'Penyesuaian pengurangan persediaan rusak stock opname gudang',
                'debit' => 0,
                'credit' => 420000,
                'created_by_user_id' => $superAdmin?->id,
                'posted_at' => Carbon::parse('2026-09-07 16:00:00'),
            ],
        ];

        foreach ($entries as $e) {
            GeneralLedgerEntry::create($e);
        }
    }
}
