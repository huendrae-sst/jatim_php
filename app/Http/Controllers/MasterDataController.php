<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Courier;
use App\Models\Item;
use App\Models\ItemConversion;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    // Items
    public function itemsIndex(Request $request)
    {
        $tab = $request->get('tab');
        if (! $tab) {
            if ($request->has('conv_page') || $request->has('conv_search') || $request->has('conv_item_id') || $request->has('conv_status')) {
                $tab = 'conversions';
            } elseif ($request->has('cat_search') || $request->has('cat_status')) {
                $tab = 'categories';
            } elseif ($request->has('uom_search') || $request->has('uom_type') || $request->has('uom_usage')) {
                $tab = 'uoms';
            } else {
                $tab = 'items';
            }
        }
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $status = $request->get('status');
        $catSearch = $request->get('cat_search');
        $catStatus = $request->get('cat_status');
        $uomSearch = $request->get('uom_search');
        $uomType = $request->get('uom_type');
        $uomUsage = $request->get('uom_usage');

        $query = Item::with(['category', 'stockBalances.warehouse']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('specification', 'like', "%{$search}%");
            });
        }

        if ($categoryId && $categoryId !== 'ALL') {
            $query->where('category_id', $categoryId);
        }

        if ($status) {
            if ($status === 'ACTIVE') {
                $query->where('is_active', true);
            } elseif ($status === 'INACTIVE') {
                $query->where('is_active', false);
            }
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $items = $query->orderBy('sku')->paginate($perPage)->withQueryString();

        // Categories query
        $catQuery = Category::withCount('items');
        if ($catSearch) {
            $catQuery->where(function ($q) use ($catSearch) {
                $q->where('code', 'like', "%{$catSearch}%")
                    ->orWhere('name', 'like', "%{$catSearch}%")
                    ->orWhere('description', 'like', "%{$catSearch}%");
            });
        }
        if ($catStatus === 'WITH_ITEMS') {
            $catQuery->has('items');
        } elseif ($catStatus === 'NO_ITEMS') {
            $catQuery->doesntHave('items');
        }
        $categories = $catQuery->orderBy('name')->get();
        $allCategories = Category::orderBy('name')->get();

        $totalItemsCount = Item::count();
        $activeItemsCount = Item::where('is_active', true)->count();
        $inactiveItemsCount = Item::where('is_active', false)->count();

        // UOM List with usage counts
        $itemUomCounts = Item::select('uom', DB::raw('count(*) as total'))
            ->groupBy('uom')
            ->pluck('total', 'uom')
            ->toArray();

        $deletedUoms = Cache::get('deleted_uoms', []);
        $customUoms = Cache::get('custom_uoms', []);

        $baseUoms = [
            ['code' => 'PCS', 'name' => 'Pieces / Satuan Terkecil', 'type' => 'Kuantitas', 'description' => 'Satuan untuk barang eceran atau satuan tunggal (pena, tumbler, spons, dll.)'],
            ['code' => 'BOX', 'name' => 'Box / Kotak', 'type' => 'Kemasan', 'description' => 'Kemasan boks berisi beberapa unit/roll/lembar (kertas HVS, slip setoran, dll.)'],
            ['code' => 'PACK', 'name' => 'Pack / Bungkus', 'type' => 'Kemasan', 'description' => 'Kemasan pack atau bungkus kelipatan unit (buku tabungan, bilyet giro, segel, dll.)'],
            ['code' => 'UNIT', 'name' => 'Unit / Perangkat', 'type' => 'Perangkat', 'description' => 'Satuan untuk perangkat, perlengkapan elektronik, stempel, atau mesin kasir'],
            ['code' => 'RIM', 'name' => 'Rim (500 Lembar)', 'type' => 'Kertas', 'description' => 'Satuan standar kertas cetak/fotokopi berisi 500 lembar'],
            ['code' => 'SET', 'name' => 'Set / Pasang', 'type' => 'Paket', 'description' => 'Kumpulan barang yang merupakan satu kesatuan lengkap yang tidak dipisah'],
            ['code' => 'ROLL', 'name' => 'Roll / Gulung', 'type' => 'Gulungan', 'description' => 'Gulungan kertas seperti roll thermal EDC/ATM, pita banner, atau kabel'],
            ['code' => 'BUKU', 'name' => 'Buku / Eksemplar', 'type' => 'Publikasi', 'description' => 'Buku administrasi, buku pedoman, atau formulir berformat jilid buku'],
            ['code' => 'LEMBAR', 'name' => 'Lembar / Sheet', 'type' => 'Kertas', 'description' => 'Satuan per lembar kertas cetak khusus atau dokumen resmi'],
            ['code' => 'DUS', 'name' => 'Dus / Karton', 'type' => 'Kemasan', 'description' => 'Kemasan master box karton pengiriman grosir dari penyedia'],
            ['code' => 'BOTOL', 'name' => 'Botol / Bottle', 'type' => 'Cairan', 'description' => 'Kemasan botol cairan tinta refill, cairan pembersih, atau disinfektan'],
            ['code' => 'LUSIN', 'name' => 'Lusin (12 Pcs)', 'type' => 'Kuantitas', 'description' => 'Kelipatan 12 satuan barang sejenis'],
            ['code' => 'METER', 'name' => 'Meter (m)', 'type' => 'Panjang', 'description' => 'Satuan ukuran panjang untuk kabel jaringan, pita pembatas, dll.'],
        ];

        // Filter out deleted base uoms
        $baseUoms = array_values(array_filter($baseUoms, fn ($bu) => ! in_array($bu['code'], $deletedUoms)));

        foreach ($customUoms as $code => $cu) {
            if (in_array($code, $deletedUoms)) {
                continue;
            }
            $exists = false;
            foreach ($baseUoms as &$bu) {
                if ($bu['code'] === $code) {
                    $bu = array_merge($bu, $cu);
                    $exists = true;
                    break;
                }
            }
            unset($bu);
            if (! $exists) {
                $baseUoms[] = $cu;
            }
        }

        foreach ($itemUomCounts as $code => $cnt) {
            $exists = false;
            foreach ($baseUoms as &$bu) {
                if ($bu['code'] === $code) {
                    $bu['items_count'] = $cnt;
                    $exists = true;
                    break;
                }
            }
            unset($bu);
            if (! $exists) {
                $baseUoms[] = [
                    'code' => $code,
                    'name' => $code.' (Satuan Khusus)',
                    'type' => 'Khusus',
                    'description' => 'Satuan yang tercatat dari data transaksi barang',
                    'items_count' => $cnt,
                ];
            }
        }

        foreach ($baseUoms as &$bu) {
            if (! isset($bu['items_count'])) {
                $bu['items_count'] = $itemUomCounts[$bu['code']] ?? 0;
            }
        }
        unset($bu);

        $allUoms = collect($baseUoms)->sortBy('code')->values();
        $uomList = collect($baseUoms);
        $allUomTypes = $uomList->pluck('type')->filter()->unique()->values()->all();

        if ($uomSearch) {
            $uomSearchLower = strtolower($uomSearch);
            $uomList = $uomList->filter(function ($u) use ($uomSearchLower) {
                return str_contains(strtolower($u['code']), $uomSearchLower) ||
                    str_contains(strtolower($u['name']), $uomSearchLower) ||
                    str_contains(strtolower($u['type'] ?? ''), $uomSearchLower) ||
                    str_contains(strtolower($u['description'] ?? ''), $uomSearchLower);
            });
        }

        if ($uomType && $uomType !== 'ALL') {
            $uomList = $uomList->filter(function ($u) use ($uomType) {
                return ($u['type'] ?? '') === $uomType;
            });
        }

        if ($uomUsage) {
            if ($uomUsage === 'USED') {
                $uomList = $uomList->filter(function ($u) {
                    return ($u['items_count'] ?? 0) > 0;
                });
            } elseif ($uomUsage === 'UNUSED') {
                $uomList = $uomList->filter(function ($u) {
                    return ($u['items_count'] ?? 0) == 0;
                });
            }
        }

        // Conversions
        $convSearch = $request->get('conv_search');
        $convItemId = $request->get('conv_item_id');
        $convStatus = $request->get('conv_status');

        $convQuery = ItemConversion::with('item')
            ->when($convSearch, function ($q) use ($convSearch) {
                $q->where(function ($sub) use ($convSearch) {
                    $sub->where('from_uom', 'like', "%{$convSearch}%")
                        ->orWhere('to_uom', 'like', "%{$convSearch}%")
                        ->orWhere('description', 'like', "%{$convSearch}%")
                        ->orWhereHas('item', function ($iq) use ($convSearch) {
                            $iq->where('name', 'like', "%{$convSearch}%")
                                ->orWhere('sku', 'like', "%{$convSearch}%");
                        });
                });
            })
            ->when($convItemId, function ($q) use ($convItemId) {
                if ($convItemId === 'global') {
                    $q->whereNull('item_id');
                } else {
                    $q->where('item_id', $convItemId);
                }
            })
            ->when($convStatus !== null && $convStatus !== '' && $convStatus !== 'ALL', function ($q) use ($convStatus) {
                $q->where('is_active', $convStatus === 'ACTIVE' || $convStatus === '1');
            })
            ->orderBy('id', 'desc');

        $conversions = $convQuery->paginate($perPage, ['*'], 'conv_page')->withQueryString();
        $allItems = Item::where('is_active', true)->orderBy('name')->get();

        return view('master.items', compact(
            'items',
            'categories',
            'allCategories',
            'allUoms',
            'uomList',
            'allUomTypes',
            'conversions',
            'allItems',
            'tab',
            'perPage',
            'search',
            'catSearch',
            'catStatus',
            'uomSearch',
            'uomType',
            'uomUsage',
            'convSearch',
            'convItemId',
            'convStatus',
            'categoryId',
            'status',
            'totalItemsCount',
            'activeItemsCount',
            'inactiveItemsCount'
        ));
    }

    public function categoryStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:categories,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $category = Category::create($validated);

        return redirect()->route('master.items', ['tab' => 'categories'])->with('success', "Kategori Barang [{$category->code}] {$category->name} berhasil ditambahkan.");
    }

    public function categoryUpdate(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:categories,code,'.$category->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $category->update($validated);

        return redirect()->route('master.items', ['tab' => 'categories'])->with('success', "Kategori Barang [{$category->code}] {$category->name} berhasil diperbarui.");
    }

    public function categoryDestroy($id)
    {
        $category = Category::withCount('items')->findOrFail($id);

        if ($category->items_count > 0) {
            return redirect()->route('master.items', ['tab' => 'categories'])->with('error', "Kategori [{$category->code}] tidak dapat dihapus karena masih digunakan oleh {$category->items_count} barang persediaan.");
        }

        $code = $category->code;
        $name = $category->name;
        $category->delete();

        return redirect()->route('master.items', ['tab' => 'categories'])->with('success', "Kategori Barang [{$code}] {$name} berhasil dihapus.");
    }

    public function uomStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $code = strtoupper(trim($validated['code']));

        $deletedUoms = Cache::get('deleted_uoms', []);
        $deletedUoms = array_values(array_diff($deletedUoms, [$code]));
        Cache::forever('deleted_uoms', $deletedUoms);

        $customUoms = Cache::get('custom_uoms', []);
        $customUoms[$code] = [
            'code' => $code,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'Satuan Tambahan',
            'description' => $validated['description'] ?? 'Satuan unit persediaan terkonfigurasi',
        ];
        Cache::forever('custom_uoms', $customUoms);

        return redirect()->route('master.items', ['tab' => 'uoms'])->with('success', "Satuan [{$code}] {$validated['name']} berhasil ditambahkan.");
    }

    public function uomUpdate(Request $request, string $code)
    {
        $code = strtoupper(trim($code));

        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'type' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $newCode = strtoupper(trim($validated['code']));

        $customUoms = Cache::get('custom_uoms', []);
        $deletedUoms = Cache::get('deleted_uoms', []);

        if ($newCode !== $code) {
            Item::where('uom', $code)->update(['uom' => $newCode]);
            unset($customUoms[$code]);
            $deletedUoms[] = $code;
            $deletedUoms = array_values(array_unique($deletedUoms));
        }

        $deletedUoms = array_values(array_diff($deletedUoms, [$newCode]));
        Cache::forever('deleted_uoms', $deletedUoms);

        $customUoms[$newCode] = [
            'code' => $newCode,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'Satuan Tambahan',
            'description' => $validated['description'] ?? 'Satuan unit persediaan terkonfigurasi',
        ];
        Cache::forever('custom_uoms', $customUoms);

        return redirect()->route('master.items', ['tab' => 'uoms'])
            ->with('success', "Satuan [{$newCode}] {$validated['name']} berhasil diperbarui.");
    }

    public function uomDestroy(string $code)
    {
        $code = strtoupper(trim($code));

        $itemsCount = Item::where('uom', $code)->count();
        if ($itemsCount > 0) {
            return redirect()->route('master.items', ['tab' => 'uoms'])
                ->with('error', "Satuan [{$code}] tidak dapat dihapus karena masih digunakan oleh {$itemsCount} barang persediaan.");
        }

        $customUoms = Cache::get('custom_uoms', []);
        unset($customUoms[$code]);
        Cache::forever('custom_uoms', $customUoms);

        $deletedUoms = Cache::get('deleted_uoms', []);
        $deletedUoms[] = $code;
        $deletedUoms = array_values(array_unique($deletedUoms));
        Cache::forever('deleted_uoms', $deletedUoms);

        return redirect()->route('master.items', ['tab' => 'uoms'])
            ->with('success', "Satuan [{$code}] berhasil dihapus.");
    }

    public function conversionStore(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'nullable|exists:items,id',
            'from_uom' => 'required|string|max:50',
            'conversion_factor' => 'required|numeric|gt:0',
            'to_uom' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['from_uom'] = strtoupper(trim($validated['from_uom']));
        $validated['to_uom'] = strtoupper(trim($validated['to_uom']));
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : true;

        $conversion = ItemConversion::create($validated);

        $targetDesc = $conversion->item ? "[{$conversion->item->sku}] {$conversion->item->name}" : 'Semua Barang (Global)';

        return redirect()->route('master.items', ['tab' => 'conversions'])
            ->with('success', "Konversi satuan [1 {$conversion->from_uom} = ".((float) $conversion->conversion_factor)." {$conversion->to_uom}] untuk {$targetDesc} berhasil ditambahkan.");
    }

    public function conversionUpdate(Request $request, $id)
    {
        $conversion = ItemConversion::findOrFail($id);

        $validated = $request->validate([
            'item_id' => 'nullable|exists:items,id',
            'from_uom' => 'required|string|max:50',
            'conversion_factor' => 'required|numeric|gt:0',
            'to_uom' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['from_uom'] = strtoupper(trim($validated['from_uom']));
        $validated['to_uom'] = strtoupper(trim($validated['to_uom']));
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        $conversion->update($validated);

        return redirect()->route('master.items', ['tab' => 'conversions'])
            ->with('success', "Konversi satuan [1 {$conversion->from_uom} = ".((float) $conversion->conversion_factor)." {$conversion->to_uom}] berhasil diperbarui.");
    }

    public function conversionDestroy($id)
    {
        $conversion = ItemConversion::findOrFail($id);
        $formula = "1 {$conversion->from_uom} = ".((float) $conversion->conversion_factor)." {$conversion->to_uom}";
        $conversion->delete();

        return redirect()->route('master.items', ['tab' => 'conversions'])
            ->with('success', "Konversi satuan [{$formula}] berhasil dihapus.");
    }

    public function itemStore(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sku' => 'required|string|unique:items,sku',
            'barcode' => 'nullable|string|unique:items,barcode',
            'name' => 'required|string|max:255',
            'uom' => 'required|string|max:50',
            'specification' => 'nullable|string',
            'estimated_unit_price' => 'required|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'safety_stock' => 'required|integer|min:0',
            'reorder_point' => 'required|integer|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = true;
        if (! isset($validated['max_stock'])) {
            $validated['max_stock'] = max(500, (int) $validated['reorder_point'] * 5);
        }
        if (! isset($validated['lead_time_days'])) {
            $validated['lead_time_days'] = 5;
        }

        $item = Item::create($validated);

        return back()->with('success', "Master Barang {$item->name} ({$item->sku}) berhasil ditambahkan.");
    }

    public function itemUpdate(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sku' => 'required|string|unique:items,sku,'.$item->id,
            'barcode' => 'nullable|string|unique:items,barcode,'.$item->id,
            'name' => 'required|string|max:255',
            'uom' => 'required|string|max:50',
            'specification' => 'nullable|string',
            'estimated_unit_price' => 'required|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'safety_stock' => 'required|integer|min:0',
            'reorder_point' => 'required|integer|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? (bool) $request->is_active : false;

        $item->update($validated);

        return back()->with('success', "Master Barang {$item->name} ({$item->sku}) berhasil diperbarui.");
    }

    public function itemDestroy($id)
    {
        $item = Item::findOrFail($id);
        $name = $item->name;
        $sku = $item->sku;

        try {
            $item->delete();

            return back()->with('success', "Master Barang {$name} ({$sku}) berhasil dihapus.");
        } catch (\Exception $e) {
            return back()->with('error', "Barang {$name} ({$sku}) tidak dapat dihapus karena sudah terikat dengan transaksi atau riwayat persediaan. Anda dapat mengubah statusnya menjadi Non-Aktif melalui menu Edit.");
        }
    }

    // Organizations & Warehouses CRUD
    public function orgIndex(Request $request)
    {
        $tab = $request->get('tab');
        if (! $tab) {
            if ($request->has('wh_page') || $request->has('wh_search') || $request->has('wh_type') || $request->has('wh_org') || $request->has('wh_status')) {
                $tab = 'warehouses';
            } else {
                $tab = 'organizations';
            }
        }
        $orgSearch = $request->get('search');
        $orgType = $request->get('type');
        $orgStatus = $request->get('status');

        $whSearch = $request->get('wh_search');
        $whType = $request->get('wh_type');
        $whOrg = $request->get('wh_org');
        $whStatus = $request->get('wh_status');

        $orgQuery = Organization::with(['parent', 'warehouses', 'users', 'children']);

        if ($orgSearch) {
            $orgQuery->where(function ($q) use ($orgSearch) {
                $q->where('code', 'like', "%{$orgSearch}%")
                    ->orWhere('name', 'like', "%{$orgSearch}%")
                    ->orWhere('city', 'like', "%{$orgSearch}%")
                    ->orWhere('cost_center_code', 'like', "%{$orgSearch}%");
            });
        }

        if ($orgType && $orgType !== 'ALL') {
            $orgQuery->where('type', $orgType);
        }

        if ($orgStatus) {
            if ($orgStatus === 'ACTIVE') {
                $orgQuery->where('is_active', true);
            } elseif ($orgStatus === 'INACTIVE') {
                $orgQuery->where('is_active', false);
            }
        }

        $whQuery = Warehouse::with(['organization', 'stockBalances']);

        if ($whSearch) {
            $whQuery->where(function ($q) use ($whSearch) {
                $q->where('code', 'like', "%{$whSearch}%")
                    ->orWhere('name', 'like', "%{$whSearch}%");
            });
        }

        if ($whType && $whType !== 'ALL') {
            $whQuery->where('type', $whType);
        }

        if ($whOrg && $whOrg !== 'ALL') {
            $whQuery->where('organization_id', $whOrg);
        }

        if ($whStatus) {
            if ($whStatus === 'ACTIVE') {
                $whQuery->where('is_active', true);
            } elseif ($whStatus === 'INACTIVE') {
                $whQuery->where('is_active', false);
            }
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $organizations = $orgQuery->orderBy('code')->paginate($perPage, ['*'], 'org_page')->withQueryString();
        $warehouses = $whQuery->orderBy('code')->paginate($perPage, ['*'], 'wh_page')->withQueryString();

        $parents = Organization::where('is_active', true)->whereIn('type', ['HEAD_OFFICE', 'MAIN_BRANCH'])->orderBy('name')->get();
        $allOrganizations = Organization::where('is_active', true)->orderBy('name')->get();

        // Aggregates for Small Boxes
        $totalCount = Organization::count();
        $headOfficeCount = Organization::where('type', 'HEAD_OFFICE')->count();
        $mainBranchCount = Organization::where('type', 'MAIN_BRANCH')->count();
        $subBranchCount = Organization::where('type', 'SUB_BRANCH')->count();
        $warehouseCount = Organization::where('type', 'WAREHOUSE')->count();

        $totalWarehouseCount = Warehouse::count();
        $centralWarehouseCount = Warehouse::where('type', 'CENTRAL_LOGISTICS')->count();
        $branchWarehouseCount = Warehouse::where('type', 'BRANCH_STORAGE')->count();

        return view('master.organizations', compact(
            'organizations',
            'parents',
            'allOrganizations',
            'warehouses',
            'tab',
            'perPage',
            'orgSearch',
            'orgType',
            'orgStatus',
            'whSearch',
            'whType',
            'whOrg',
            'whStatus',
            'totalCount',
            'headOfficeCount',
            'mainBranchCount',
            'subBranchCount',
            'warehouseCount',
            'totalWarehouseCount',
            'centralWarehouseCount',
            'branchWarehouseCount'
        ));
    }

    public function orgStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:organizations,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:HEAD_OFFICE,MAIN_BRANCH,SUB_BRANCH,WAREHOUSE',
            'parent_id' => 'nullable|exists:organizations,id',
            'cost_center_code' => 'nullable|string|max:50',
            'city' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'create_warehouse' => 'nullable|boolean',
        ]);

        $org = Organization::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'parent_id' => $validated['parent_id'] ?: null,
            'cost_center_code' => ! empty($validated['cost_center_code']) ? strtoupper($validated['cost_center_code']) : null,
            'city' => $validated['city'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]);

        if ($request->boolean('create_warehouse', true)) {
            Warehouse::create([
                'organization_id' => $org->id,
                'code' => 'WH-'.strtoupper($org->code),
                'name' => 'Gudang '.$org->name,
                'type' => in_array($org->type, ['HEAD_OFFICE', 'WAREHOUSE']) ? 'CENTRAL_LOGISTICS' : 'BRANCH_STORAGE',
                'address' => $org->address,
                'is_active' => true,
            ]);
        }

        return back()->with('success', "Unit Kerja [{$org->code}] {$org->name} berhasil ditambahkan.");
    }

    public function orgUpdate(Request $request, $id)
    {
        $org = Organization::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:organizations,code,'.$org->id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:HEAD_OFFICE,MAIN_BRANCH,SUB_BRANCH,WAREHOUSE',
            'parent_id' => 'nullable|exists:organizations,id|not_in:'.$org->id,
            'cost_center_code' => 'nullable|string|max:50',
            'city' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        $org->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'parent_id' => $validated['parent_id'] ?: null,
            'cost_center_code' => ! empty($validated['cost_center_code']) ? strtoupper($validated['cost_center_code']) : null,
            'city' => $validated['city'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return back()->with('success', "Unit Kerja [{$org->code}] berhasil diperbarui.");
    }

    public function orgToggleStatus($id)
    {
        $org = Organization::findOrFail($id);
        $org->is_active = ! $org->is_active;
        $org->save();

        $statusLabel = $org->is_active ? 'Diaktifkan' : 'Dinonaktifkan';

        return back()->with('success', "Status Unit Kerja [{$org->code}] berhasil diubah menjadi {$statusLabel}.");
    }

    public function orgDestroy($id)
    {
        $org = Organization::withCount(['users', 'warehouses', 'children'])->findOrFail($id);

        if ($org->users_count > 0 || $org->children_count > 0) {
            return back()->with('error', "Unit Kerja [{$org->code}] tidak dapat dihapus permanen karena masih memiliki {$org->users_count} user atau {$org->children_count} sub-unit terhubung. Anda dapat menonaktifkan statusnya.");
        }

        $code = $org->code;
        $org->delete();

        return back()->with('success', "Unit Kerja [{$code}] berhasil dihapus.");
    }

    // Warehouses CRUD
    public function warehouseStore(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'code' => 'required|string|max:20|unique:warehouses,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:CENTRAL_LOGISTICS,BRANCH_STORAGE',
            'address' => 'nullable|string|max:500',
        ]);

        $warehouse = Warehouse::create([
            'organization_id' => $validated['organization_id'],
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'address' => $validated['address'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('master.organizations', ['tab' => 'warehouses'])->with('success', "Gudang [{$warehouse->code}] {$warehouse->name} berhasil ditambahkan.");
    }

    public function warehouseUpdate(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'code' => 'required|string|max:20|unique:warehouses,code,'.$warehouse->id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:CENTRAL_LOGISTICS,BRANCH_STORAGE',
            'address' => 'nullable|string|max:500',
            'is_active' => 'required|boolean',
        ]);

        $warehouse->update([
            'organization_id' => $validated['organization_id'],
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'address' => $validated['address'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('master.organizations', ['tab' => 'warehouses'])->with('success', "Data Gudang [{$warehouse->code}] berhasil diperbarui.");
    }

    public function warehouseToggleStatus($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->is_active = ! $warehouse->is_active;
        $warehouse->save();

        $statusLabel = $warehouse->is_active ? 'Diaktifkan' : 'Dinonaktifkan';

        return redirect()->route('master.organizations', ['tab' => 'warehouses'])->with('success', "Status Gudang [{$warehouse->code}] berhasil diubah menjadi {$statusLabel}.");
    }

    public function warehouseDestroy($id)
    {
        $warehouse = Warehouse::withCount(['stockBalances'])->findOrFail($id);

        if ($warehouse->stock_balances_count > 0) {
            return redirect()->route('master.organizations', ['tab' => 'warehouses'])->with('error', "Gudang [{$warehouse->code}] tidak dapat dihapus karena memiliki riwayat atau saldo barang persediaan. Anda dapat menonaktifkan statusnya.");
        }

        $code = $warehouse->code;
        $warehouse->delete();

        return redirect()->route('master.organizations', ['tab' => 'warehouses'])->with('success', "Gudang [{$code}] berhasil dihapus.");
    }

    // Vendors & Couriers
    public function vendorsIndex(Request $request)
    {
        $tab = $request->query('tab');
        if (! $tab) {
            if ($request->has('courier_page') || $request->has('courier_search')) {
                $tab = 'couriers';
            } else {
                $tab = 'vendors';
            }
        }
        $vendorSearch = $request->query('vendor_search');
        $vendorRating = $request->query('rating');
        $courierSearch = $request->query('courier_search');
        $courierStatus = $request->query('courier_status');

        $vendorQuery = Vendor::withCount('purchaseOrders');
        if ($vendorSearch) {
            $vendorQuery->where(function ($q) use ($vendorSearch) {
                $q->where('name', 'like', "%{$vendorSearch}%")
                    ->orWhere('code', 'like', "%{$vendorSearch}%")
                    ->orWhere('payment_terms', 'like', "%{$vendorSearch}%")
                    ->orWhere('address', 'like', "%{$vendorSearch}%");
            });
        }
        if ($vendorRating && $vendorRating !== 'ALL') {
            if ($vendorRating === '4.5') {
                $vendorQuery->where('rating', '>=', 4.5);
            } elseif ($vendorRating === '4.0') {
                $vendorQuery->where('rating', '>=', 4.0);
            } elseif ($vendorRating === 'UNDER_4') {
                $vendorQuery->where('rating', '<', 4.0);
            }
        }

        $courierQuery = Courier::withCount('shipments');
        if ($courierSearch) {
            $courierQuery->where(function ($q) use ($courierSearch) {
                $q->where('name', 'like', "%{$courierSearch}%")
                    ->orWhere('code', 'like', "%{$courierSearch}%");
            });
        }
        if ($courierStatus) {
            if ($courierStatus === 'ACTIVE') {
                $courierQuery->where('is_active', true);
            } elseif ($courierStatus === 'INACTIVE') {
                $courierQuery->where('is_active', false);
            }
        }

        $allVendors = Vendor::all();
        $allCouriers = Courier::all();

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $vendors = $vendorQuery->orderBy('name')->paginate($perPage, ['*'], 'vendor_page')->withQueryString();
        $couriers = $courierQuery->orderBy('name')->paginate($perPage, ['*'], 'courier_page')->withQueryString();

        return view('master.vendors_couriers', compact(
            'vendors',
            'couriers',
            'allVendors',
            'allCouriers',
            'tab',
            'perPage',
            'vendorSearch',
            'vendorRating',
            'courierSearch',
            'courierStatus'
        ));
    }

    // Users & Access Control
    public function usersIndex(Request $request)
    {
        $search = $request->query('search');
        $role = $request->query('role');
        $orgId = $request->query('organization_id');
        $status = $request->query('status');

        $query = User::with(['organization', 'warehouse']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role && $role !== 'ALL') {
            $query->where('role', $role);
        }

        if ($orgId && $orgId !== 'ALL') {
            $query->where('organization_id', $orgId);
        }

        if ($status) {
            if ($status === 'ACTIVE') {
                $query->where('is_active', true);
            } elseif ($status === 'INACTIVE') {
                $query->where('is_active', false);
            }
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $users = $query->orderBy('name')->paginate($perPage)->withQueryString();
        $organizations = Organization::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        $totalUsersCount = User::count();
        $activeUsersCount = User::where('is_active', true)->count();
        $approverCount = User::where(function ($q) {
            $q->where('role', 'like', '%APPROVER%')
                ->orWhere('role', 'like', '%MANAGEMENT%');
        })->count();
        $roles = User::select('role')->distinct()->pluck('role');

        return view('master.users', compact(
            'users',
            'organizations',
            'warehouses',
            'perPage',
            'search',
            'role',
            'orgId',
            'status',
            'totalUsersCount',
            'activeUsersCount',
            'approverCount',
            'roles'
        ));
    }

    // Budgets
    public function budgetIndex(Request $request)
    {
        $currentYear = (int) date('Y');
        $search = $request->query('search');
        $orgId = $request->query('organization_id');

        $query = Budget::with('organization')->where('year', $currentYear);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('cost_center_code', 'like', "%{$search}%")
                    ->orWhereHas('organization', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    });
            });
        }

        if ($orgId && $orgId !== 'ALL') {
            $query->where('organization_id', $orgId);
        }

        $allBudgets = Budget::where('year', $currentYear)->get();
        $totalAllocated = $allBudgets->sum('allocated_amount');
        $totalCommitted = $allBudgets->sum('committed_amount');
        $totalRealized = $allBudgets->sum('realized_amount');
        $totalAvailable = $allBudgets->sum('available_amount');
        $avgUtilization = $totalAllocated > 0 ? round((($totalCommitted + $totalRealized) / $totalAllocated) * 100, 1) : 0;

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $budgets = $query->orderBy('cost_center_code')->paginate($perPage)->withQueryString();
        $organizations = Organization::orderBy('name')->get();

        return view('master.budgets', compact(
            'budgets',
            'organizations',
            'currentYear',
            'perPage',
            'search',
            'orgId',
            'totalAllocated',
            'totalCommitted',
            'totalRealized',
            'totalAvailable',
            'avgUtilization'
        ));
    }

    public function budgetStore(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'cost_center_code' => 'required|string|max:50',
            'year' => 'required|integer|min:2020|max:2099',
            'allocated_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $existing = Budget::where('organization_id', $validated['organization_id'])
            ->where('year', $validated['year'])
            ->first();

        if ($existing) {
            return back()->with('error', "Pagu anggaran untuk unit kerja tersebut pada Tahun {$validated['year']} sudah terdaftar.");
        }

        $validated['committed_amount'] = 0;
        $validated['realized_amount'] = 0;

        $budget = Budget::create($validated);

        return back()->with('success', "Alokasi Pagu Anggaran {$budget->cost_center_code} (T.A. {$budget->year}) berhasil ditambahkan.");
    }

    public function vendorStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:vendors,code',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'sla_days' => 'required|integer|min:1',
            'payment_terms' => 'required|string|max:100',
            'rating' => 'nullable|numeric|min:1|max:5',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = true;
        $validated['rating'] = $validated['rating'] ?? 5.00;

        $vendor = Vendor::create($validated);

        return redirect()->route('master.vendors', ['tab' => 'vendors'])->with('success', "Vendor Rekanan [{$vendor->code}] {$vendor->name} berhasil ditambahkan.");
    }

    public function courierStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:couriers,code',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'sla_days' => 'required|integer|min:1',
            'service_types' => 'nullable|array',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = true;
        if (empty($validated['service_types'])) {
            $validated['service_types'] = ['REGULER', 'EXPRESS'];
        }

        $courier = Courier::create($validated);

        return redirect()->route('master.vendors', ['tab' => 'couriers'])->with('success', "Mitra Ekspedisi [{$courier->code}] {$courier->name} berhasil ditambahkan.");
    }

    public function userStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'required|string|max:50|unique:users,nip',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => 'required|string',
            'organization_id' => 'nullable|exists:organizations,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'approval_limit' => 'nullable|numeric|min:0',
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
        ]);

        $validated['password'] = bcrypt($validated['password'] ?? 'password123');
        $validated['is_active'] = true;
        $validated['approval_limit'] = $validated['approval_limit'] ?? 0;

        $user = User::create($validated);

        return back()->with('success', "Pengguna Baru {$user->name} ({$user->nip}) berhasil didaftarkan.");
    }

    public function userUpdate(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'required|string|max:50|unique:users,nip,'.$user->id,
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'role' => 'required|string',
            'organization_id' => 'nullable|exists:organizations,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'approval_limit' => 'nullable|numeric|min:0',
            'phone' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
            'is_active' => 'nullable|boolean',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : $user->is_active;
        $validated['approval_limit'] = $validated['approval_limit'] ?? 0;

        $user->update($validated);

        return redirect()->route('master.users')->with('success', "Data Pengguna {$user->name} ({$user->nip}) berhasil diperbarui.");
    }

    public function userDestroy($id)
    {
        $user = User::findOrFail($id);

        if (Auth::id() === $user->id) {
            return redirect()->route('master.users')->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        try {
            $name = $user->name;
            $nip = $user->nip;
            $user->delete();

            return redirect()->route('master.users')->with('success', "Pengguna {$name} ({$nip}) berhasil dihapus.");
        } catch (\Throwable $e) {
            return redirect()->route('master.users')->with('error', 'Pengguna tidak dapat dihapus karena memiliki riwayat transaksi/aktivitas terkait.');
        }
    }

    // Notifications
    public function notificationsIndex(Request $request)
    {
        $user = Auth::user();
        $notifPerPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $tab = $request->get('tab', 'all');

        $baseQuery = Notification::forUser($user);

        $totalCount = (clone $baseQuery)->count();
        $actionRequiredCount = (clone $baseQuery)->where('type', 'ACTION_REQUIRED')->count();
        $alertCount = (clone $baseQuery)->where('type', 'ALERT')->count();
        $infoCount = (clone $baseQuery)->where('type', 'INFORMATION')->count();
        $unreadCount = (clone $baseQuery)->where('is_read', false)->count();

        $query = clone $baseQuery;
        if ($tab === 'action_required') {
            $query->where('type', 'ACTION_REQUIRED');
        } elseif ($tab === 'alert') {
            $query->where('type', 'ALERT');
        } elseif ($tab === 'info') {
            $query->where('type', 'INFORMATION');
        } elseif ($tab === 'unread') {
            $query->where('is_read', false);
        }

        $notifications = $query->latest()->paginate($notifPerPage)->withQueryString();

        return view('notifications.index', compact(
            'notifications',
            'tab',
            'totalCount',
            'actionRequiredCount',
            'alertCount',
            'infoCount',
            'unreadCount'
        ));
    }

    public function markNotificationAsRead(Request $request, $id)
    {
        $user = Auth::user();
        $notification = Notification::forUser($user)->findOrFail($id);
        $notification->update(['is_read' => true, 'read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Notifikasi berhasil ditandai sudah dibaca.');
    }

    public function markAllNotificationsAsRead(Request $request)
    {
        $user = Auth::user();
        Notification::unreadForUser($user)->update(['is_read' => true, 'read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Semua notifikasi berhasil ditandai sudah dibaca.');
    }

    public function openNotification($id)
    {
        $user = Auth::user();
        $notification = Notification::forUser($user)->findOrFail($id);
        if (! $notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }

        return redirect($notification->action_url ?: route('notifications.index'));
    }

    // Audit Trail
    public function auditTrailIndex(Request $request)
    {
        $logsPerPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $logs = AuditLog::with(['user', 'organization'])->latest()->paginate($logsPerPage)->withQueryString();

        return view('audit.index', compact('logs'));
    }

    // Accounting Master: Chart of Accounts (CoA) & Cost Centers
    public function accountingIndex(Request $request)
    {
        $tab = $request->get('tab');
        if (! $tab) {
            if ($request->has('cc_page') || $request->has('cc_search') || $request->has('cc_org_id') || $request->has('cc_status')) {
                $tab = 'cost_centers';
            } else {
                $tab = 'coa';
            }
        }

        // CoA Filters
        $coaSearch = $request->get('coa_search');
        $coaType = $request->get('coa_type');
        $coaStatus = $request->get('coa_status');

        $coaQuery = ChartOfAccount::query();

        if ($coaSearch) {
            $coaQuery->where(function ($q) use ($coaSearch) {
                $q->where('account_code', 'like', "%{$coaSearch}%")
                    ->orWhere('account_name', 'like', "%{$coaSearch}%")
                    ->orWhere('classification', 'like', "%{$coaSearch}%")
                    ->orWhere('description', 'like', "%{$coaSearch}%");
            });
        }

        if ($coaType && $coaType !== 'ALL') {
            $coaQuery->where('account_type', $coaType);
        }

        if ($coaStatus && $coaStatus !== 'ALL') {
            $coaQuery->where('is_active', $coaStatus === 'ACTIVE');
        }

        $coaPerPage = in_array((int) $request->get('coa_per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('coa_per_page') : 10;
        $coas = $coaQuery->orderBy('account_code')->paginate($coaPerPage, ['*'], 'coa_page')->withQueryString();

        // Cost Center Filters
        $ccSearch = $request->get('cc_search');
        $ccOrgId = $request->get('cc_org_id');
        $ccStatus = $request->get('cc_status');

        $ccQuery = CostCenter::with('organization');

        if ($ccSearch) {
            $ccQuery->where(function ($q) use ($ccSearch) {
                $q->where('code', 'like', "%{$ccSearch}%")
                    ->orWhere('name', 'like', "%{$ccSearch}%")
                    ->orWhere('department', 'like', "%{$ccSearch}%")
                    ->orWhere('pic_name', 'like', "%{$ccSearch}%")
                    ->orWhereHas('organization', fn ($oq) => $oq->where('name', 'like', "%{$ccSearch}%")->orWhere('code', 'like', "%{$ccSearch}%"));
            });
        }

        if ($ccOrgId && $ccOrgId !== 'ALL') {
            $ccQuery->where('organization_id', $ccOrgId);
        }

        if ($ccStatus && $ccStatus !== 'ALL') {
            $ccQuery->where('is_active', $ccStatus === 'ACTIVE');
        }

        $ccPerPage = in_array((int) $request->get('cc_per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('cc_per_page') : 10;
        $costCenters = $ccQuery->orderBy('code')->paginate($ccPerPage, ['*'], 'cc_page')->withQueryString();

        // Summary KPI Counts
        $totalCoa = ChartOfAccount::count();
        $totalAssetCoa = ChartOfAccount::where('account_type', 'ASSET')->count();
        $totalExpenseCoa = ChartOfAccount::where('account_type', 'EXPENSE')->count();
        $totalCostCenter = CostCenter::count();

        $organizations = Organization::where('is_active', true)->orderBy('name')->get();

        return view('master.accounting', compact(
            'tab',
            'coas',
            'costCenters',
            'totalCoa',
            'totalAssetCoa',
            'totalExpenseCoa',
            'totalCostCenter',
            'organizations',
            'coaSearch',
            'coaType',
            'coaStatus',
            'coaPerPage',
            'ccSearch',
            'ccOrgId',
            'ccStatus',
            'ccPerPage'
        ));
    }

    public function coaStore(Request $request)
    {
        $validated = $request->validate([
            'account_code' => 'required|string|max:50|unique:chart_of_accounts,account_code',
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE',
            'normal_balance' => 'required|in:DEBIT,CREDIT',
            'classification' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : true;

        $coa = ChartOfAccount::create($validated);

        return redirect()->route('master.accounting', ['tab' => 'coa'])
            ->with('success', "Rekening Akun GL [{$coa->account_code}] {$coa->account_name} berhasil ditambahkan.");
    }

    public function coaUpdate(Request $request, $id)
    {
        $coa = ChartOfAccount::findOrFail($id);

        $validated = $request->validate([
            'account_code' => 'required|string|max:50|unique:chart_of_accounts,account_code,'.$coa->id,
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE',
            'normal_balance' => 'required|in:DEBIT,CREDIT',
            'classification' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $coa->update($validated);

        return redirect()->route('master.accounting', ['tab' => 'coa'])
            ->with('success', "Rekening Akun GL [{$coa->account_code}] berhasil diperbarui.");
    }

    public function coaDestroy($id)
    {
        $coa = ChartOfAccount::findOrFail($id);
        $code = $coa->account_code;
        $name = $coa->account_name;
        $coa->delete();

        return redirect()->route('master.accounting', ['tab' => 'coa'])
            ->with('success', "Rekening Akun GL [{$code}] {$name} berhasil dihapus.");
    }

    public function coaToggleStatus($id)
    {
        $coa = ChartOfAccount::findOrFail($id);
        $coa->is_active = ! $coa->is_active;
        $coa->save();

        $statusText = $coa->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('master.accounting', ['tab' => 'coa'])
            ->with('success', "Status Rekening GL [{$coa->account_code}] berhasil {$statusText}.");
    }

    public function costCenterStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:cost_centers,code',
            'name' => 'required|string|max:255',
            'organization_id' => 'nullable|exists:organizations,id',
            'department' => 'nullable|string|max:100',
            'pic_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->has('is_active') ? (bool) $request->input('is_active') : true;

        $costCenter = CostCenter::create($validated);

        return redirect()->route('master.accounting', ['tab' => 'cost_centers'])
            ->with('success', "Cost Center [{$costCenter->code}] {$costCenter->name} berhasil ditambahkan.");
    }

    public function costCenterUpdate(Request $request, $id)
    {
        $costCenter = CostCenter::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:cost_centers,code,'.$costCenter->id,
            'name' => 'required|string|max:255',
            'organization_id' => 'nullable|exists:organizations,id',
            'department' => 'nullable|string|max:100',
            'pic_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $costCenter->update($validated);

        return redirect()->route('master.accounting', ['tab' => 'cost_centers'])
            ->with('success', "Cost Center [{$costCenter->code}] berhasil diperbarui.");
    }

    public function costCenterDestroy($id)
    {
        $costCenter = CostCenter::findOrFail($id);
        $code = $costCenter->code;
        $name = $costCenter->name;
        $costCenter->delete();

        return redirect()->route('master.accounting', ['tab' => 'cost_centers'])
            ->with('success', "Cost Center [{$code}] {$name} berhasil dihapus.");
    }

    public function costCenterToggleStatus($id)
    {
        $costCenter = CostCenter::findOrFail($id);
        $costCenter->is_active = ! $costCenter->is_active;
        $costCenter->save();

        $statusText = $costCenter->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('master.accounting', ['tab' => 'cost_centers'])
            ->with('success', "Status Cost Center [{$costCenter->code}] berhasil {$statusText}.");
    }
}
