<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemConversion;
use Illuminate\Database\Seeder;

class ItemConversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = Item::all()->keyBy('sku');

        $conversionsData = [
            ['item_sku' => 'ATK-KRT-001', 'from_uom' => 'BOX', 'conversion_factor' => 5, 'to_uom' => 'RIM', 'description' => '1 Box Kertas HVS A4 berisi 5 Rim', 'is_active' => true],
            ['item_sku' => 'ATK-KRT-002', 'from_uom' => 'BOX', 'conversion_factor' => 5, 'to_uom' => 'RIM', 'description' => '1 Box Kertas HVS F4 berisi 5 Rim', 'is_active' => true],
            ['item_sku' => 'ATK-PUL-001', 'from_uom' => 'PACK', 'conversion_factor' => 12, 'to_uom' => 'PCS', 'description' => '1 Pack Ballpoint Standard AE7 berisi 12 Pcs', 'is_active' => true],
            ['item_sku' => 'IT-EDC-001', 'from_uom' => 'BOX', 'conversion_factor' => 100, 'to_uom' => 'ROLL', 'description' => '1 Box Kertas Thermal EDC berisi 100 Roll', 'is_active' => true],
            ['item_sku' => 'CTK-TAB-001', 'from_uom' => 'PACK', 'conversion_factor' => 50, 'to_uom' => 'BUKU', 'description' => '1 Pack Buku Tabungan Simpeda berisi 50 Buku', 'is_active' => true],
            ['item_sku' => 'CTK-BG-001', 'from_uom' => 'PACK', 'conversion_factor' => 10, 'to_uom' => 'BUKU', 'description' => '1 Pack Warkat Bilyet Giro berisi 10 Buku', 'is_active' => true],
            ['item_sku' => 'KHZ-SGL-001', 'from_uom' => 'PACK', 'conversion_factor' => 100, 'to_uom' => 'PCS', 'description' => '1 Pack Segel Plastik berisi 100 Pcs', 'is_active' => true],
            ['item_sku' => null, 'from_uom' => 'DUS', 'conversion_factor' => 10, 'to_uom' => 'BOX', 'description' => '1 Dus Karton Master standar setara 10 Box', 'is_active' => true],
            ['item_sku' => null, 'from_uom' => 'RIM', 'conversion_factor' => 500, 'to_uom' => 'LEMBAR', 'description' => '1 Rim kertas cetak standar berisi 500 Lembar', 'is_active' => true],
            ['item_sku' => null, 'from_uom' => 'LUSIN', 'conversion_factor' => 12, 'to_uom' => 'PCS', 'description' => '1 Lusin standar kelipatan 12 Pieces', 'is_active' => true],
        ];

        foreach ($conversionsData as $cd) {
            $itemId = $cd['item_sku'] && isset($items[$cd['item_sku']]) ? $items[$cd['item_sku']]->id : null;

            ItemConversion::firstOrCreate(
                [
                    'item_id' => $itemId,
                    'from_uom' => $cd['from_uom'],
                    'to_uom' => $cd['to_uom'],
                ],
                [
                    'conversion_factor' => $cd['conversion_factor'],
                    'description' => $cd['description'],
                    'is_active' => $cd['is_active'],
                ]
            );
        }
    }
}
