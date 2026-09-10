<?php

namespace App\Http\Controllers;

use App\Services\EssReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EssReportController extends Controller
{
    public function __construct(
        protected EssReportService $essReportService
    ) {}

    /**
     * 1. Laporan Valuasi Aset & Realisasi Anggaran Persediaan
     */
    public function valuationBudget(Request $request)
    {
        $filters = [
            'period_year' => $request->get('period_year', date('Y')),
            'warehouse_id' => $request->get('warehouse_id', 'all'),
            'category_id' => $request->get('category_id', 'all'),
        ];

        $report = $this->essReportService->getValuationBudgetReport($filters);

        return view('ess.valuation_budget', $report);
    }

    public function exportValuationBudgetCsv(Request $request): StreamedResponse
    {
        $filters = [
            'period_year' => $request->get('period_year', date('Y')),
            'warehouse_id' => $request->get('warehouse_id', 'all'),
            'category_id' => $request->get('category_id', 'all'),
        ];

        $report = $this->essReportService->getValuationBudgetReport($filters);
        $filename = 'ESS_Valuasi_dan_Realisasi_Anggaran_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['KODE UNIT', 'NAMA UNIT KERJA / CABANG', 'TIPE', 'KOTA', 'PAGU ANGGARAN (RP)', 'REALISASI BELANJA (RP)', 'SISA ANGGARAN (RP)', 'PENYERAPAN (%)', 'VALUASI STOK ON-HAND (RP)', 'JUMLAH UNIT ON-HAND', 'TOTAL SKU']);

            foreach ($report['tableData'] as $row) {
                fputcsv($handle, [
                    $row['organization']->code,
                    $row['organization']->name,
                    $row['organization']->type,
                    $row['organization']->city ?? '-',
                    $row['allocated_amount'],
                    $row['realized_amount'],
                    $row['remaining_budget'],
                    $row['utilization_rate'].'%',
                    $row['stock_valuation'],
                    $row['on_hand_units'],
                    $row['sku_count'],
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 2. Laporan Efisiensi Biaya & Penghematan Switching
     */
    public function costSaving(Request $request)
    {
        $filters = [
            'period_year' => $request->get('period_year', date('Y')),
            'source_warehouse_id' => $request->get('source_warehouse_id', 'all'),
            'destination_warehouse_id' => $request->get('destination_warehouse_id', 'all'),
        ];

        $report = $this->essReportService->getCostSavingReport($filters);

        return view('ess.cost_saving', $report);
    }

    public function exportCostSavingCsv(Request $request): StreamedResponse
    {
        $filters = [
            'period_year' => $request->get('period_year', date('Y')),
            'source_warehouse_id' => $request->get('source_warehouse_id', 'all'),
            'destination_warehouse_id' => $request->get('destination_warehouse_id', 'all'),
        ];

        $report = $this->essReportService->getCostSavingReport($filters);
        $filename = 'ESS_Efisiensi_Biaya_Switching_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['NO. REFERENSI', 'TANGGAL', 'GUDANG ASAL (SUMBER)', 'GUDANG TUJUAN', 'STATUS', 'JUMLAH SKU', 'TOTAL KUANTITAS', 'PENGHEMATAN ANGGARAN (RP)', 'LEAD TIME (HARI)', 'BENCHMARK VENDOR (HARI)']);

            foreach ($report['tableData'] as $row) {
                $sw = $row['switching'];
                fputcsv($handle, [
                    $sw->reference_number,
                    $sw->created_at?->format('Y-m-d') ?? '-',
                    $sw->sourceWarehouse?->name ?? '-',
                    $sw->destinationWarehouse?->name ?? '-',
                    $sw->status,
                    $row['items_count'],
                    $row['units_count'],
                    $row['savings_amount'],
                    $row['duration_days'] ?? '-',
                    $row['vendor_lead_time_days'],
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 3. Laporan Perputaran Persediaan & Produktivitas Aset (ITO)
     */
    public function inventoryTurnover(Request $request)
    {
        $filters = [
            'category_id' => $request->get('category_id', 'all'),
            'warehouse_id' => $request->get('warehouse_id', 'all'),
        ];

        $report = $this->essReportService->getInventoryTurnoverReport($filters);

        return view('ess.inventory_turnover', $report);
    }

    public function exportInventoryTurnoverCsv(Request $request): StreamedResponse
    {
        $filters = [
            'category_id' => $request->get('category_id', 'all'),
            'warehouse_id' => $request->get('warehouse_id', 'all'),
        ];

        $report = $this->essReportService->getInventoryTurnoverReport($filters);
        $filename = 'ESS_Perputaran_Persediaan_ITO_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['SKU', 'NAMA BARANG', 'KATEGORI', 'SATUAN', 'HARGA SATUAN (RP)', 'ON HAND', 'NILAI STOK (RP)', 'PENGELUARAN 1 TAHUN (UNIT)', 'NILAI KELUAR / COGS (RP)', 'ITO (KALI/THN)', 'DAYS OF INVENTORY (HARI)', 'KATEGORI KECEPATAN']);

            foreach ($report['tableData'] as $row) {
                $it = $row['item'];
                fputcsv($handle, [
                    $it->sku,
                    $it->name,
                    $it->category?->name ?? '-',
                    $it->uom,
                    $it->estimated_unit_price,
                    $row['on_hand'],
                    $row['stock_valuation'],
                    $row['annual_qty_out'],
                    $row['annual_cogs'],
                    $row['ito'],
                    $row['doi'],
                    $row['velocity'],
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 4. Peta Risiko Ketahanan Logistik Jaringan
     */
    public function riskHeatmap(Request $request)
    {
        $filters = [
            'city' => $request->get('city', 'all'),
            'type' => $request->get('type', 'all'),
            'risk_status' => $request->get('risk_status', 'all'),
        ];

        $report = $this->essReportService->getRiskHeatmapReport($filters);

        return view('ess.risk_heatmap', $report);
    }

    public function exportRiskHeatmapCsv(Request $request): StreamedResponse
    {
        $filters = [
            'city' => $request->get('city', 'all'),
            'type' => $request->get('type', 'all'),
            'risk_status' => $request->get('risk_status', 'all'),
        ];

        $report = $this->essReportService->getRiskHeatmapReport($filters);
        $filename = 'ESS_Peta_Ketahanan_Logistik_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['KODE GUDANG', 'NAMA GUDANG / UNIT', 'TIPE', 'KOTA / WILAYAH', 'TOTAL SKU AKTIF', 'SKU AMAN', 'SKU REORDER (ROP)', 'SKU KRITIS / HABIS', 'INDEKS KETAHANAN (%)', 'STATUS RISIKO', 'VALUASI STOK (RP)']);

            foreach ($report['tableData'] as $row) {
                $wh = $row['warehouse'];
                fputcsv($handle, [
                    $wh->code,
                    $wh->name,
                    $wh->type,
                    $wh->organization?->city ?? '-',
                    $row['managed_skus'],
                    $row['safe_skus'],
                    $row['reorder_skus'],
                    $row['critical_skus'],
                    $row['resilience_score'].'%',
                    $row['risk_status'],
                    $row['total_valuation'],
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 5. Laporan Kinerja Layanan & SLA Distribusi
     */
    public function serviceLevel(Request $request)
    {
        $filters = [
            'period_year' => $request->get('period_year', date('Y')),
            'organization_id' => $request->get('organization_id', 'all'),
        ];

        $report = $this->essReportService->getServiceLevelReport($filters);

        return view('ess.service_level', $report);
    }

    public function exportServiceLevelCsv(Request $request): StreamedResponse
    {
        $filters = [
            'period_year' => $request->get('period_year', date('Y')),
            'organization_id' => $request->get('organization_id', 'all'),
        ];

        $report = $this->essReportService->getServiceLevelReport($filters);
        $filename = 'ESS_Kinerja_Layanan_SLA_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['NO. ORDER', 'CABANG PEMOHON', 'TANGGAL PENGAJUAN', 'TANGGAL TARGET (REQUIRED)', 'TANGGAL SELESAI', 'QTY DIMINTA', 'QTY TERPENUHI', 'FILL RATE (%)', 'DURASI LAYANAN (HARI)', 'KETEPATAN WAKTU (SLA)', 'STATUS ORDER']);

            foreach ($report['tableData'] as $row) {
                $order = $row['order'];
                fputcsv($handle, [
                    $order->order_number,
                    $order->requestingOrganization?->name ?? '-',
                    $order->submitted_at?->format('Y-m-d') ?? $order->created_at?->format('Y-m-d'),
                    $order->required_date?->format('Y-m-d') ?? '-',
                    $order->completed_at?->format('Y-m-d') ?? '-',
                    $row['total_requested'],
                    $row['total_fulfilled'],
                    $row['fill_rate'].'%',
                    $row['duration_days'] ?? '-',
                    $row['is_on_time'] ? 'ON TIME' : 'TERLAMBAT',
                    $row['status'],
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 6. Laporan Akuntabilitas, Kerugian Aset & Kepatuhan Audit
     */
    public function auditCompliance(Request $request)
    {
        $filters = [
            'period_year' => $request->get('period_year', date('Y')),
            'warehouse_id' => $request->get('warehouse_id', 'all'),
        ];

        $report = $this->essReportService->getAuditComplianceReport($filters);

        return view('ess.audit_compliance', $report);
    }

    public function exportAuditComplianceCsv(Request $request): StreamedResponse
    {
        $filters = [
            'period_year' => $request->get('period_year', date('Y')),
            'warehouse_id' => $request->get('warehouse_id', 'all'),
        ];

        $report = $this->essReportService->getAuditComplianceReport($filters);
        $filename = 'ESS_Akuntabilitas_Audit_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['NO. OPNAME', 'GUDANG / UNIT', 'PERIODE', 'TANGGAL SENSUS', 'AUDITOR / PETUGAS', 'TOTAL ITEM DIHITUNG', 'ITEM SELISIH', 'SELISIH QTY BERSIH', 'NILAI SELISIH / VARIANCE (RP)', 'STATUS OPNAME']);

            foreach ($report['tableData'] as $opn) {
                fputcsv($handle, [
                    $opn->opname_number,
                    $opn->warehouse?->name ?? '-',
                    $opn->period_formatted,
                    $opn->opname_date?->format('Y-m-d') ?? '-',
                    $opn->user?->name ?? '-',
                    $opn->total_items,
                    $opn->discrepancy_items_count,
                    $opn->net_variance_qty,
                    $opn->net_variance_value,
                    $opn->status,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 7. Laporan Prediktif & Proyeksi Anggaran Pengadaan
     */
    public function predictiveBudget(Request $request)
    {
        $filters = [
            'horizon_months' => (int) $request->get('horizon_months', 6),
            'category_id' => $request->get('category_id', 'all'),
        ];

        $report = $this->essReportService->getPredictiveBudgetReport($filters);

        return view('ess.predictive_budget', $report);
    }

    public function exportPredictiveBudgetCsv(Request $request): StreamedResponse
    {
        $filters = [
            'horizon_months' => (int) $request->get('horizon_months', 6),
            'category_id' => $request->get('category_id', 'all'),
        ];

        $report = $this->essReportService->getPredictiveBudgetReport($filters);
        $filename = 'ESS_Proyeksi_Anggaran_Pengadaan_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['SKU', 'NAMA BARANG', 'KATEGORI', 'SATUAN', 'HARGA ESTIMASI (RP)', 'STOK BEBAS SAAT INI', 'KONSUMSI / HARI', 'PROYEKSI KEBUTUHAN (UNIT)', 'SAFETY STOCK', 'REKOMENDASI PENGADAAN (UNIT)', 'ESTIMASI ANGGARAN (RP)', 'STATUS KEBUTUHAN']);

            foreach ($report['tableData'] as $row) {
                $it = $row['item'];
                fputcsv($handle, [
                    $it->sku,
                    $it->name,
                    $it->category?->name ?? '-',
                    $it->uom,
                    $it->estimated_unit_price,
                    $row['available_stock'],
                    $row['daily_demand'],
                    $row['projected_demand'],
                    $row['safety_stock'],
                    $row['recommended_qty'],
                    $row['estimated_budget'],
                    $row['status'],
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
