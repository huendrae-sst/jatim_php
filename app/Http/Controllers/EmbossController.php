<?php

namespace App\Http\Controllers;

use App\Models\EmbossFile;
use App\Models\EmbossRecord;
use App\Models\Item;
use App\Models\Organization;
use App\Services\EmbossIntegrationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmbossController extends Controller
{
    public function __construct(
        protected EmbossIntegrationService $embossService
    ) {}

    public function index(Request $request)
    {
        $status = $request->get('status');
        $source = $request->get('source');
        $search = $request->get('search');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;

        $query = EmbossFile::with('uploader');

        if ($status && $status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($source && $source !== 'ALL') {
            $query->where('source', $source);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('file_id', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $files = $query->latest()->paginate($perPage)->withQueryString();

        // Summary metrics
        $metrics = [
            'total_files' => EmbossFile::count(),
            'total_records' => (int) EmbossFile::sum('total_records'),
            'total_success' => (int) EmbossFile::sum('success_records'),
            'total_reject' => (int) EmbossFile::sum('reject_records'),
            'total_duplicate' => (int) EmbossFile::sum('duplicate_records'),
        ];

        return view('emboss.index', compact('files', 'metrics', 'status', 'source', 'search', 'perPage'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'emboss_file' => 'required|file|max:51200', // 50MB max (POC-24)
            'source' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ], [
            'emboss_file.required' => 'Wajib memilih berkas emboss untuk diunggah.',
            'emboss_file.max' => 'Ukuran berkas melebihi batas maksimal 50 MB.',
            'source.required' => 'Pilih sistem sumber data emboss.',
        ]);

        try {
            $file = $request->file('emboss_file');
            $embossFile = $this->embossService->importFile(
                $file,
                $request->source,
                Auth::user(),
                $request->notes
            );

            return redirect()->route('emboss.show', $embossFile->id)
                ->with('success', "Berkas {$embossFile->file_name} berhasil diproses: {$embossFile->success_records} record valid, {$embossFile->reject_records} reject, {$embossFile->duplicate_records} duplikat (Durasi: {$embossFile->duration_seconds} detik).");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, $id)
    {
        $file = EmbossFile::with(['uploader', 'orders'])->findOrFail($id);
        $tab = $request->get('tab', 'records');

        // Valid & Processed records with pagination
        $recordsQuery = EmbossRecord::with(['organization', 'item', 'order'])
            ->where('emboss_file_id', $file->id);

        $filterStatus = $request->get('record_status');
        if ($filterStatus && $filterStatus !== 'ALL') {
            $recordsQuery->where('status', $filterStatus);
        }

        $records = $recordsQuery->paginate(15, ['*'], 'records_page')->withQueryString();

        // Grouping summary (POC-29)
        $groupingSummary = EmbossRecord::with(['organization', 'item'])
            ->where('emboss_file_id', $file->id)
            ->whereIn('status', ['VALID', 'PROCESSED_TO_ORDER'])
            ->select('organization_id', 'card_type', 'product_code')
            ->selectRaw('COUNT(*) as card_count, SUM(total_price) as estimated_val')
            ->groupBy('organization_id', 'card_type', 'product_code')
            ->get();

        return view('emboss.show', compact('file', 'records', 'groupingSummary', 'tab', 'filterStatus'));
    }

    public function rejectQueue(Request $request, $id)
    {
        $file = EmbossFile::findOrFail($id);
        $search = $request->get('search');

        $query = EmbossRecord::where('emboss_file_id', $file->id)
            ->where('status', 'INVALID');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('external_reference_id', 'like', "%{$search}%")
                    ->orWhere('branch_code', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%")
                    ->orWhere('cardholder_name', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%");
            });
        }

        $rejects = $query->paginate(15)->withQueryString();

        $organizations = Organization::where('is_active', true)->orderBy('code')->get();
        $items = Item::where('is_active', true)->orderBy('sku')->get();

        return view('emboss.reject_queue', compact('file', 'rejects', 'organizations', 'items', 'search'));
    }

    public function updateRecord(Request $request, $id)
    {
        $record = EmbossRecord::findOrFail($id);

        $request->validate([
            'branch_code' => 'required|string',
            'product_code' => 'required|string',
            'cardholder_name' => 'nullable|string',
            'qty' => 'required|integer|min:1',
        ]);

        try {
            $this->embossService->reprocessRecord($record, $request->all(), Auth::user());

            if ($record->status === 'VALID') {
                return back()->with('success', "Record {$record->external_reference_id} berhasil diperbaiki dan dipindahkan ke antrean VALID.");
            }

            return back()->with('warning', "Record {$record->external_reference_id} masih memiliki kesalahan validasi: {$record->error_message}");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal memproses perbaikan record: '.$e->getMessage());
        }
    }

    public function generateOrders(Request $request, $id)
    {
        $file = EmbossFile::findOrFail($id);

        $request->validate([
            'delivery_method' => 'required|in:COURIER,PICKUP_KP',
            'pickup_nip' => 'nullable|required_if:delivery_method,PICKUP_KP|string',
            'pickup_name' => 'nullable|required_if:delivery_method,PICKUP_KP|string',
            'pickup_position' => 'nullable|required_if:delivery_method,PICKUP_KP|string',
            'pickup_notes' => 'nullable|string',
        ], [
            'pickup_nip.required_if' => 'Wajib mengisi NIP PIC pengambil jika metode pengiriman Ambil di Kantor Pusat.',
            'pickup_name.required_if' => 'Wajib mengisi Nama Lengkap PIC pengambil jika metode pengiriman Ambil di Kantor Pusat.',
            'pickup_position.required_if' => 'Wajib mengisi Jabatan PIC pengambil jika metode pengiriman Ambil di Kantor Pusat.',
        ]);

        try {
            $pickupData = [
                'nip' => $request->pickup_nip,
                'name' => $request->pickup_name,
                'position' => $request->pickup_position,
                'notes' => $request->pickup_notes,
            ];

            $orders = $this->embossService->generateOrdersFromEmboss(
                $file,
                Auth::user(),
                $request->delivery_method,
                $pickupData
            );

            $orderNumbers = $orders->pluck('order_number')->implode(', ');

            return redirect()->route('orders.index')
                ->with('success', "Berhasil membuat {$orders->count()} order persediaan cabang dari berkas {$file->file_id} (No. Order: {$orderNumbers}).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function downloadTemplate(): StreamedResponse
    {
        $fileName = 'sample_emboss_template_bank_jatim.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, [
                'reference_id',
                'branch_code',
                'product_code',
                'card_type',
                'cardholder_name',
                'card_number',
                'cif_number',
                'account_number',
                'qty',
                'procurement_id',
            ]);

            // Sample rows: valid ATM instant, valid ATM named, token, KUE, and sample error
            $sampleData = [
                ['REF-EMB-00001', 'KC-SBY', 'ATM-INST-001', 'INSTANT', 'NASABAH INSTAN GPN', '6013021122334401', 'CIF10001', '0011223301', '1', 'PO/2026/001'],
                ['REF-EMB-00002', 'KC-SBY', 'ATM-NAME-001', 'NAMED', 'BAMBANG HERMANTO', '6013021122334402', 'CIF10002', '0011223302', '1', 'PO/2026/001'],
                ['REF-EMB-00003', 'KC-MLG', 'ATM-INST-001', 'INSTANT', 'NASABAH INSTAN GPN', '6013021122334403', 'CIF10003', '0011223303', '1', 'PO/2026/001'],
                ['REF-EMB-00004', 'KC-MLG', 'TKN-HRD-001', 'TOKEN', 'PT MAJU JAYA BERSAMA', 'DIGIPASS-77001', 'CIF10004', '0011223304', '1', 'PO/2026/002'],
                ['REF-EMB-00005', 'KC-KDR', 'KUE-FLZ-001', 'KUE', 'KARTU UANG ELEKTRONIK', '6013021122334405', 'CIF10005', '0011223305', '1', 'PO/2026/003'],
                // Sample invalid rows for testing Reject Queue
                ['REF-EMB-00006', 'KC-INVALID', 'ATM-INST-001', 'INSTANT', 'KARTU CABANG SALAH', '6013021122334406', 'CIF10006', '0011223306', '1', 'PO/2026/001'],
                ['REF-EMB-00007', 'KC-SBY', 'SKU-TIDAK-ADA', 'INSTANT', 'KARTU SKU SALAH', '6013021122334407', 'CIF10007', '0011223307', '1', 'PO/2026/001'],
            ];

            foreach ($sampleData as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function update(Request $request, $id)
    {
        $file = EmbossFile::findOrFail($id);
        $request->validate([
            'notes' => 'nullable|string|max:500',
            'source' => 'nullable|string|max:100',
        ]);

        $file->update($request->only(['notes', 'source']));

        return redirect()->route('emboss.index')->with('success', "Berkas Emboss {$file->file_id} berhasil diperbarui.");
    }

    public function destroy($id)
    {
        $file = EmbossFile::findOrFail($id);
        $fileId = $file->file_id;

        $file->records()->delete();
        $file->delete();

        return redirect()->route('emboss.index')->with('success', "Berkas Emboss {$fileId} berhasil dihapus.");
    }
}
