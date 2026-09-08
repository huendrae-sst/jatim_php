<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeTransactionDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:purge {--force : Jalankan pembersihan tanpa konfirmasi interaktif}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus seluruh data transaksi sistem (Order, PR, PO, Penerimaan, Distribusi, Opname, Mutasi Stok, Notifikasi, Audit Log) dan reset saldo stok ke 0 untuk entri ulang.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('PERHATIAN: Seluruh data transaksi akan dihapus permanen dan saldo persediaan direset ke 0. Data master (User, Cabang, Gudang, Item, Vendor, CoA, Pagu) akan tetap dipertahankan. Lanjutkan?')) {
            $this->warn('Operasi dibatalkan.');

            return Command::SUCCESS;
        }

        $this->info('Memulai pembersihan data transaksi...');

        $deletedCounts = [];

        DB::transaction(function () use (&$deletedCounts) {
            // Urutan penghapusan tabel dari child ke parent (Foreign Key safe)
            $transactionTables = [
                'discrepancies',
                'receivings',
                'settlements',
                'shipments',
                'warehouse_packings',
                'warehouse_pickings',
                'order_allocations',
                'switching_stock_items',
                'switching_stocks',
                'order_items',
                'orders',
                'goods_receipt_items',
                'goods_receipts',
                'purchase_order_items',
                'purchase_orders',
                'purchase_request_items',
                'purchase_requests',
                'stock_opname_items',
                'stock_opnames',
                'stock_ledgers',
                'notifications',
                'audit_logs',
            ];

            foreach ($transactionTables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $count = DB::table($table)->count();
                    DB::table($table)->delete();
                    $deletedCounts[$table] = $count;
                }
            }

            // Reset Saldo Stok menjadi 0
            if (DB::getSchemaBuilder()->hasTable('stock_balances')) {
                DB::table('stock_balances')->update([
                    'on_hand' => 0,
                    'reserved' => 0,
                    'allocated' => 0,
                    'in_transit' => 0,
                    'hold' => 0,
                    'damaged' => 0,
                    'updated_at' => now(),
                ]);

                // Pastikan setiap kombinasi Gudang & Item aktif memiliki record saldo awal 0
                $warehouses = Warehouse::where('is_active', true)->pluck('id');
                $items = Item::where('is_active', true)->pluck('id');
                foreach ($warehouses as $whId) {
                    foreach ($items as $itemId) {
                        DB::table('stock_balances')->updateOrInsert(
                            ['warehouse_id' => $whId, 'item_id' => $itemId],
                            [
                                'on_hand' => 0,
                                'reserved' => 0,
                                'allocated' => 0,
                                'in_transit' => 0,
                                'hold' => 0,
                                'damaged' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }

            // Reset Akumulasi Komitmen & Realisasi Anggaran ke 0 (Pertahankan Pagu / Allocated Amount)
            if (DB::getSchemaBuilder()->hasTable('budgets')) {
                DB::table('budgets')->update([
                    'committed_amount' => 0,
                    'realized_amount' => 0,
                    'updated_at' => now(),
                ]);
            }
        });

        $this->newLine();
        $this->info('✓ Seluruh data transaksi berhasil dibersihkan!');
        $this->newLine();

        $rows = [];
        foreach ($deletedCounts as $table => $count) {
            $rows[] = [$table, number_format($count).' data dihapus'];
        }
        $this->table(['Tabel Transaksi', 'Status Penghapusan'], $rows);

        $this->newLine();
        $this->info('Ringkasan Data Master yang Tetap Terjaga:');
        $masterData = [
            ['Pengguna (Users)', DB::table('users')->count()],
            ['Organisasi & Unit Kerja', DB::table('organizations')->count()],
            ['Gudang (Warehouses)', DB::table('warehouses')->count()],
            ['Cost Center', DB::getSchemaBuilder()->hasTable('cost_centers') ? DB::table('cost_centers')->count() : 0],
            ['Rekening Buku Besar (CoA)', DB::getSchemaBuilder()->hasTable('chart_of_accounts') ? DB::table('chart_of_accounts')->count() : 0],
            ['Kategori Barang', DB::table('categories')->count()],
            ['Master Item / SKU', DB::table('items')->count()],
            ['Konversi Satuan', DB::getSchemaBuilder()->hasTable('item_conversions') ? DB::table('item_conversions')->count() : 0],
            ['Rekanan Vendor', DB::table('vendors')->count()],
            ['Kurir & Ekspedisi', DB::table('couriers')->count()],
            ['Pagu Anggaran (Budgets)', DB::table('budgets')->count()],
        ];
        $this->table(['Data Master', 'Jumlah Data Aktif'], $masterData);

        return Command::SUCCESS;
    }
}
