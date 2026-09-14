<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\EmbossFile;
use App\Models\EmbossRecord;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmbossIntegrationService
{
    /**
     * Import and process an emboss batch file (POC-20, POC-21, POC-22, POC-23, POC-24, POC-26, POC-27).
     *
     * @throws Exception
     */
    public function importFile(UploadedFile $file, string $source, User $uploader, ?string $notes = null): EmbossFile
    {
        $startTime = microtime(true);
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Level 1 Idempotency: Check if file with identical content hash was already processed (POC-23)
        $existingFile = EmbossFile::where('file_hash', $fileHash)->first();
        if ($existingFile) {
            throw new Exception("Duplikasi berkas terdeteksi! Berkas identik telah diproses sebelumnya dengan File ID: {$existingFile->file_id} ({$existingFile->file_name}) pada {$existingFile->created_at->format('d/m/Y H:i')}.");
        }

        $fileSeq = EmbossFile::count() + 1;
        $fileId = 'EMB/'.date('Ym').'/'.sprintf('%04d', $fileSeq);
        $fileName = $file->getClientOriginalName();
        $storedPath = $file->storeAs('emboss_uploads', $fileId.'_'.time().'.csv', 'local');

        $embossFile = EmbossFile::create([
            'file_id' => $fileId,
            'file_name' => $fileName,
            'file_hash' => $fileHash,
            'source' => $source,
            'status' => 'PROCESSING',
            'started_at' => now(),
            'file_path' => $storedPath,
            'uploaded_by_user_id' => $uploader->id,
            'notes' => $notes,
        ]);

        // Preload master data to memory for high-performance large batch processing (POC-24)
        $organizations = Organization::all()->keyBy('code');
        $items = Item::all()->keyBy('sku');

        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            $embossFile->update(['status' => 'FAILED']);
            throw new Exception("Gagal membuka berkas unggahan: {$fileName}");
        }

        // Detect delimiter (comma or semicolon)
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        // Normalize header
        $headerMap = [];
        if ($header) {
            foreach ($header as $idx => $col) {
                $cleanCol = strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $col)));
                $headerMap[$cleanCol] = $idx;
            }
        }

        $totalRecords = 0;
        $successRecords = 0;
        $rejectRecords = 0;
        $duplicateRecords = 0;

        $batchRecords = [];
        $seenRefsInBatch = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                // Skip empty lines
                if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
                    continue;
                }

                $totalRecords++;

                // Map fields by header or position
                $refId = trim($this->getValue($row, $headerMap, ['reference_id', 'ref_id', 'id'], 0));
                $branchCode = trim($this->getValue($row, $headerMap, ['branch_code', 'cabang', 'branch'], 1));
                $productCode = trim($this->getValue($row, $headerMap, ['product_code', 'sku', 'item_code'], 2));
                $cardType = strtoupper(trim($this->getValue($row, $headerMap, ['card_type', 'tipe_kartu', 'type'], 3) ?: 'INSTANT'));
                $cardholderName = trim($this->getValue($row, $headerMap, ['cardholder_name', 'nama_nasabah', 'name'], 4));
                $cardNumber = trim($this->getValue($row, $headerMap, ['card_number', 'pan', 'nomor_kartu'], 5));
                $cifNumber = trim($this->getValue($row, $headerMap, ['cif_number', 'cif', 'no_cif'], 6));
                $accountNumber = trim($this->getValue($row, $headerMap, ['account_number', 'rekening', 'no_rek'], 7));
                $qtyRaw = $this->getValue($row, $headerMap, ['qty', 'jumlah', 'quantity'], 8);
                $qty = is_numeric($qtyRaw) && (int) $qtyRaw > 0 ? (int) $qtyRaw : 1;
                $procurementId = trim($this->getValue($row, $headerMap, ['procurement_id', 'po_number', 'po_id'], 9));

                // Fallback reference ID if missing
                if (! $refId) {
                    $refId = 'REF-'.$fileId.'-'.sprintf('%05d', $totalRecords);
                }

                $status = 'VALID';
                $errorMessage = null;
                $unitPrice = 0.0;
                $totalPrice = 0.0;

                // 1. Check blank mandatory fields (POC-22)
                if (! $branchCode) {
                    $status = 'INVALID';
                    $errorMessage = 'Kode cabang (branch_code) kosong atau tidak terbaca.';
                } elseif (! $productCode) {
                    $status = 'INVALID';
                    $errorMessage = 'Kode produk/SKU (product_code) kosong atau tidak terbaca.';
                } elseif ($cardType === 'NAMED' && ! $cardholderName) {
                    $status = 'INVALID';
                    $errorMessage = 'Nama pemegang kartu wajib diisi untuk jenis kartu NAMED.';
                }

                // 2. Validate Branch Mapping (POC-21, POC-22)
                $org = null;
                if ($status === 'VALID') {
                    $org = $organizations->get($branchCode);
                    if (! $org) {
                        $status = 'INVALID';
                        $errorMessage = "Kode cabang '{$branchCode}' tidak ditemukan di master data organisasi Bank Jatim.";
                    }
                }

                // 3. Validate Product SKU & Price Enrichment (POC-21, POC-27)
                $item = null;
                if ($status === 'VALID') {
                    $item = $items->get($productCode);
                    if (! $item) {
                        $status = 'INVALID';
                        $errorMessage = "SKU barang '{$productCode}' tidak ditemukan di katalog master barang persediaan.";
                    } else {
                        // POC-27: Procurement price enrichment
                        $unitPrice = (float) $item->estimated_unit_price;
                        if ($unitPrice <= 0) {
                            $status = 'INVALID';
                            $errorMessage = "Harga pengadaan aktif tidak ditemukan untuk produk '{$productCode}' (missing procurement price).";
                        } else {
                            $totalPrice = $unitPrice * $qty;
                        }
                    }
                }

                // 4. Level 2 Idempotency: Duplicate Reference Check (POC-23)
                if ($status === 'VALID') {
                    if (isset($seenRefsInBatch[$refId])) {
                        $status = 'DUPLICATE';
                        $errorMessage = "Duplikat internal: Reference ID '{$refId}' terulang dalam berkas yang sama pada baris {$seenRefsInBatch[$refId]}.";
                    } else {
                        $existingRecord = EmbossRecord::where('external_reference_id', $refId)
                            ->whereIn('status', ['VALID', 'PROCESSED_TO_ORDER'])
                            ->first();

                        if ($existingRecord) {
                            $status = 'DUPLICATE';
                            $errorMessage = "Duplikat sistem: Reference ID '{$refId}' sudah pernah diproses pada berkas {$existingRecord->embossFile?->file_id} ({$existingRecord->created_at->format('d/m/Y')}).";
                        }
                    }
                }

                $seenRefsInBatch[$refId] = $totalRecords;

                // Tally counts
                if ($status === 'VALID') {
                    $successRecords++;
                } elseif ($status === 'DUPLICATE') {
                    $duplicateRecords++;
                } else {
                    $rejectRecords++;
                }

                $batchRecords[] = [
                    'emboss_file_id' => $embossFile->id,
                    'external_reference_id' => $refId,
                    'branch_code' => $branchCode,
                    'organization_id' => $org?->id,
                    'product_code' => $productCode,
                    'item_id' => $item?->id,
                    'card_type' => $cardType,
                    'cardholder_name' => $cardholderName,
                    'card_number' => $cardNumber,
                    'cif_number' => $cifNumber,
                    'account_number' => $accountNumber,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'status' => $status,
                    'error_message' => $errorMessage,
                    'procurement_id' => $procurementId ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Chunked DB insert for high volume batch processing (POC-24)
                if (count($batchRecords) >= 500) {
                    EmbossRecord::insert($batchRecords);
                    $batchRecords = [];
                }
            }

            if (! empty($batchRecords)) {
                EmbossRecord::insert($batchRecords);
            }

            fclose($handle);

            $endTime = microtime(true);
            $durationSeconds = round($endTime - $startTime, 2);

            $finalStatus = 'COMPLETED';
            if ($totalRecords > 0 && $successRecords === 0) {
                $finalStatus = 'FAILED';
            } elseif ($rejectRecords > 0 || $duplicateRecords > 0) {
                $finalStatus = 'PARTIAL_SUCCESS';
            }

            $embossFile->update([
                'total_records' => $totalRecords,
                'success_records' => $successRecords,
                'reject_records' => $rejectRecords,
                'duplicate_records' => $duplicateRecords,
                'status' => $finalStatus,
                'completed_at' => now(),
                'duration_seconds' => $durationSeconds,
            ]);

            // Audit Trail Logging (POC-26, POC-54)
            AuditTrailService::log('IMPORT_EMBOSS_BATCH', $embossFile, null, [
                'file_id' => $embossFile->file_id,
                'file_name' => $embossFile->file_name,
                'source' => $source,
                'total' => $totalRecords,
                'success' => $successRecords,
                'reject' => $rejectRecords,
                'duplicate' => $duplicateRecords,
                'duration_seconds' => $durationSeconds,
            ], $uploader);

            DB::commit();

            return $embossFile;
        } catch (Exception $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            $embossFile->update(['status' => 'FAILED']);
            throw $e;
        }
    }

    /**
     * Reprocess a rejected record from the Reject Queue after correction (POC-22).
     */
    public function reprocessRecord(EmbossRecord $record, array $data, User $user): EmbossRecord
    {
        $branchCode = trim($data['branch_code'] ?? $record->branch_code);
        $productCode = trim($data['product_code'] ?? $record->product_code);
        $cardholderName = trim($data['cardholder_name'] ?? $record->cardholder_name);
        $qty = (int) ($data['qty'] ?? $record->qty);

        $org = Organization::where('code', $branchCode)->first();
        $item = Item::where('sku', $productCode)->first();

        $status = 'VALID';
        $errorMessage = null;
        $unitPrice = 0.0;
        $totalPrice = 0.0;

        if (! $org) {
            $status = 'INVALID';
            $errorMessage = "Kode cabang '{$branchCode}' tidak valid.";
        } elseif (! $item) {
            $status = 'INVALID';
            $errorMessage = "SKU barang '{$productCode}' tidak ditemukan di master data.";
        } else {
            $unitPrice = (float) $item->estimated_unit_price;
            if ($unitPrice <= 0) {
                $status = 'INVALID';
                $errorMessage = "Harga pengadaan tidak ditemukan untuk SKU '{$productCode}'.";
            } else {
                $totalPrice = $unitPrice * $qty;
            }
        }

        $oldStatus = $record->status;
        $record->update([
            'branch_code' => $branchCode,
            'organization_id' => $org?->id,
            'product_code' => $productCode,
            'item_id' => $item?->id,
            'cardholder_name' => $cardholderName,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        // If status changed from INVALID to VALID, update parent file statistics
        if ($oldStatus === 'INVALID' && $status === 'VALID') {
            $file = $record->embossFile;
            $file->increment('success_records');
            $file->decrement('reject_records');
            if ($file->reject_records === 0 && $file->duplicate_records === 0) {
                $file->update(['status' => 'COMPLETED']);
            }
        }

        AuditTrailService::log('REPROCESS_EMBOSS_RECORD', $record, null, [
            'record_id' => $record->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
        ], $user);

        return $record;
    }

    /**
     * Group valid emboss records into Inventory Orders (POC-28, POC-29).
     * Supports delivery methods: COURIER or PICKUP_KP (POC-28).
     *
     * @return Collection<int, Order>
     *
     * @throws Exception
     */
    public function generateOrdersFromEmboss(
        EmbossFile $file,
        User $user,
        string $deliveryMethod = 'COURIER',
        ?array $pickupData = null
    ): Collection {
        $validRecords = $file->validRecords()->with(['organization', 'item'])->get();

        if ($validRecords->isEmpty()) {
            throw new Exception("Tidak ada record valid yang dapat diproses menjadi Order pada berkas {$file->file_id}.");
        }

        // Validate pickup at KP fields if selected (POC-28)
        if ($deliveryMethod === 'PICKUP_KP') {
            if (empty($pickupData['nip']) || empty($pickupData['name']) || empty($pickupData['position'])) {
                throw new Exception('Pengambilan di Kantor Pusat (Ambil di KP) mewajibkan pengisian NIP, Nama Lengkap, dan Jabatan PIC Unit Peminta.');
            }
        }

        // Group valid records by Branch (organization_id) and Card Type / SKU (POC-29)
        $grouped = $validRecords->groupBy(fn ($rec) => $rec->organization_id.'_'.$rec->card_type);

        $createdOrders = collect();

        DB::transaction(function () use ($grouped, $file, $user, $deliveryMethod, $pickupData, &$createdOrders) {
            foreach ($grouped as $groupKey => $records) {
                $first = $records->first();
                $org = $first->organization;
                if (! $org) {
                    continue;
                }

                $totalGroupValue = $records->sum('total_price');
                $totalGroupQty = $records->sum('qty');
                $cardType = $first->card_type;

                // Check Branch Budget (POC-07, POC-08)
                $currentYear = (int) date('Y');
                $budget = Budget::where('organization_id', $org->id)->where('year', $currentYear)->first();
                $isOverbudget = false;
                $projectedUtilization = 0.0;

                if ($budget && $budget->allocated_amount > 0) {
                    $used = (float) ($budget->committed_amount + $budget->realized_amount);
                    $projectedUtilization = round((($used + $totalGroupValue) / (float) $budget->allocated_amount) * 100, 2);
                    $isOverbudget = $projectedUtilization > 100.0;
                }

                $orderSeq = Order::count() + 1;
                $orderNumber = 'ORD/'.date('Y/m').'/'.sprintf('%05d', $orderSeq);

                $order = Order::create([
                    'order_number' => $orderNumber,
                    'requesting_organization_id' => $org->id,
                    'created_by_user_id' => $user->id,
                    'status' => 'SUBMITTED',
                    'priority' => 'NORMAL',
                    'required_date' => now()->addDays(5)->toDateString(),
                    'notes' => "Order otomatis personalisasi {$cardType} dari berkas Emboss {$file->file_id} ({$totalGroupQty} kartu).",
                    'total_estimated_value' => $totalGroupValue,
                    'budget_id' => $budget?->id,
                    'is_overbudget' => $isOverbudget,
                    'projected_utilization' => $projectedUtilization,
                    'emboss_file_id' => $file->id,
                    'delivery_method' => $deliveryMethod,
                    'pickup_pic_nip' => $deliveryMethod === 'PICKUP_KP' ? ($pickupData['nip'] ?? null) : null,
                    'pickup_pic_name' => $deliveryMethod === 'PICKUP_KP' ? ($pickupData['name'] ?? null) : null,
                    'pickup_pic_position' => $deliveryMethod === 'PICKUP_KP' ? ($pickupData['position'] ?? null) : null,
                    'pickup_notes' => $deliveryMethod === 'PICKUP_KP' ? ($pickupData['notes'] ?? null) : null,
                ]);

                // Aggregate items by item_id
                $itemsAggregated = $records->groupBy('item_id');
                foreach ($itemsAggregated as $itemId => $itemRecords) {
                    $item = $itemRecords->first()->item;
                    $itemQty = $itemRecords->sum('qty');
                    $itemUnitPrice = (float) ($item?->estimated_unit_price ?? 0);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $itemId,
                        'qty_requested' => $itemQty,
                        'qty_approved' => 0,
                        'qty_allocated' => 0,
                        'qty_picked' => 0,
                        'qty_packed' => 0,
                        'qty_shipped' => 0,
                        'qty_received' => 0,
                        'unit_price' => $itemUnitPrice,
                        'subtotal' => $itemUnitPrice * $itemQty,
                    ]);
                }

                // Update records to PROCESSED_TO_ORDER and set order_id
                $recordIds = $records->pluck('id')->toArray();
                EmbossRecord::whereIn('id', $recordIds)->update([
                    'status' => 'PROCESSED_TO_ORDER',
                    'order_id' => $order->id,
                ]);

                AuditTrailService::log('GENERATE_ORDER_FROM_EMBOSS', $order, null, [
                    'order_number' => $order->order_number,
                    'emboss_file_id' => $file->id,
                    'card_type' => $cardType,
                    'card_count' => $totalGroupQty,
                    'delivery_method' => $deliveryMethod,
                ], $user);

                $createdOrders->push($order);
            }
        });

        return $createdOrders;
    }

    /**
     * Helper to read value from row by header name or fallback index.
     */
    protected function getValue(array $row, array $headerMap, array $possibleKeys, int $defaultIndex): ?string
    {
        foreach ($possibleKeys as $key) {
            if (isset($headerMap[$key]) && isset($row[$headerMap[$key]])) {
                return $row[$headerMap[$key]];
            }
        }

        return $row[$defaultIndex] ?? null;
    }
}
