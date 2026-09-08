<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class AccountingMasterSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Cost Centers if table is empty
        if (CostCenter::count() === 0) {
            $organizations = Organization::all();
            $costCenterDepartments = [
                'KP-001' => ['dept' => 'Divisi Umum & Logistik', 'pic' => 'Budi Santoso'],
                'GD-RKT' => ['dept' => 'Gudang Pusat SIER', 'pic' => 'Gudang Logistik Officer'],
                'KC-SBY' => ['dept' => 'Bagian Operasional & Layanan', 'pic' => 'Branch Operations Head SBY'],
                'KC-MLG' => ['dept' => 'Bagian Operasional Cabang', 'pic' => 'Branch Operations Head MLG'],
                'KC-KDR' => ['dept' => 'Bagian Operasional Cabang', 'pic' => 'Branch Operations Head KDR'],
                'KC-JBR' => ['dept' => 'Bagian Operasional Cabang', 'pic' => 'Branch Operations Head JBR'],
                'KC-BWX' => ['dept' => 'Bagian Operasional Cabang', 'pic' => 'Branch Operations Head BWX'],
                'KC-MDN' => ['dept' => 'Bagian Operasional Cabang', 'pic' => 'Branch Operations Head MDN'],
                'KC-BJN' => ['dept' => 'Bagian Operasional Cabang', 'pic' => 'Branch Operations Head BJN'],
                'KCP-GBG' => ['dept' => 'Operasional Capem Gubeng', 'pic' => 'Pimpinan Capem Gubeng'],
                'KCP-DNY' => ['dept' => 'Operasional Capem Dinoyo', 'pic' => 'Pimpinan Capem Dinoyo'],
                'KCP-BAT' => ['dept' => 'Operasional Capem Batu', 'pic' => 'Pimpinan Capem Batu'],
            ];

            foreach ($organizations as $org) {
                if (! $org->cost_center_code) {
                    continue;
                }

                $deptInfo = $costCenterDepartments[$org->code] ?? ['dept' => 'Operasional Cabang', 'pic' => 'Pimpinan Unit'];
                CostCenter::firstOrCreate(
                    ['code' => $org->cost_center_code],
                    [
                        'name' => 'Cost Center '.$org->name,
                        'organization_id' => $org->id,
                        'department' => $deptInfo['dept'],
                        'pic_name' => $deptInfo['pic'],
                        'is_active' => true,
                        'notes' => 'Pusat biaya resmi untuk alokasi beban logistik '.$org->name,
                    ]
                );
            }
        }

        // 2. Seed Chart of Accounts if table is empty
        if (ChartOfAccount::count() === 0) {
            $coaData = [
                // ASSETS (1)
                ['account_code' => '11301', 'account_name' => 'Persediaan Alat Tulis Kantor (ATK)', 'account_type' => 'ASSET', 'classification' => 'Aset Lancar', 'normal_balance' => 'DEBIT', 'description' => 'Rekening persediaan untuk seluruh barang ATK dan kertas'],
                ['account_code' => '11302', 'account_name' => 'Persediaan Cetakan & Formulir Warkat', 'account_type' => 'ASSET', 'classification' => 'Aset Lancar', 'normal_balance' => 'DEBIT', 'description' => 'Rekening persediaan bilyet giro, slip, cek, dan formulir perbankan'],
                ['account_code' => '11303', 'account_name' => 'Persediaan Perlengkapan Komputer & IT', 'account_type' => 'ASSET', 'classification' => 'Aset Lancar', 'normal_balance' => 'DEBIT', 'description' => 'Persediaan toner, ribbon, passbook printer, dan periferal IT'],
                ['account_code' => '11304', 'account_name' => 'Persediaan Barang Promosi & Souvenir', 'account_type' => 'ASSET', 'classification' => 'Aset Lancar', 'normal_balance' => 'DEBIT', 'description' => 'Persediaan materi promosi nasabah, merchandise, dan souvenir'],
                ['account_code' => '11305', 'account_name' => 'Persediaan Khazanah & Keamanan', 'account_type' => 'ASSET', 'classification' => 'Aset Lancar', 'normal_balance' => 'DEBIT', 'description' => 'Persediaan segel security bag, gembok khazanah, dan amplop gaji'],
                ['account_code' => '11401', 'account_name' => 'Uang Muka Pengadaan Logistik', 'account_type' => 'ASSET', 'classification' => 'Aset Lancar', 'normal_balance' => 'DEBIT', 'description' => 'Uang muka kerja kepada rekanan pengadaan barang'],

                // LIABILITIES (2)
                ['account_code' => '21101', 'account_name' => 'Hutang Usaha Rekanan Vendor Pengadaan', 'account_type' => 'LIABILITY', 'classification' => 'Kewajiban Lancar', 'normal_balance' => 'CREDIT', 'description' => 'Kewajiban pembayaran PO pengadaan barang kepada pihak ketiga'],
                ['account_code' => '21102', 'account_name' => 'Hutang Biaya Ekspedisi & Distribusi', 'account_type' => 'LIABILITY', 'classification' => 'Kewajiban Lancar', 'normal_balance' => 'CREDIT', 'description' => 'Kewajiban pembayaran ongkos kirim kepada jasa kurir/ekspedisi'],
                ['account_code' => '21201', 'account_name' => 'Titipan Pajak Pertambahan Nilai (PPN 11%)', 'account_type' => 'LIABILITY', 'classification' => 'Kewajiban Lancar', 'normal_balance' => 'CREDIT', 'description' => 'Kewajiban PPN masukan/keluaran atas transaksi pengadaan'],

                // EQUITY / INTER-OFFICE (3)
                ['account_code' => '31101', 'account_name' => 'RAK (Rekening Antar Kantor) Logistik Pusat', 'account_type' => 'EQUITY', 'classification' => 'Antar Kantor', 'normal_balance' => 'CREDIT', 'description' => 'Rekening perantara kliring inter-unit pemenuhan barang cabang'],

                // EXPENSES (5)
                ['account_code' => '51201', 'account_name' => 'Beban Pemakaian ATK & Kertas Cabang', 'account_type' => 'EXPENSE', 'classification' => 'Beban Operasional', 'normal_balance' => 'DEBIT', 'description' => 'Beban operasional atas pemakaian ATK oleh unit kerja cabang'],
                ['account_code' => '51202', 'account_name' => 'Beban Cetakan & Formulir Perbankan', 'account_type' => 'EXPENSE', 'classification' => 'Beban Operasional', 'normal_balance' => 'DEBIT', 'description' => 'Beban operasional atas penggunaan formulir warkat operasional'],
                ['account_code' => '51203', 'account_name' => 'Beban Perlengkapan IT & Konsumabel', 'account_type' => 'EXPENSE', 'classification' => 'Beban Operasional', 'normal_balance' => 'DEBIT', 'description' => 'Beban toner dan ribbon pemakaian cabang operasional'],
                ['account_code' => '51204', 'account_name' => 'Beban Promosi & Pemasaran Cabang', 'account_type' => 'EXPENSE', 'classification' => 'Beban Operasional', 'normal_balance' => 'DEBIT', 'description' => 'Beban souvenir dan materi promosi acara cabang'],
                ['account_code' => '51205', 'account_name' => 'Beban Jasa Ekspedisi & Pengiriman Logistik', 'account_type' => 'EXPENSE', 'classification' => 'Beban Operasional', 'normal_balance' => 'DEBIT', 'description' => 'Beban pengiriman paket logistik dari pusat ke cabang'],
                ['account_code' => '51206', 'account_name' => 'Beban Kerusakan & Selisih Persediaan (Loss)', 'account_type' => 'EXPENSE', 'classification' => 'Beban Non-Operasional', 'normal_balance' => 'DEBIT', 'description' => 'Beban penyesuaian selisih fisik rusak atau selisih stock opname'],
            ];

            foreach ($coaData as $cd) {
                ChartOfAccount::firstOrCreate(
                    ['account_code' => $cd['account_code']],
                    array_merge($cd, ['is_active' => true])
                );
            }
        }
    }
}
