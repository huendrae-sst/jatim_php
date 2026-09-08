<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Courier;
use App\Models\Discrepancy;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\ItemConversion;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Receiving;
use App\Models\Settlement;
use App\Models\Shipment;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\SwitchingStock;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehousePacking;
use App\Models\WarehousePicking;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // =========================================================================
        // 1. MASTER ORGANISASI (12 Unit Kerja Bank Jatim)
        // =========================================================================
        $orgData = [
            ['code' => 'KP-001', 'name' => 'Kantor Pusat Bank Jatim (Divisi Umum)', 'type' => 'HEAD_OFFICE', 'address' => 'Jl. Basuki Rahmat No. 98-104', 'city' => 'Surabaya', 'cost_center_code' => 'CC-KP-001'],
            ['code' => 'GD-RKT', 'name' => 'Gudang Logistik Pusat SIER Rungkut', 'type' => 'WAREHOUSE', 'address' => 'Kawasan Industri SIER Blok C-12', 'city' => 'Surabaya', 'cost_center_code' => 'CC-LOG-001'],
            ['code' => 'KC-SBY', 'name' => 'Kantor Cabang Utama Surabaya', 'type' => 'MAIN_BRANCH', 'address' => 'Jl. Pahlawan No. 24', 'city' => 'Surabaya', 'cost_center_code' => 'CC-KC-SBY'],
            ['code' => 'KC-MLG', 'name' => 'Kantor Cabang Malang', 'type' => 'MAIN_BRANCH', 'address' => 'Jl. Jaksa Agung Suprapto No. 32', 'city' => 'Malang', 'cost_center_code' => 'CC-KC-MLG'],
            ['code' => 'KC-KDR', 'name' => 'Kantor Cabang Kediri', 'type' => 'MAIN_BRANCH', 'address' => 'Jl. Brawijaya No. 10', 'city' => 'Kediri', 'cost_center_code' => 'CC-KC-KDR'],
            ['code' => 'KC-JBR', 'name' => 'Kantor Cabang Jember', 'type' => 'MAIN_BRANCH', 'address' => 'Jl. Ahmad Yani No. 45', 'city' => 'Jember', 'cost_center_code' => 'CC-KC-JBR'],
            ['code' => 'KC-BWX', 'name' => 'Kantor Cabang Banyuwangi', 'type' => 'MAIN_BRANCH', 'address' => 'Jl. Basuki Rahmat No. 12', 'city' => 'Banyuwangi', 'cost_center_code' => 'CC-KC-BWX'],
            ['code' => 'KC-MDN', 'name' => 'Kantor Cabang Madiun', 'type' => 'MAIN_BRANCH', 'address' => 'Jl. Pahlawan No. 58', 'city' => 'Madiun', 'cost_center_code' => 'CC-KC-MDN'],
            ['code' => 'KC-BJN', 'name' => 'Kantor Cabang Bojonegoro', 'type' => 'MAIN_BRANCH', 'address' => 'Jl. Veteran No. 18', 'city' => 'Bojonegoro', 'cost_center_code' => 'CC-KC-BJN'],
            ['code' => 'KCP-GBG', 'name' => 'Kantor Cabang Pembantu Gubeng', 'type' => 'SUB_BRANCH', 'address' => 'Jl. Raya Gubeng No. 15', 'city' => 'Surabaya', 'cost_center_code' => 'CC-KCP-GBG'],
            ['code' => 'KCP-DNY', 'name' => 'Kantor Cabang Pembantu Dinoyo', 'type' => 'SUB_BRANCH', 'address' => 'Jl. MT Haryono No. 80', 'city' => 'Malang', 'cost_center_code' => 'CC-KCP-DNY'],
            ['code' => 'KCP-BAT', 'name' => 'Kantor Cabang Pembantu Batu', 'type' => 'SUB_BRANCH', 'address' => 'Jl. Panglima Sudirman No. 22', 'city' => 'Batu', 'cost_center_code' => 'CC-KCP-BAT'],
        ];

        $organizations = [];
        foreach ($orgData as $od) {
            $organizations[$od['code']] = Organization::create(array_merge($od, ['is_active' => true]));
        }

        // =========================================================================
        // 2. MASTER GUDANG & LOKASI (12 Gudang)
        // =========================================================================
        $warehouses = [];
        foreach ($organizations as $code => $org) {
            $type = $code === 'GD-RKT' ? 'CENTRAL_LOGISTICS' : 'BRANCH_STORAGE';
            $warehouses[$code] = Warehouse::create([
                'organization_id' => $org->id,
                'code' => 'WH-'.str_replace('-', '', $code),
                'name' => 'Gudang '.$org->name,
                'type' => $type,
                'address' => $org->address,
                'is_active' => true,
            ]);
        }

        // =========================================================================
        // 2b. MASTER COST CENTER (12 Unit Kerja Bank Jatim)
        // =========================================================================
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

        foreach ($organizations as $code => $org) {
            $deptInfo = $costCenterDepartments[$code] ?? ['dept' => 'Operasional', 'pic' => 'Pimpinan Unit'];
            CostCenter::create([
                'code' => $org->cost_center_code,
                'name' => 'Cost Center '.$org->name,
                'organization_id' => $org->id,
                'department' => $deptInfo['dept'],
                'pic_name' => $deptInfo['pic'],
                'is_active' => true,
                'notes' => 'Pusat biaya resmi untuk alokasi beban logistik '.$org->name,
            ]);
        }

        // =========================================================================
        // 2c. MASTER CHART OF ACCOUNTS (CoA / Rekening Buku Besar GL Bank Jatim)
        // =========================================================================
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
            ChartOfAccount::create(array_merge($cd, ['is_active' => true]));
        }

        // =========================================================================
        // 3. MASTER KATEGORI BARANG (10 Kategori)
        // =========================================================================
        $catData = [
            ['code' => 'CAT-ATK', 'name' => 'Alat Tulis Kantor (ATK)', 'description' => 'Kertas, pena, map, binder, dan perlengkapan administrasi'],
            ['code' => 'CAT-CTK', 'name' => 'Cetakan & Warkat Kas', 'description' => 'Slip setoran, bilyet giro, cek, buku tabungan'],
            ['code' => 'CAT-IT', 'name' => 'IT & Perlengkapan Komputer', 'description' => 'Toner printer, ribbon passbook, cartridge, periferal EDC'],
            ['code' => 'CAT-PRM', 'name' => 'Sarana Promosi & Branding', 'description' => 'Brosur, spanduk, merchandise, gimmick nasabah'],
            ['code' => 'CAT-KHZ', 'name' => 'Khazanah & Keamanan Kas', 'description' => 'Segel kas, kantong uang kanvas, lead seal, security bag'],
            ['code' => 'CAT-SRG', 'name' => 'Seragam & Atribut Pegawai', 'description' => 'Seragam CS, teller, ID card holder, name tag emas'],
            ['code' => 'CAT-FLM', 'name' => 'Formulir Pembukaan Rekening', 'description' => 'Formulir CIF, akad kredit, perjanjian pembukaan rekening'],
            ['code' => 'CAT-TLR', 'name' => 'Perlengkapan Teller & Kas', 'description' => 'Stempel validasi, bak tinta, spons penghitung uang'],
            ['code' => 'CAT-SVN', 'name' => 'Souvenir Nasabah Prioritas', 'description' => 'Tumbler premium, powerbank eksklusif, payung golf'],
            ['code' => 'CAT-NET', 'name' => 'Perangkat Kabel & Jaringan', 'description' => 'Patch cord CAT6, converter, kabel HDMI, crimping tool'],
        ];

        $categories = [];
        foreach ($catData as $cd) {
            $categories[$cd['code']] = Category::create($cd);
        }

        // =========================================================================
        // 4. MASTER BARANG / ITEM (20 SKU Barang)
        // =========================================================================
        $itemsData = [
            ['category_id' => $categories['CAT-IT']->id, 'sku' => 'IT-TNR-001', 'barcode' => '8991001001', 'name' => 'Toner HP LaserJet Enterprise MFP M528', 'specification' => 'Black Original LaserJet Toner Cartridge 89A', 'uom' => 'UNIT', 'min_stock' => 10, 'safety_stock' => 20, 'reorder_point' => 35, 'lead_time_days' => 7, 'estimated_unit_price' => 2450000],
            ['category_id' => $categories['CAT-IT']->id, 'sku' => 'IT-RBN-001', 'barcode' => '8991001002', 'name' => 'Ribbon Cartridge Olivetti PR2 Plus', 'specification' => 'Original Black Ribbon for Passbook Printer PR2/PR2+', 'uom' => 'PCS', 'min_stock' => 25, 'safety_stock' => 50, 'reorder_point' => 80, 'lead_time_days' => 5, 'estimated_unit_price' => 75000],
            ['category_id' => $categories['CAT-IT']->id, 'sku' => 'IT-EDC-001', 'barcode' => '8991001003', 'name' => 'Kertas Thermal Roll EDC Bank Jatim 57x40mm', 'specification' => 'Kertas thermal putih logo Bank Jatim isi 100 roll/box', 'uom' => 'BOX', 'min_stock' => 15, 'safety_stock' => 30, 'reorder_point' => 50, 'lead_time_days' => 4, 'estimated_unit_price' => 320000],
            ['category_id' => $categories['CAT-ATK']->id, 'sku' => 'ATK-KRT-001', 'barcode' => '8991001004', 'name' => 'Kertas HVS PaperOne A4 80gr Box (5 Rim)', 'specification' => 'High quality laser copy paper 80 GSM per box isi 5 rim', 'uom' => 'BOX', 'min_stock' => 40, 'safety_stock' => 80, 'reorder_point' => 120, 'lead_time_days' => 3, 'estimated_unit_price' => 265000],
            ['category_id' => $categories['CAT-ATK']->id, 'sku' => 'ATK-KRT-002', 'barcode' => '8991001005', 'name' => 'Kertas HVS PaperOne F4/Folio 80gr Box', 'specification' => 'PaperOne Folio 80 GSM per box isi 5 rim', 'uom' => 'BOX', 'min_stock' => 30, 'safety_stock' => 60, 'reorder_point' => 90, 'lead_time_days' => 3, 'estimated_unit_price' => 295000],
            ['category_id' => $categories['CAT-ATK']->id, 'sku' => 'ATK-PUL-001', 'barcode' => '8991001006', 'name' => 'Ballpoint Standard AE7 0.5mm Hitam (Pack 12)', 'specification' => 'Ballpoint tinta hitam pekat anti macet isi 12 pcs', 'uom' => 'PACK', 'min_stock' => 50, 'safety_stock' => 100, 'reorder_point' => 150, 'lead_time_days' => 2, 'estimated_unit_price' => 28000],
            ['category_id' => $categories['CAT-CTK']->id, 'sku' => 'CTK-SLP-001', 'barcode' => '8991001007', 'name' => 'Slip Setoran Tunai NCR 3 Rangkap (Bank Jatim)', 'specification' => 'Formulir setoran teller 3 ply NCR cetak 2 warna blok isi 100 set', 'uom' => 'BOX', 'min_stock' => 20, 'safety_stock' => 40, 'reorder_point' => 70, 'lead_time_days' => 10, 'estimated_unit_price' => 185000],
            ['category_id' => $categories['CAT-CTK']->id, 'sku' => 'CTK-SLP-002', 'barcode' => '8991001008', 'name' => 'Slip Penarikan Tunai NCR 2 Rangkap', 'specification' => 'Formulir tarikan kas 2 ply NCR isi 100 set per buku/box', 'uom' => 'BOX', 'min_stock' => 20, 'safety_stock' => 40, 'reorder_point' => 65, 'lead_time_days' => 10, 'estimated_unit_price' => 165000],
            ['category_id' => $categories['CAT-CTK']->id, 'sku' => 'CTK-TAB-001', 'barcode' => '8991001009', 'name' => 'Buku Tabungan Simpeda Bank Jatim', 'specification' => 'Buku tabungan passbook magnetic stripe 16 halaman', 'uom' => 'PACK', 'min_stock' => 100, 'safety_stock' => 200, 'reorder_point' => 350, 'lead_time_days' => 14, 'estimated_unit_price' => 450000],
            ['category_id' => $categories['CAT-CTK']->id, 'sku' => 'CTK-BG-001', 'barcode' => '8991001010', 'name' => 'Bilyet Giro Standar Bank Jatim (Buku 25 Lembar)', 'specification' => 'Warkat kliring security paper watermark BI', 'uom' => 'PACK', 'min_stock' => 50, 'safety_stock' => 100, 'reorder_point' => 180, 'lead_time_days' => 21, 'estimated_unit_price' => 375000],
            ['category_id' => $categories['CAT-KHZ']->id, 'sku' => 'KHZ-SGL-001', 'barcode' => '8991001011', 'name' => 'Segel Plastik Security Seal Nomor Seri Kas (Pack 100)', 'specification' => 'Segel plastik kancing numbered security seal merah', 'uom' => 'PACK', 'min_stock' => 15, 'safety_stock' => 30, 'reorder_point' => 60, 'lead_time_days' => 7, 'estimated_unit_price' => 140000],
            ['category_id' => $categories['CAT-KHZ']->id, 'sku' => 'KHZ-KTG-001', 'barcode' => '8991001012', 'name' => 'Kantong Uang Kanvas Khazanah Tebal 40x60cm', 'specification' => 'Kantong kanvas tebal dengan lubang gembok & segel', 'uom' => 'PCS', 'min_stock' => 20, 'safety_stock' => 40, 'reorder_point' => 70, 'lead_time_days' => 10, 'estimated_unit_price' => 85000],
            ['category_id' => $categories['CAT-PRM']->id, 'sku' => 'PRM-BSR-001', 'barcode' => '8991001013', 'name' => 'Brosur Produk Kredit Usaha Rakyat (KUR) 2026', 'specification' => 'Art Paper 150 GSM Full Color Lipat 3 isi 500 lbr/pack', 'uom' => 'PACK', 'min_stock' => 10, 'safety_stock' => 25, 'reorder_point' => 45, 'lead_time_days' => 5, 'estimated_unit_price' => 225000],
            ['category_id' => $categories['CAT-PRM']->id, 'sku' => 'PRM-TMB-001', 'barcode' => '8991001014', 'name' => 'Tumbler Stainless Steel Logo Grafir Bank Jatim', 'specification' => 'Vacuum Insulated 500ml Stainless 304 Food Grade', 'uom' => 'PCS', 'min_stock' => 20, 'safety_stock' => 50, 'reorder_point' => 90, 'lead_time_days' => 14, 'estimated_unit_price' => 115000],
            ['category_id' => $categories['CAT-SRG']->id, 'sku' => 'SRG-LNY-001', 'barcode' => '8991001015', 'name' => 'Lanyard & Card Holder Kulit Bank Jatim', 'specification' => 'Lanyard satin printing + holder kulit sintetis logo emboss', 'uom' => 'PCS', 'min_stock' => 30, 'safety_stock' => 60, 'reorder_point' => 100, 'lead_time_days' => 7, 'estimated_unit_price' => 45000],
            ['category_id' => $categories['CAT-FLM']->id, 'sku' => 'FLM-CIF-001', 'barcode' => '8991001016', 'name' => 'Formulir Pembukaan Rekening Perorangan (CIF)', 'specification' => 'Formulir CIF 4 Halaman Art Paper 100gr per pack 100 set', 'uom' => 'PACK', 'min_stock' => 25, 'safety_stock' => 50, 'reorder_point' => 85, 'lead_time_days' => 5, 'estimated_unit_price' => 175000],
            ['category_id' => $categories['CAT-TLR']->id, 'sku' => 'TLR-STP-001', 'barcode' => '8991001017', 'name' => 'Stempel Validasi Otomatis Teller Dater Trodat', 'specification' => 'Trodat Printy 4810 dater otomatis logo Bank Jatim', 'uom' => 'UNIT', 'min_stock' => 5, 'safety_stock' => 10, 'reorder_point' => 18, 'lead_time_days' => 4, 'estimated_unit_price' => 165000],
            ['category_id' => $categories['CAT-TLR']->id, 'sku' => 'TLR-SPN-001', 'barcode' => '8991001018', 'name' => 'Spons Pembersih & Penghitung Uang Kasir', 'specification' => 'Bak bulat spons basah penghitung lembaran uang', 'uom' => 'PCS', 'min_stock' => 15, 'safety_stock' => 30, 'reorder_point' => 50, 'lead_time_days' => 3, 'estimated_unit_price' => 15000],
            ['category_id' => $categories['CAT-SVN']->id, 'sku' => 'SVN-PWR-001', 'barcode' => '8991001019', 'name' => 'Powerbank Wireless Fast Charge 10000mAh Prioritas', 'specification' => 'Powerbank MagSafe Fast Charge custom box gift set', 'uom' => 'UNIT', 'min_stock' => 10, 'safety_stock' => 20, 'reorder_point' => 35, 'lead_time_days' => 10, 'estimated_unit_price' => 340000],
            ['category_id' => $categories['CAT-NET']->id, 'sku' => 'NET-CBL-001', 'barcode' => '8991001020', 'name' => 'Kabel Patch Cord UTP Belden Cat6 3 Meter', 'specification' => 'Original Belden Cat6 molded patch cord factory terminated', 'uom' => 'PCS', 'min_stock' => 20, 'safety_stock' => 40, 'reorder_point' => 60, 'lead_time_days' => 3, 'estimated_unit_price' => 42000],
        ];

        $items = [];
        foreach ($itemsData as $id) {
            $items[$id['sku']] = Item::create(array_merge($id, ['is_active' => true]));
        }

        // =========================================================================
        // 4b. MASTER KONVERSI SATUAN BARANG (10 Aturan Konversi)
        // =========================================================================
        $conversionsData = [
            ['item_id' => $items['ATK-KRT-001']->id, 'from_uom' => 'BOX', 'conversion_factor' => 5, 'to_uom' => 'RIM', 'description' => '1 Box Kertas HVS A4 berisi 5 Rim', 'is_active' => true],
            ['item_id' => $items['ATK-KRT-002']->id, 'from_uom' => 'BOX', 'conversion_factor' => 5, 'to_uom' => 'RIM', 'description' => '1 Box Kertas HVS F4 berisi 5 Rim', 'is_active' => true],
            ['item_id' => $items['ATK-PUL-001']->id, 'from_uom' => 'PACK', 'conversion_factor' => 12, 'to_uom' => 'PCS', 'description' => '1 Pack Ballpoint Standard AE7 berisi 12 Pcs', 'is_active' => true],
            ['item_id' => $items['IT-EDC-001']->id, 'from_uom' => 'BOX', 'conversion_factor' => 100, 'to_uom' => 'ROLL', 'description' => '1 Box Kertas Thermal EDC berisi 100 Roll', 'is_active' => true],
            ['item_id' => $items['CTK-TAB-001']->id, 'from_uom' => 'PACK', 'conversion_factor' => 50, 'to_uom' => 'BUKU', 'description' => '1 Pack Buku Tabungan Simpeda berisi 50 Buku', 'is_active' => true],
            ['item_id' => $items['CTK-BG-001']->id, 'from_uom' => 'PACK', 'conversion_factor' => 10, 'to_uom' => 'BUKU', 'description' => '1 Pack Warkat Bilyet Giro berisi 10 Buku', 'is_active' => true],
            ['item_id' => $items['KHZ-SGL-001']->id, 'from_uom' => 'PACK', 'conversion_factor' => 100, 'to_uom' => 'PCS', 'description' => '1 Pack Segel Plastik berisi 100 Pcs', 'is_active' => true],
            ['item_id' => null, 'from_uom' => 'DUS', 'conversion_factor' => 10, 'to_uom' => 'BOX', 'description' => '1 Dus Karton Master standar setara 10 Box', 'is_active' => true],
            ['item_id' => null, 'from_uom' => 'RIM', 'conversion_factor' => 500, 'to_uom' => 'LEMBAR', 'description' => '1 Rim kertas cetak standar berisi 500 Lembar', 'is_active' => true],
            ['item_id' => null, 'from_uom' => 'LUSIN', 'conversion_factor' => 12, 'to_uom' => 'PCS', 'description' => '1 Lusin standar kelipatan 12 Pieces', 'is_active' => true],
        ];

        foreach ($conversionsData as $cd) {
            ItemConversion::create($cd);
        }

        // =========================================================================
        // 5. MASTER REKANAN VENDOR (10 Vendor Pengadaan)
        // =========================================================================
        $vendorData = [
            ['code' => 'VND-DTS', 'name' => 'PT Datascrip Surabaya', 'address' => 'Jl. Mayjend Sungkono No. 89, Surabaya', 'phone' => '031-5678901', 'email' => 'sales.sby@datascrip.co.id', 'payment_terms' => 'NET 30', 'sla_days' => 5, 'rating' => 4.9],
            ['code' => 'VND-AST', 'name' => 'PT Astra Graphia Tbk', 'address' => 'Jl. Dr. Soetomo No. 102, Surabaya', 'phone' => '031-5681234', 'email' => 'corporate.jatim@astragraphia.co.id', 'payment_terms' => 'NET 30', 'sla_days' => 7, 'rating' => 4.8],
            ['code' => 'VND-BLP', 'name' => 'PT Balai Pustaka (Persero)', 'address' => 'Jl. Pulogadung Kav. 15, Jakarta', 'phone' => '021-4600100', 'email' => 'percetakan.security@balaipustaka.co.id', 'payment_terms' => 'NET 45', 'sla_days' => 14, 'rating' => 4.9],
            ['code' => 'VND-PPR', 'name' => 'PT Paperina Dwijaya', 'address' => 'Kawasan Industri Rungkut Industri IV No. 8', 'phone' => '031-8432100', 'email' => 'order@paperina.com', 'payment_terms' => 'NET 14', 'sla_days' => 3, 'rating' => 4.7],
            ['code' => 'VND-PRM', 'name' => 'PT Prima Grafika Nusantara', 'address' => 'Jl. Raya Tenggilis No. 45, Surabaya', 'phone' => '031-8411223', 'email' => 'info@primagrafika.co.id', 'payment_terms' => 'NET 30', 'sla_days' => 7, 'rating' => 4.6],
            ['code' => 'VND-SEC', 'name' => 'PT Security Seal Mandiri', 'address' => 'Kawasan Industri Gresik Kav. F-10', 'phone' => '031-3987654', 'email' => 'sales@securityseal.co.id', 'payment_terms' => 'NET 30', 'sla_days' => 5, 'rating' => 4.8],
            ['code' => 'VND-MTR', 'name' => 'PT Multi Sarana IT Solusindo', 'address' => 'Grand City Surabaya Lt. 3 No. 12', 'phone' => '031-5456789', 'email' => 'b2b@multisarana.com', 'payment_terms' => 'NET 14', 'sla_days' => 4, 'rating' => 4.5],
            ['code' => 'VND-SBR', 'name' => 'CV Sumber Makmur ATK', 'address' => 'Jl. Kertajaya No. 88, Surabaya', 'phone' => '031-5034567', 'email' => 'sumbermakmur.atk@gmail.com', 'payment_terms' => 'NET 14', 'sla_days' => 2, 'rating' => 4.7],
            ['code' => 'VND-GFT', 'name' => 'PT Giftindo Cipta Promosi', 'address' => 'Jl. Darmo Permai Selatan No. 19', 'phone' => '031-7345678', 'email' => 'corporate@giftindo.co.id', 'payment_terms' => 'NET 30', 'sla_days' => 10, 'rating' => 4.8],
            ['code' => 'VND-NET', 'name' => 'PT Jaringan Solusi Nusantara', 'address' => 'Ruko Landmark Kayoon Blok B-5', 'phone' => '031-5312345', 'email' => 'sales@jasolnu.com', 'payment_terms' => 'NET 14', 'sla_days' => 3, 'rating' => 4.6],
        ];

        $vendors = [];
        foreach ($vendorData as $vd) {
            $vendors[$vd['code']] = Vendor::create(array_merge($vd, ['is_active' => true]));
        }

        // =========================================================================
        // 6. MASTER EKSPEDISI & KURIR (10 Jasa Logistik)
        // =========================================================================
        $courierData = [
            ['code' => 'JNE', 'name' => 'PT Tiki Jalur Nugraha Ekakurir (JNE)', 'phone' => '021-29278888', 'service_types' => ['REGULER', 'YES', 'TRUCKING'], 'sla_days' => 2],
            ['code' => 'POS', 'name' => 'PT Pos Indonesia (Persero)', 'phone' => '1500161', 'service_types' => ['POS_KILAT_KHUSUS', 'POS_JUMBO_CARGO'], 'sla_days' => 3],
            ['code' => 'JNT', 'name' => 'PT Global Jet Express (J&T Cargo)', 'phone' => '021-80661888', 'service_types' => ['EZ', 'CARGO_STANDAR'], 'sla_days' => 2],
            ['code' => 'SCP', 'name' => 'PT SiCepat Ekspres Indonesia', 'phone' => '021-50200050', 'service_types' => ['SIUNTUNG', 'GOKIL_CARGO'], 'sla_days' => 2],
            ['code' => 'TIK', 'name' => 'PT Citra Van Titipan Kilat (TIKI)', 'phone' => '1500125', 'service_types' => ['REG', 'ONS', 'TDS'], 'sla_days' => 2],
            ['code' => 'WHN', 'name' => 'PT Wahana Prestasi Logistik', 'phone' => '021-7341688', 'service_types' => ['LAYANAN_EKONOMIS', 'CARGO'], 'sla_days' => 3],
            ['code' => 'LION', 'name' => 'PT Lion Parcel Nusantara', 'phone' => '021-80820072', 'service_types' => ['REGPACK', 'ONEPACK', 'JAGOPACK'], 'sla_days' => 2],
            ['code' => 'ANTR', 'name' => 'PT Tri Adi Bersama (Anteraja)', 'phone' => '021-50603333', 'service_types' => ['REGULAR', 'NEXT_DAY', 'CARGO'], 'sla_days' => 2],
            ['code' => 'INDH', 'name' => 'PT Indah Logistik Cargo', 'phone' => '021-80665555', 'service_types' => ['DARAT_CARGO', 'UDARA_EXPRESS'], 'sla_days' => 3],
            ['code' => 'ARM-BJ', 'name' => 'Armada Ekspedisi Internal Bank Jatim', 'phone' => '031-5310090', 'service_types' => ['DEDICATED_VAN', 'BOX_TRUCK'], 'sla_days' => 1],
        ];

        $couriers = [];
        foreach ($courierData as $crd) {
            $couriers[$crd['code']] = Courier::create(array_merge($crd, ['is_active' => true]));
        }

        // =========================================================================
        // 7. MASTER PENGGUNA & HAK AKSES (15 User untuk Seluruh Role)
        // =========================================================================
        $usersData = [
            ['name' => 'Bambang Soediro', 'email' => 'admin@bankjatim.co.id', 'role' => 'SUPER_ADMIN', 'nip' => 'BJ-00001', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => null, 'approval_limit' => 5000000000],
            ['name' => 'Rachmat Hidayat', 'email' => 'useradmin@bankjatim.co.id', 'role' => 'USER_ADMIN', 'nip' => 'BJ-00002', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => null, 'approval_limit' => 0],
            ['name' => 'Iwan Setiawan', 'email' => 'proc.officer@bankjatim.co.id', 'role' => 'PROCUREMENT_OFFICER', 'nip' => 'BJ-00003', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => null, 'approval_limit' => 0],
            ['name' => 'Ir. Hendro Siswanto', 'email' => 'proc.approver@bankjatim.co.id', 'role' => 'PROCUREMENT_APPROVER', 'nip' => 'BJ-00004', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => null, 'approval_limit' => 1500000000],
            ['name' => 'Tri Wahyuni', 'email' => 'inventory@bankjatim.co.id', 'role' => 'INVENTORY_OFFICER', 'nip' => 'BJ-00005', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => $warehouses['GD-RKT']->id, 'approval_limit' => 0],
            ['name' => 'Joko Purwanto', 'email' => 'warehouse@bankjatim.co.id', 'role' => 'WAREHOUSE_OFFICER', 'nip' => 'BJ-00006', 'organization_id' => $organizations['GD-RKT']->id, 'warehouse_id' => $warehouses['GD-RKT']->id, 'approval_limit' => 0],
            ['name' => 'Agus Priyono', 'email' => 'distribusi@bankjatim.co.id', 'role' => 'DISTRIBUTION_OFFICER', 'nip' => 'BJ-00007', 'organization_id' => $organizations['GD-RKT']->id, 'warehouse_id' => $warehouses['GD-RKT']->id, 'approval_limit' => 0],
            ['name' => 'Budi Santoso', 'email' => 'requester.sby@bankjatim.co.id', 'role' => 'REQUESTER_CABANG', 'nip' => 'BJ-10001', 'organization_id' => $organizations['KC-SBY']->id, 'warehouse_id' => $warehouses['KC-SBY']->id, 'approval_limit' => 0],
            ['name' => 'Dewi Sartika', 'email' => 'approver.sby@bankjatim.co.id', 'role' => 'ORDER_APPROVER', 'nip' => 'BJ-10002', 'organization_id' => $organizations['KC-SBY']->id, 'warehouse_id' => $warehouses['KC-SBY']->id, 'approval_limit' => 250000000],
            ['name' => 'Ahmad Fauzi', 'email' => 'requester.mlg@bankjatim.co.id', 'role' => 'REQUESTER_CABANG', 'nip' => 'BJ-20001', 'organization_id' => $organizations['KC-MLG']->id, 'warehouse_id' => $warehouses['KC-MLG']->id, 'approval_limit' => 0],
            ['name' => 'Siti Nurhaliza', 'email' => 'receiving.gbg@bankjatim.co.id', 'role' => 'RECEIVING_OFFICER', 'nip' => 'BJ-30001', 'organization_id' => $organizations['KCP-GBG']->id, 'warehouse_id' => $warehouses['KCP-GBG']->id, 'approval_limit' => 0],
            ['name' => 'Eko Prasetyo', 'email' => 'finance.officer@bankjatim.co.id', 'role' => 'FINANCE_OFFICER', 'nip' => 'BJ-00008', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => null, 'approval_limit' => 0],
            ['name' => 'Dra. Sri Mulyani', 'email' => 'finance.approver@bankjatim.co.id', 'role' => 'FINANCE_APPROVER', 'nip' => 'BJ-00009', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => null, 'approval_limit' => 2000000000],
            ['name' => 'Kurniawan Pratama', 'email' => 'auditor@bankjatim.co.id', 'role' => 'AUDITOR', 'nip' => 'BJ-00010', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => null, 'approval_limit' => 0],
            ['name' => 'Direksi Operasional', 'email' => 'management@bankjatim.co.id', 'role' => 'MANAGEMENT', 'nip' => 'BJ-00099', 'organization_id' => $organizations['KP-001']->id, 'warehouse_id' => null, 'approval_limit' => 10000000000],
        ];

        $users = [];
        foreach ($usersData as $ud) {
            $users[$ud['email']] = User::create(array_merge($ud, [
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]));
        }

        // =========================================================================
        // 8. MASTER PAGU ANGGARAN UNIT (12 Anggaran Unit TA 2026)
        // =========================================================================
        $budgetAllocations = [
            'KP-001' => 1500000000,
            'GD-RKT' => 2000000000,
            'KC-SBY' => 850000000,
            'KC-MLG' => 650000000,
            'KC-KDR' => 500000000,
            'KC-JBR' => 450000000,
            'KC-BWX' => 400000000,
            'KC-MDN' => 380000000,
            'KC-BJN' => 350000000,
            'KCP-GBG' => 180000000,
            'KCP-DNY' => 150000000,
            'KCP-BAT' => 140000000,
        ];

        foreach ($budgetAllocations as $code => $pagu) {
            $committed = round($pagu * 0.15);
            $realized = round($pagu * 0.25);
            Budget::create([
                'organization_id' => $organizations[$code]->id,
                'year' => 2026,
                'cost_center_code' => $organizations[$code]->cost_center_code,
                'allocated_amount' => $pagu,
                'committed_amount' => $committed,
                'realized_amount' => $realized,
            ]);
        }

        // =========================================================================
        // 9. INITIAL STOCK BALANCES & STOCK LEDGERS (Seluruh Gudang & Barang)
        // =========================================================================
        $whCentral = $warehouses['GD-RKT'];
        foreach ($items as $sku => $it) {
            // Gudang Pusat SIER
            $onHandCentral = $sku === 'IT-TNR-001' ? 85 : ($sku === 'ATK-KRT-001' ? 240 : 150);
            $reservedCentral = $sku === 'IT-TNR-001' ? 15 : 20;
            $damagedCentral = $sku === 'ATK-KRT-001' ? 2 : 0;

            StockBalance::create([
                'warehouse_id' => $whCentral->id,
                'item_id' => $it->id,
                'on_hand' => $onHandCentral,
                'reserved' => $reservedCentral,
                'hold' => 0,
                'damaged' => $damagedCentral,
            ]);

            StockLedger::create([
                'warehouse_id' => $whCentral->id,
                'item_id' => $it->id,
                'transaction_type' => 'STOCK_INITIAL',
                'reference_number' => 'INIT-2026-'.$it->sku,
                'qty_in' => $onHandCentral,
                'qty_out' => 0,
                'unit_cost' => $it->estimated_unit_price,
                'total_value' => $it->estimated_unit_price * $onHandCentral,
                'balance_after' => $onHandCentral,
                'notes' => 'Saldo awal migrasi persediaan Bank Jatim TA 2026',
                'created_by_user_id' => $users['admin@bankjatim.co.id']->id,
                'created_at' => $now->copy()->subMonths(2),
            ]);

            // Gudang Cabang (KC Surabaya & KC Malang)
            foreach (['KC-SBY', 'KC-MLG', 'KC-KDR'] as $branchCode) {
                $whBranch = $warehouses[$branchCode];
                $onHandBranch = $branchCode === 'KC-MLG' && $sku === 'IT-TNR-001' ? 45 : 25; // KC Malang has surplus toner

                StockBalance::create([
                    'warehouse_id' => $whBranch->id,
                    'item_id' => $it->id,
                    'on_hand' => $onHandBranch,
                    'reserved' => 0,
                    'hold' => 0,
                    'damaged' => 0,
                ]);

                StockLedger::create([
                    'warehouse_id' => $whBranch->id,
                    'item_id' => $it->id,
                    'transaction_type' => 'STOCK_INITIAL',
                    'reference_number' => 'INIT-'.$branchCode.'-'.$it->sku,
                    'qty_in' => $onHandBranch,
                    'qty_out' => 0,
                    'unit_cost' => $it->estimated_unit_price,
                    'total_value' => $it->estimated_unit_price * $onHandBranch,
                    'balance_after' => $onHandBranch,
                    'notes' => "Saldo awal cabang {$branchCode}",
                    'created_by_user_id' => $users['admin@bankjatim.co.id']->id,
                    'created_at' => $now->copy()->subMonths(2),
                ]);
            }
        }

        // =========================================================================
        // 10. TRANSAKSI PURCHASE REQUEST (10 PR)
        // =========================================================================
        $prList = [
            ['org' => 'KP-001', 'method' => 'DIRECT', 'purpose' => 'Pengadaan Rutin Toner & Periferal IT Q1', 'status' => 'APPROVED', 'days_ago' => 45, 'items' => [['sku' => 'IT-TNR-001', 'qty' => 50], ['sku' => 'IT-RBN-001', 'qty' => 100]]],
            ['org' => 'KP-001', 'method' => 'DIRECT', 'purpose' => 'Kebutuhan Kertas HVS & Map Divisi Umum', 'status' => 'APPROVED', 'days_ago' => 40, 'items' => [['sku' => 'ATK-KRT-001', 'qty' => 80], ['sku' => 'ATK-KRT-002', 'qty' => 60]]],
            ['org' => 'KC-SBY', 'method' => 'DIRECT', 'purpose' => 'Stok Slip Setoran & Warkat Kas Surabaya', 'status' => 'APPROVED', 'days_ago' => 35, 'items' => [['sku' => 'CTK-SLP-001', 'qty' => 40], ['sku' => 'CTK-TAB-001', 'qty' => 150]]],
            ['org' => 'KC-MLG', 'method' => 'DIRECT', 'purpose' => 'Kebutuhan Form CIF & Stempel Dater Malang', 'status' => 'APPROVED', 'days_ago' => 30, 'items' => [['sku' => 'FLM-CIF-001', 'qty' => 30], ['sku' => 'TLR-STP-001', 'qty' => 10]]],
            ['org' => 'KC-KDR', 'method' => 'DIRECT', 'purpose' => 'Perlengkapan Khazanah & Segel Kas Kediri', 'status' => 'APPROVED', 'days_ago' => 25, 'items' => [['sku' => 'KHZ-SGL-001', 'qty' => 20], ['sku' => 'KHZ-KTG-001', 'qty' => 30]]],
            ['org' => 'KC-JBR', 'method' => 'DIRECT', 'purpose' => 'Brosur KUR & Souvenir Prioritas Jember', 'status' => 'APPROVED', 'days_ago' => 20, 'items' => [['sku' => 'PRM-BSR-001', 'qty' => 15], ['sku' => 'SVN-PWR-001', 'qty' => 20]]],
            ['org' => 'KC-BWX', 'method' => 'DIRECT', 'purpose' => 'Kertas Thermal EDC & Lanyard Banyuwangi', 'status' => 'APPROVED', 'days_ago' => 15, 'items' => [['sku' => 'IT-EDC-001', 'qty' => 25], ['sku' => 'SRG-LNY-001', 'qty' => 40]]],
            ['org' => 'KC-MDN', 'method' => 'DIRECT', 'purpose' => 'Kabel Patch Cord & Alat Teller Madiun', 'status' => 'WAITING_APPROVAL', 'days_ago' => 5, 'items' => [['sku' => 'NET-CBL-001', 'qty' => 30], ['sku' => 'TLR-SPN-001', 'qty' => 20]]],
            ['org' => 'KC-BJN', 'method' => 'DIRECT', 'purpose' => 'Bilyet Giro & Ballpoint Bojonegoro', 'status' => 'WAITING_APPROVAL', 'days_ago' => 3, 'items' => [['sku' => 'CTK-BG-001', 'qty' => 40], ['sku' => 'ATK-PUL-001', 'qty' => 50]]],
            ['org' => 'KCP-GBG', 'method' => 'DIRECT', 'purpose' => 'Pengadaan Mendesak Kertas Thermal EDC Gubeng', 'status' => 'SUBMITTED', 'days_ago' => 1, 'items' => [['sku' => 'IT-EDC-001', 'qty' => 15]]],
        ];

        $createdPRs = [];
        $prCounter = 1;
        foreach ($prList as $p) {
            $totalEst = 0;
            foreach ($p['items'] as $itRow) {
                $totalEst += $items[$itRow['sku']]->estimated_unit_price * $itRow['qty'];
            }

            $pr = PurchaseRequest::create([
                'pr_number' => 'PR/'.date('Y/m').'/'.sprintf('%04d', $prCounter++),
                'organization_id' => $organizations[$p['org']]->id,
                'procurement_method' => $p['method'],
                'purpose' => $p['purpose'],
                'estimated_total_cost' => $totalEst,
                'budget_status' => 'VALIDATED',
                'status' => $p['status'],
                'created_by_user_id' => $users['proc.officer@bankjatim.co.id']->id,
                'approved_by_user_id' => $p['status'] === 'APPROVED' ? $users['proc.approver@bankjatim.co.id']->id : null,
                'approved_at' => $p['status'] === 'APPROVED' ? $now->copy()->subDays($p['days_ago'] - 1) : null,
                'submitted_at' => $now->copy()->subDays($p['days_ago']),
                'created_at' => $now->copy()->subDays($p['days_ago']),
            ]);

            foreach ($p['items'] as $itRow) {
                $targetItem = $items[$itRow['sku']];
                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'item_id' => $targetItem->id,
                    'qty_requested' => $itRow['qty'],
                    'qty_approved' => $p['status'] === 'APPROVED' ? $itRow['qty'] : 0,
                    'qty_ordered' => $p['status'] === 'APPROVED' ? $itRow['qty'] : 0,
                    'estimated_unit_price' => $targetItem->estimated_unit_price,
                    'estimated_subtotal' => $targetItem->estimated_unit_price * $itRow['qty'],
                    'notes' => 'Kebutuhan unit '.$p['org'],
                ]);
            }

            $createdPRs[] = $pr;
        }

        // =========================================================================
        // 11. TRANSAKSI PURCHASE ORDER (10 PO Konsolidasi & Standalone)
        // =========================================================================
        $poList = [
            ['vendor' => 'VND-DTS', 'pr_idx' => 0, 'status' => 'COMPLETED', 'days_ago' => 38, 'notes' => 'PO Pengadaan Toner & Ribbon'],
            ['vendor' => 'VND-PPR', 'pr_idx' => 1, 'status' => 'COMPLETED', 'days_ago' => 34, 'notes' => 'PO Kertas HVS Box Konsolidasi'],
            ['vendor' => 'VND-BLP', 'pr_idx' => 2, 'status' => 'COMPLETED', 'days_ago' => 28, 'notes' => 'PO Slip Setoran & Buku Tabungan'],
            ['vendor' => 'VND-PRM', 'pr_idx' => 3, 'status' => 'COMPLETED', 'days_ago' => 24, 'notes' => 'PO Cetak Form CIF & Stempel'],
            ['vendor' => 'VND-SEC', 'pr_idx' => 4, 'status' => 'COMPLETED', 'days_ago' => 20, 'notes' => 'PO Segel Kas & Kantong Kanvas'],
            ['vendor' => 'VND-GFT', 'pr_idx' => 5, 'status' => 'IN_DELIVERY', 'days_ago' => 14, 'notes' => 'PO Brosur KUR & Powerbank Hadiah'],
            ['vendor' => 'VND-DTS', 'pr_idx' => 6, 'status' => 'IN_DELIVERY', 'days_ago' => 10, 'notes' => 'PO Thermal EDC & Lanyard'],
            ['vendor' => 'VND-AST', 'pr_idx' => 0, 'status' => 'VENDOR_PROCESS', 'days_ago' => 6, 'notes' => 'PO Tambahan Toner Cadangan'],
            ['vendor' => 'VND-SBR', 'pr_idx' => 1, 'status' => 'ISSUED', 'days_ago' => 3, 'notes' => 'PO ATK Ballpoint & Folio'],
            ['vendor' => 'VND-NET', 'pr_idx' => 4, 'status' => 'ISSUED', 'days_ago' => 1, 'notes' => 'PO Kabel Cat6 & Konektor'],
        ];

        $createdPOs = [];
        $poCounter = 1;
        foreach ($poList as $poEntry) {
            $sourcePR = $createdPRs[$poEntry['pr_idx']];
            $subtotal = $sourcePR->estimated_total_cost;
            $tax = round($subtotal * 0.11);
            $total = $subtotal + $tax;

            $po = PurchaseOrder::create([
                'po_number' => 'PO/'.date('Y/m').'/'.sprintf('%04d', $poCounter++),
                'vendor_id' => $vendors[$poEntry['vendor']]->id,
                'warehouse_id' => $whCentral->id,
                'created_by_user_id' => $users['proc.officer@bankjatim.co.id']->id,
                'approved_by_user_id' => $users['proc.approver@bankjatim.co.id']->id,
                'order_date' => $now->copy()->subDays($poEntry['days_ago'])->toDateString(),
                'expected_delivery_date' => $now->copy()->addDays(10)->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'status' => $poEntry['status'],
                'notes' => $poEntry['notes'],
                'created_at' => $now->copy()->subDays($poEntry['days_ago']),
            ]);

            foreach ($sourcePR->items as $pri) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'purchase_request_item_id' => $pri->id,
                    'item_id' => $pri->item_id,
                    'qty_ordered' => $pri->qty_requested,
                    'qty_received' => $poEntry['status'] === 'COMPLETED' ? $pri->qty_requested : 0,
                    'unit_price' => $pri->estimated_unit_price,
                    'subtotal' => $pri->estimated_subtotal,
                ]);
            }

            $createdPOs[] = $po;
        }

        // =========================================================================
        // 12. TRANSAKSI GOODS RECEIPT VENDOR / GRN (10 GRN)
        // =========================================================================
        for ($i = 0; $i < 10; $i++) {
            $poTarget = $createdPOs[$i];
            $grn = GoodsReceipt::create([
                'grn_number' => 'GRN/'.date('Y/m').'/'.sprintf('%04d', $i + 1),
                'purchase_order_id' => $poTarget->id,
                'warehouse_id' => $whCentral->id,
                'received_by_user_id' => $users['warehouse@bankjatim.co.id']->id,
                'vendor_delivery_note_number' => 'SJ/VND/'.date('Ymd').'/'.sprintf('%03d', $i + 1),
                'receipt_date' => $now->copy()->subDays(30 - ($i * 2))->toDateString(),
                'status' => $i < 8 ? 'RECEIVED' : 'PARTIAL',
                'notes' => 'Penerimaan barang fisik dari vendor lengkap dan kondisi baik',
                'created_at' => $now->copy()->subDays(30 - ($i * 2)),
            ]);

            foreach ($poTarget->items as $poi) {
                GoodsReceiptItem::create([
                    'goods_receipt_id' => $grn->id,
                    'purchase_order_item_id' => $poi->id,
                    'item_id' => $poi->item_id,
                    'qty_received' => $poi->qty_ordered,
                    'qty_accepted' => $poi->qty_ordered,
                    'qty_rejected' => 0,
                    'condition_notes' => 'Kondisi kemasan utuh, lolos QC',
                ]);
            }
        }

        // =========================================================================
        // 13. TRANSAKSI ORDER PERMINTAAN CABANG (15 Orders)
        // =========================================================================
        $orderList = [
            ['org' => 'KC-SBY', 'wh' => 'KC-SBY', 'prio' => 'NORMAL', 'status' => 'COMPLETED', 'days' => 25, 'items' => [['sku' => 'IT-TNR-001', 'qty' => 5], ['sku' => 'ATK-KRT-001', 'qty' => 20]]],
            ['org' => 'KC-MLG', 'wh' => 'KC-MLG', 'prio' => 'NORMAL', 'status' => 'COMPLETED', 'days' => 22, 'items' => [['sku' => 'CTK-SLP-001', 'qty' => 10], ['sku' => 'IT-EDC-001', 'qty' => 8]]],
            ['org' => 'KC-KDR', 'wh' => 'KC-KDR', 'prio' => 'URGENT', 'status' => 'COMPLETED', 'days' => 18, 'items' => [['sku' => 'CTK-TAB-001', 'qty' => 50], ['sku' => 'KHZ-SGL-001', 'qty' => 10]]],
            ['org' => 'KC-JBR', 'wh' => 'KC-JBR', 'prio' => 'NORMAL', 'status' => 'RECEIVED', 'days' => 15, 'items' => [['sku' => 'ATK-PUL-001', 'qty' => 25], ['sku' => 'PRM-BSR-001', 'qty' => 5]]],
            ['org' => 'KCP-GBG', 'wh' => 'KCP-GBG', 'prio' => 'NORMAL', 'status' => 'RECEIVED', 'days' => 12, 'items' => [['sku' => 'IT-EDC-001', 'qty' => 6], ['sku' => 'ATK-KRT-001', 'qty' => 10]]],
            ['org' => 'KC-BWX', 'wh' => 'KC-BWX', 'prio' => 'HIGH', 'status' => 'IN_TRANSIT', 'days' => 8, 'items' => [['sku' => 'IT-TNR-001', 'qty' => 4], ['sku' => 'CTK-SLP-002', 'qty' => 15]]],
            ['org' => 'KC-MDN', 'wh' => 'KC-MDN', 'prio' => 'NORMAL', 'status' => 'IN_TRANSIT', 'days' => 6, 'items' => [['sku' => 'SRG-LNY-001', 'qty' => 20], ['sku' => 'FLM-CIF-001', 'qty' => 10]]],
            ['org' => 'KC-BJN', 'wh' => 'KC-BJN', 'prio' => 'NORMAL', 'status' => 'READY_TO_SHIP', 'days' => 4, 'items' => [['sku' => 'TLR-STP-001', 'qty' => 4], ['sku' => 'TLR-SPN-001', 'qty' => 10]]],
            ['org' => 'KCP-DNY', 'wh' => 'KCP-DNY', 'prio' => 'NORMAL', 'status' => 'PACKING', 'days' => 3, 'items' => [['sku' => 'ATK-KRT-002', 'qty' => 8], ['sku' => 'IT-RBN-001', 'qty' => 10]]],
            ['org' => 'KCP-BAT', 'wh' => 'KCP-BAT', 'prio' => 'HIGH', 'status' => 'PICKING', 'days' => 2, 'items' => [['sku' => 'KHZ-KTG-001', 'qty' => 10], ['sku' => 'IT-EDC-001', 'qty' => 5]]],
            ['org' => 'KC-SBY', 'wh' => 'KC-SBY', 'prio' => 'NORMAL', 'status' => 'WAITING_APPROVAL', 'days' => 2, 'items' => [['sku' => 'SVN-PWR-001', 'qty' => 10], ['sku' => 'PRM-TMB-001', 'qty' => 15]]],
            ['org' => 'KC-MLG', 'wh' => 'KC-MLG', 'prio' => 'URGENT', 'status' => 'WAITING_APPROVAL', 'days' => 1, 'items' => [['sku' => 'IT-TNR-001', 'qty' => 8], ['sku' => 'NET-CBL-001', 'qty' => 15]]],
            ['org' => 'KC-KDR', 'wh' => 'KC-KDR', 'prio' => 'NORMAL', 'status' => 'WAITING_APPROVAL', 'days' => 1, 'items' => [['sku' => 'CTK-BG-001', 'qty' => 20], ['sku' => 'ATK-PUL-001', 'qty' => 30]]],
            ['org' => 'KC-JBR', 'wh' => 'KC-JBR', 'prio' => 'NORMAL', 'status' => 'SUBMITTED', 'days' => 0, 'items' => [['sku' => 'CTK-TAB-001', 'qty' => 40]]],
            ['org' => 'KC-BWX', 'wh' => 'KC-BWX', 'prio' => 'NORMAL', 'status' => 'REJECTED', 'days' => 10, 'items' => [['sku' => 'SVN-PWR-001', 'qty' => 50]]],
        ];

        $createdOrders = [];
        $ordCounter = 1;
        foreach ($orderList as $ol) {
            $totalVal = 0;
            foreach ($ol['items'] as $itRow) {
                $totalVal += $items[$itRow['sku']]->estimated_unit_price * $itRow['qty'];
            }

            $order = Order::create([
                'order_number' => 'ORD/'.date('Y/m').'/'.sprintf('%04d', $ordCounter++),
                'requesting_organization_id' => $organizations[$ol['org']]->id,
                'requesting_warehouse_id' => $warehouses[$ol['wh']]->id,
                'created_by_user_id' => $users['requester.sby@bankjatim.co.id']->id,
                'approved_by_user_id' => in_array($ol['status'], ['SUBMITTED', 'WAITING_APPROVAL']) ? null : $users['approver.sby@bankjatim.co.id']->id,
                'priority' => $ol['prio'],
                'required_date' => $now->copy()->addDays(5)->toDateString(),
                'total_items' => count($ol['items']),
                'total_estimated_value' => $totalVal,
                'status' => $ol['status'],
                'notes' => "Order pemenuhan kebutuhan {$ol['org']} ({$ol['prio']})",
                'submitted_at' => $now->copy()->subDays($ol['days']),
                'approved_at' => in_array($ol['status'], ['SUBMITTED', 'WAITING_APPROVAL']) ? null : $now->copy()->subDays($ol['days'] - 1),
                'completed_at' => in_array($ol['status'], ['COMPLETED', 'RECEIVED']) ? $now->copy()->subDays($ol['days'] - 2) : null,
                'created_at' => $now->copy()->subDays($ol['days']),
            ]);

            foreach ($ol['items'] as $itRow) {
                $targetItem = $items[$itRow['sku']];
                $allocated = in_array($ol['status'], ['SUBMITTED', 'WAITING_APPROVAL', 'REJECTED']) ? 0 : $itRow['qty'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $targetItem->id,
                    'qty_requested' => $itRow['qty'],
                    'qty_approved' => $allocated,
                    'qty_allocated' => $allocated,
                    'qty_picked' => in_array($ol['status'], ['PACKING', 'READY_TO_SHIP', 'IN_TRANSIT', 'RECEIVED', 'COMPLETED']) ? $itRow['qty'] : 0,
                    'qty_packed' => in_array($ol['status'], ['READY_TO_SHIP', 'IN_TRANSIT', 'RECEIVED', 'COMPLETED']) ? $itRow['qty'] : 0,
                    'qty_shipped' => in_array($ol['status'], ['IN_TRANSIT', 'RECEIVED', 'COMPLETED']) ? $itRow['qty'] : 0,
                    'qty_received' => in_array($ol['status'], ['RECEIVED', 'COMPLETED']) ? $itRow['qty'] : 0,
                    'unit_price_ref' => $targetItem->estimated_unit_price,
                    'subtotal_ref' => $targetItem->estimated_unit_price * $itRow['qty'],
                    'notes' => 'Alokasi stok regular',
                ]);
            }

            $createdOrders[] = $order;
        }

        // =========================================================================
        // 14. TRANSAKSI PICKING, PACKING & SHIPMENTS (10 Records Each)
        // =========================================================================
        $courierKeys = array_keys($couriers);
        for ($i = 0; $i < 10; $i++) {
            $ord = $createdOrders[$i];

            // Picking
            WarehousePicking::create([
                'picking_number' => 'PCK/'.date('Y/m').'/'.sprintf('%04d', $i + 1),
                'order_id' => $ord->id,
                'warehouse_id' => $whCentral->id,
                'picked_by_user_id' => $users['warehouse@bankjatim.co.id']->id,
                'status' => 'COMPLETED',
                'picked_at' => $ord->created_at->copy()->addHours(6),
                'created_at' => $ord->created_at->copy()->addHours(2),
            ]);

            // Packing
            $packing = WarehousePacking::create([
                'packing_number' => 'PCK-BOX/'.date('Y/m').'/'.sprintf('%04d', $i + 1),
                'order_id' => $ord->id,
                'warehouse_id' => $whCentral->id,
                'packed_by_user_id' => $users['warehouse@bankjatim.co.id']->id,
                'koli_count' => rand(1, 4),
                'total_weight_kg' => rand(8, 35) + 0.5,
                'dimensions_cm' => '45x35x30',
                'status' => 'PACKED',
                'packed_at' => $ord->created_at->copy()->addHours(12),
                'created_at' => $ord->created_at->copy()->addHours(8),
            ]);

            // Shipment Manifest
            $shipmentStatus = in_array($ord->status, ['RECEIVED', 'COMPLETED']) || $i >= 5 ? 'DELIVERED' : 'IN_TRANSIT';
            $crKey = $courierKeys[$i % count($courierKeys)];

            $shipment = Shipment::create([
                'manifest_number' => 'MNF/'.date('Y/m').'/'.sprintf('%04d', $i + 1),
                'order_id' => $ord->id,
                'origin_warehouse_id' => $whCentral->id,
                'destination_organization_id' => $ord->requesting_organization_id,
                'courier_id' => $couriers[$crKey]->id,
                'service_type' => 'REGULER',
                'tracking_number' => 'EXP-'.strtoupper($crKey).'-'.sprintf('%08d', rand(10000000, 99999999)),
                'dispatched_by_user_id' => $users['distribusi@bankjatim.co.id']->id,
                'koli_count' => $packing->koli_count,
                'total_weight_kg' => $packing->total_weight_kg,
                'shipping_cost' => rand(45, 150) * 1000,
                'eta_date' => $ord->created_at->copy()->addDays(3)->toDateString(),
                'status' => $shipmentStatus,
                'dispatched_at' => $ord->created_at->copy()->addHours(18),
                'delivered_at' => $ord->created_at->copy()->addDays(2),
                'created_at' => $ord->created_at->copy()->addHours(14),
            ]);

            // Receiving at Destination
            $receiving = Receiving::create([
                'receiving_number' => 'RCV/'.date('Y/m').'/'.sprintf('%04d', $i + 1),
                'shipment_id' => $shipment->id,
                'order_id' => $ord->id,
                'organization_id' => $ord->requesting_organization_id,
                'warehouse_id' => $ord->requesting_warehouse_id ?? $whCentral->id,
                'received_by_user_id' => $users['receiving.gbg@bankjatim.co.id']->id,
                'receipt_date' => $shipment->delivered_at ? $shipment->delivered_at->toDateString() : $now->toDateString(),
                'status' => 'RECEIVED_FULL',
                'pod_signature' => 'signatures/pod_'.($i + 1).'.png',
                'notes' => 'Barang diterima lengkap segel ekspedisi utuh',
                'created_at' => $shipment->delivered_at ?? $now,
            ]);

            // Inter-unit Settlement
            Settlement::create([
                'settlement_number' => 'STL/'.date('Y/m').'/'.sprintf('%04d', $i + 1),
                'order_id' => $ord->id,
                'debit_organization_id' => $ord->requesting_organization_id,
                'credit_organization_id' => $whCentral->organization_id,
                'debit_cost_center' => $ord->requestingOrganization->cost_center_code,
                'credit_cost_center' => $whCentral->organization->cost_center_code,
                'item_amount' => $ord->total_estimated_value,
                'shipping_amount' => $shipment->shipping_cost,
                'total_amount' => $ord->total_estimated_value + $shipment->shipping_cost,
                'status' => in_array($ord->status, ['COMPLETED', 'RECEIVED']) || $i < 6 ? 'POSTED' : 'WAITING_APPROVAL',
                'created_by_user_id' => $users['finance.officer@bankjatim.co.id']->id,
                'approved_by_user_id' => $users['finance.approver@bankjatim.co.id']->id,
                'posted_at' => $shipment->delivered_at ? $shipment->delivered_at->copy()->addDay() : null,
                'created_at' => $shipment->delivered_at ? $shipment->delivered_at->copy()->addHours(6) : $now,
            ]);
        }

        // =========================================================================
        // 15. TRANSAKSI SWITCHING STOCK (10 Proposals Antar-Cabang)
        // =========================================================================
        $switchPairs = [
            ['from' => 'KC-MLG', 'to' => 'KC-SBY', 'sku' => 'IT-TNR-001', 'qty' => 10, 'status' => 'APPROVED'],
            ['from' => 'KC-MLG', 'to' => 'KCP-GBG', 'sku' => 'IT-TNR-001', 'qty' => 5, 'status' => 'APPROVED'],
            ['from' => 'KC-SBY', 'to' => 'KC-KDR', 'sku' => 'ATK-KRT-001', 'qty' => 20, 'status' => 'APPROVED'],
            ['from' => 'KC-KDR', 'to' => 'KC-BWX', 'sku' => 'CTK-TAB-001', 'qty' => 30, 'status' => 'PROPOSED'],
            ['from' => 'KC-JBR', 'to' => 'KC-BWX', 'sku' => 'PRM-BSR-001', 'qty' => 10, 'status' => 'PROPOSED'],
            ['from' => 'KC-MDN', 'to' => 'KC-BJN', 'sku' => 'FLM-CIF-001', 'qty' => 15, 'status' => 'PROPOSED'],
            ['from' => 'KC-SBY', 'to' => 'KCP-DNY', 'sku' => 'ATK-PUL-001', 'qty' => 20, 'status' => 'COMPLETED'],
            ['from' => 'KC-MLG', 'to' => 'KCP-BAT', 'sku' => 'IT-EDC-001', 'qty' => 8, 'status' => 'COMPLETED'],
            ['from' => 'KC-KDR', 'to' => 'KC-MDN', 'sku' => 'KHZ-SGL-001', 'qty' => 15, 'status' => 'PROPOSED'],
            ['from' => 'KC-SBY', 'to' => 'KC-JBR', 'sku' => 'SVN-PWR-001', 'qty' => 10, 'status' => 'PROPOSED'],
        ];

        foreach ($switchPairs as $sw) {
            SwitchingStock::create([
                'order_id' => $createdOrders[0]->id,
                'item_id' => $items[$sw['sku']]->id,
                'source_organization_id' => $organizations[$sw['from']]->id,
                'source_warehouse_id' => $warehouses[$sw['from']]->id,
                'destination_organization_id' => $organizations[$sw['to']]->id,
                'destination_warehouse_id' => $warehouses[$sw['to']]->id,
                'qty_requested' => $sw['qty'],
                'proposed_by_user_id' => $users['inventory@bankjatim.co.id']->id,
                'approved_by_user_id' => $sw['status'] === 'APPROVED' || $sw['status'] === 'COMPLETED' ? $users['approver.sby@bankjatim.co.id']->id : null,
                'status' => $sw['status'],
                'recommendation_reason' => "Pemenuhan switching stock alternatif surplus dari {$sw['from']} ke {$sw['to']}",
                'created_at' => $now->copy()->subDays(8),
            ]);
        }

        // =========================================================================
        // 16. TRANSAKSI DISCREPANCIES (10 Berita Acara Selisih / Kerusakan)
        // =========================================================================
        $discrepancyList = [
            ['type' => 'DAMAGED', 'sku' => 'IT-TNR-001', 'exp' => 5, 'act' => 4, 'dam' => 1, 'notes' => 'Kemasan toner tertindih saat pengiriman ekspedisi, seal bocor 1 unit.'],
            ['type' => 'MISSING', 'sku' => 'ATK-PUL-001', 'exp' => 25, 'act' => 23, 'dam' => 0, 'notes' => 'Koli terbuka di perjalanan, selisih kurang 2 pack ballpoint.'],
            ['type' => 'WRONG_ITEM', 'sku' => 'CTK-SLP-001', 'exp' => 10, 'act' => 8, 'dam' => 0, 'notes' => 'Tercampur dengan slip penarikan 2 buku.'],
            ['type' => 'DAMAGED', 'sku' => 'CTK-TAB-001', 'exp' => 50, 'act' => 47, 'dam' => 3, 'notes' => 'Kardus basah terkena hujan saat transit gudang kurir.'],
            ['type' => 'EXCESS', 'sku' => 'IT-EDC-001', 'exp' => 6, 'act' => 7, 'dam' => 0, 'notes' => 'Kelebihan 1 roll thermal dari supplier.'],
            ['type' => 'DAMAGED', 'sku' => 'PRM-TMB-001', 'exp' => 15, 'act' => 13, 'dam' => 2, 'notes' => 'Tumbler penyok 2 pcs.'],
            ['type' => 'MISSING', 'sku' => 'KHZ-SGL-001', 'exp' => 10, 'act' => 9, 'dam' => 0, 'notes' => 'Kurang 1 pack segel kas.'],
            ['type' => 'DAMAGED', 'sku' => 'FLM-CIF-001', 'exp' => 10, 'act' => 9, 'dam' => 1, 'notes' => 'Formulir robek pada bagian tepi.'],
            ['type' => 'WRONG_ITEM', 'sku' => 'ATK-KRT-002', 'exp' => 8, 'act' => 7, 'dam' => 0, 'notes' => 'Terkirim ukuran A4 bukannya Folio.'],
            ['type' => 'DAMAGED', 'sku' => 'SVN-PWR-001', 'exp' => 10, 'act' => 9, 'dam' => 1, 'notes' => 'Powerbank tidak bisa menyala saat testing di cabang.'],
        ];

        $allReceivings = Receiving::all();
        $firstReceiving = $allReceivings->first();
        $firstOrderItem = $createdOrders[0]->items->first();

        $discCount = 1;
        foreach ($discrepancyList as $d) {
            Discrepancy::create([
                'receiving_id' => $firstReceiving ? $firstReceiving->id : 1,
                'order_item_id' => $firstOrderItem ? $firstOrderItem->id : 1,
                'item_id' => $items[$d['sku']]->id,
                'discrepancy_type' => $d['type'],
                'qty_expected' => $d['exp'],
                'qty_actual' => $d['act'],
                'qty_damaged' => $d['dam'],
                'resolution_status' => $discCount > 5 ? 'UNDER_REVIEW' : 'REPORTED',
                'resolution_notes' => $d['notes'],
                'created_at' => $now->copy()->subDays(12 - $discCount++),
            ]);
        }

        // =========================================================================
        // 17. NOTIFIKASI SISTEM (15 Notifikasi Berbagai Prioritas)
        // =========================================================================
        $notifData = [
            ['title' => 'Permintaan Approval PR Q1 2026', 'msg' => 'PR/2026/08/0001 membutuhkan otorisasi Pejabat Pengadaan.', 'type' => 'ACTION_REQUIRED', 'priority' => 'HIGH', 'url' => '/procurement/pr/1'],
            ['title' => 'Peringatan Safety Stock Kritis: Toner HP M528', 'msg' => 'Sisa stok bebas (Available: 12) berada di bawah Reorder Point (35). Segera ajukan PR pengadaan.', 'type' => 'ALERT', 'priority' => 'CRITICAL', 'url' => '/procurement/pr/create'],
            ['title' => 'Persetujuan Order Cabang Surabaya Menunggu Otorisasi', 'msg' => 'Order ORD/2026/08/0001 diajukan oleh KC Surabaya.', 'type' => 'ACTION_REQUIRED', 'priority' => 'HIGH', 'url' => '/orders/1'],
            ['title' => 'Klaim Discrepancy Barang Pengiriman Banyuwangi', 'msg' => 'Terdapat laporan kerusakan 1 unit Toner pada ekspedisi EXP-JNE-10293847.', 'type' => 'ALERT', 'priority' => 'HIGH', 'url' => '/receiving/discrepancies'],
            ['title' => 'Jurnal Inter-unit Settlement Siap Di-Posting', 'msg' => 'Settlement STL/2026/08/0001 sebesar Rp 14.500.000 menunggu approval finance.', 'type' => 'ACTION_REQUIRED', 'priority' => 'NORMAL', 'url' => '/finance/settlements'],
            ['title' => 'Proposal Switching Stock KC Malang Disetujui', 'msg' => 'Surplus toner KC Malang telah direservasi untuk pemenuhan cabang peminta.', 'type' => 'INFORMATION', 'priority' => 'NORMAL', 'url' => '/inventory/switching-stocks'],
            ['title' => 'Hasil Stock Opname Gudang Logistik SIER Berhasil Diposting', 'msg' => 'Seluruh rekonsiliasi fisik telah dicatat pada immutable Stock Ledger.', 'type' => 'INFORMATION', 'priority' => 'LOW', 'url' => '/inventory/stock-balances'],
            ['title' => 'Konsolidasi PR ke PO Berhasil', 'msg' => 'PO/2026/08/0001 telah diterbitkan ke PT Datascrip Surabaya.', 'type' => 'INFORMATION', 'priority' => 'LOW', 'url' => '/procurement/po/1'],
            ['title' => 'Serapan Pagu Anggaran KC Malang Mencapai 40%', 'msg' => 'Monitoring realisasi anggaran persediaan triwulan 1 berjalan sesuai pagu.', 'type' => 'INFORMATION', 'priority' => 'LOW', 'url' => '/master/budgets'],
            ['title' => 'Penerimaan Barang Vendor (GRN) Selesai', 'msg' => 'GRN/2026/08/0001 sebanyak 50 Unit Toner HP telah masuk gudang pusat.', 'type' => 'INFORMATION', 'priority' => 'LOW', 'url' => '/procurement/po/1'],
            ['title' => 'Manifest Pengiriman Ekspedisi Diterbitkan', 'msg' => 'Surat jalan MNF/2026/08/0001 siap diberangkatkan via Armada Internal Bank Jatim.', 'type' => 'INFORMATION', 'priority' => 'NORMAL', 'url' => '/distribution/shipments'],
            ['title' => 'Peringatan Reorder Point Kertas Thermal EDC', 'msg' => 'Stok kertas EDC gudang pusat mendekati batas minimum (Tersisa 18 Box).', 'type' => 'ALERT', 'priority' => 'HIGH', 'url' => '/inventory/forecasting'],
            ['title' => 'Audit Trail Login dari IP Baru', 'msg' => 'Pengguna Super Admin login dari perangkat workstation logistik.', 'type' => 'INFORMATION', 'priority' => 'LOW', 'url' => '/audit-trail'],
            ['title' => 'Verifikasi POD Penerimaan KCP Gubeng Selesai', 'msg' => 'Tanda tangan elektronik digital penerima telah divalidasi.', 'type' => 'INFORMATION', 'priority' => 'LOW', 'url' => '/receiving'],
            ['title' => 'Pagu Anggaran Tahun 2026 Aktif', 'msg' => 'Seluruh cost center unit kerja telah dapat mengajukan kebutuhan operasional.', 'type' => 'INFORMATION', 'priority' => 'LOW', 'url' => '/master/budgets'],
        ];

        foreach ($notifData as $n) {
            Notification::create([
                'user_id' => null,
                'target_role' => null,
                'target_organization_id' => null,
                'title' => $n['title'],
                'message' => $n['msg'],
                'type' => $n['type'],
                'priority' => $n['priority'],
                'action_url' => $n['url'],
                'is_read' => false,
                'created_at' => $now->copy()->subHours(rand(1, 48)),
            ]);
        }

        // =========================================================================
        // 18. AUDIT LOGS (20 Audit Trail Records)
        // =========================================================================
        $auditActions = [
            ['action' => 'LOGIN', 'type' => User::class, 'id' => 1, 'notes' => 'User super admin login sukses via SSO Bank Jatim'],
            ['action' => 'CREATE_PR', 'type' => PurchaseRequest::class, 'id' => 1, 'notes' => 'Pembuatan PR Pengadaan Toner Q1 2026'],
            ['action' => 'APPROVE_PR', 'type' => PurchaseRequest::class, 'id' => 1, 'notes' => 'Approval PR oleh Pejabat Pengadaan'],
            ['action' => 'CONSOLIDATE_PO', 'type' => PurchaseOrder::class, 'id' => 1, 'notes' => 'Konsolidasi 2 PR menjadi 1 PO Vendor'],
            ['action' => 'RECEIVE_GRN', 'type' => GoodsReceipt::class, 'id' => 1, 'notes' => 'Penerimaan fisik barang vendor ke gudang logistik'],
            ['action' => 'POST_STOCK_LEDGER', 'type' => StockLedger::class, 'id' => 1, 'notes' => 'Posting otomatis mutasi penerimaan vendor ke buku besar stok'],
            ['action' => 'CREATE_ORDER', 'type' => Order::class, 'id' => 1, 'notes' => 'Pengajuan order kebutuhan cabang Surabaya'],
            ['action' => 'APPROVE_ORDER', 'type' => Order::class, 'id' => 1, 'notes' => 'Persetujuan order dan reservasi stok otomatis'],
            ['action' => 'PICKING_COMPLETE', 'type' => WarehousePicking::class, 'id' => 1, 'notes' => 'Penyelesaian picking list barang di gudang SIER'],
            ['action' => 'PACKING_COMPLETE', 'type' => WarehousePacking::class, 'id' => 1, 'notes' => 'Pengepakan paket koli dan pencetakan label QR Code'],
            ['action' => 'DISPATCH_SHIPMENT', 'type' => Shipment::class, 'id' => 1, 'notes' => 'Penerbitan surat jalan & serah terima ke kurir JNE'],
            ['action' => 'RECEIVE_BRANCH', 'type' => Receiving::class, 'id' => 1, 'notes' => 'Konfirmasi penerimaan di cabang tujuan dengan tanda tangan POD'],
            ['action' => 'REPORT_DISCREPANCY', 'type' => Discrepancy::class, 'id' => 1, 'notes' => 'Pencatatan berita acara selisih barang rusak'],
            ['action' => 'CREATE_SETTLEMENT', 'type' => Settlement::class, 'id' => 1, 'notes' => 'Pembentukan jurnal akuntansi settlement antarunit'],
            ['action' => 'POST_SETTLEMENT', 'type' => Settlement::class, 'id' => 1, 'notes' => 'Posting realisasi anggaran dan settlement finance'],
            ['action' => 'PROPOSE_SWITCHING', 'type' => SwitchingStock::class, 'id' => 1, 'notes' => 'Pengajuan switching stock surplus KC Malang'],
            ['action' => 'APPROVE_SWITCHING', 'type' => SwitchingStock::class, 'id' => 1, 'notes' => 'Otorisasi pemindahan stok antar cabang'],
            ['action' => 'STOCK_OPNAME_ADJUST', 'type' => StockLedger::class, 'id' => 2, 'notes' => 'Penyesuaian hasil stock opname berkala'],
            ['action' => 'UPDATE_MASTER_ITEM', 'type' => Item::class, 'id' => 1, 'notes' => 'Pembaruan parameter safety stock dan ROP barang'],
            ['action' => 'EXPORT_REPORT_CSV', 'type' => Organization::class, 'id' => 1, 'notes' => 'Ekspor laporan valuasi persediaan ke format CSV'],
        ];

        foreach ($auditActions as $a) {
            AuditLog::create([
                'user_id' => $users['admin@bankjatim.co.id']->id,
                'action' => $a['action'],
                'auditable_type' => $a['type'],
                'auditable_id' => $a['id'],
                'old_values' => null,
                'new_values' => ['action_note' => $a['notes']],
                'ip_address' => '192.168.18.'.rand(10, 99),
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'created_at' => $now->copy()->subDays(rand(1, 30)),
            ]);
        }
    }
}
