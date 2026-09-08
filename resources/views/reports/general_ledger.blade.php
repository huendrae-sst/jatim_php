@extends('layouts.app')
@section('title', 'Buku Besar (General Ledger)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none text-danger">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Buku Besar (General Ledger)</li>
@endsection

@section('content')
<div class="space-y-5">

    <!-- Filter & Action Panel (Memilih Unit Kerja, Rekening, Periode, & Aksi) -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('reports.general_ledger') }}" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Filter Unit / Cabang -->
                <div>
                    <label class="form-label text-xs fw-bold text-slate-600 mb-1">Unit Kerja / Cabang</label>
                    @if(auth()->user()->isBranchUser() && auth()->user()->organization_id)
                        <input type="text" class="form-control form-control-sm bg-slate-100 font-semibold" value="{{ auth()->user()->organization?->name }}" readonly>
                        <input type="hidden" name="organization_id" value="{{ auth()->user()->organization_id }}">
                    @else
                        <select name="organization_id" class="form-select form-select-sm">
                            <option value="ALL" {{ ($filters['organization_id'] ?? 'ALL') === 'ALL' ? 'selected' : '' }}>-- Seluruh Cabang & KP (Global) --</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}" {{ (string) ($filters['organization_id'] ?? '') === (string) $org->id ? 'selected' : '' }}>
                                    [{{ $org->code }}] {{ $org->name }} ({{ $org->type }})
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <!-- Filter Akun CoA -->
                <div>
                    <label class="form-label text-xs fw-bold text-slate-600 mb-1">Rekening Akun (CoA)</label>
                    <select name="chart_of_account_id" class="form-select form-select-sm">
                        <option value="ALL" {{ ($filters['chart_of_account_id'] ?? 'ALL') === 'ALL' ? 'selected' : '' }}>-- Semua Rekening Akun --</option>
                        @foreach($allCoas as $c)
                            <option value="{{ $c->id }}" {{ (string) ($filters['chart_of_account_id'] ?? '') === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->account_code }} - {{ $c->account_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Tanggal Dari -->
                <div>
                    <label class="form-label text-xs fw-bold text-slate-600 mb-1">Periode Dari</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $filters['start_date'] }}">
                </div>

                <!-- Filter Tanggal Sampai -->
                <div>
                    <label class="form-label text-xs fw-bold text-slate-600 mb-1">Periode Sampai</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $filters['end_date'] }}">
                </div>
            </div>

            <!-- Action Buttons: Filter, Reset, Unduh CSV, Cetak Laporan -->
            <div class="flex items-center justify-between flex-wrap gap-2 pt-2 border-t border-slate-100">
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-sm btn-danger fw-bold">
                        <i class="bi bi-funnel me-1"></i> Terapkan Filter
                    </button>
                    <a href="{{ route('reports.general_ledger') }}" class="btn btn-sm btn-light border fw-semibold" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.general_ledger.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fw-bold">
                        <i class="bi bi-file-earmark-excel me-1"></i> Unduh CSV
                    </a>
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold">
                        <i class="bi bi-printer me-1"></i> Cetak Laporan
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Debit -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <div class="text-[11px] uppercase font-bold text-slate-400">Total Mutasi Debit</div>
            <div class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Rp {{ number_format($totalDebitGlobal, 0, ',', '.') }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Seluruh rekening dalam periode</div>
        </div>

        <!-- Total Credit -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="text-[11px] uppercase font-bold text-slate-400">Total Mutasi Kredit</div>
                @if($isBalanced)
                    <span class="badge text-bg-success text-[10px]"><i class="bi bi-check-circle-fill me-0.5"></i> Balanced (Seimbang)</span>
                @else
                    <span class="badge text-bg-danger text-[10px]"><i class="bi bi-exclamation-triangle-fill me-0.5"></i> Selisih</span>
                @endif
            </div>
            <div class="text-xl sm:text-2xl font-black text-slate-900 mt-1">Rp {{ number_format($totalCreditGlobal, 0, ',', '.') }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">
                @if($isBalanced)
                    <span class="text-emerald-600 font-bold">Jurnal Seimbang - Balanced (Seimbang)</span>
                @else
                    <span class="text-rose-600 font-bold">Selisih Rp {{ number_format(abs($totalDebitGlobal - $totalCreditGlobal), 2, ',', '.') }}</span>
                @endif
            </div>
        </div>

        <!-- Active Accounts Count -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <div class="text-[11px] uppercase font-bold text-slate-400">Rekening Memiliki Mutasi / Saldo</div>
            <div class="text-xl sm:text-2xl font-black text-indigo-600 mt-1">{{ $activeAccountsCount }} Akun</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Dari total {{ $allCoas->count() }} rekening CoA</div>
        </div>

        <!-- Total Journal Lines -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <div class="text-[11px] uppercase font-bold text-slate-400">Total Transaksi Baris Jurnal</div>
            <div class="text-xl sm:text-2xl font-black text-emerald-700 mt-1">{{ $totalEntriesCount }} Baris</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Audit log double-entry terverifikasi</div>
        </div>
    </div>

    <!-- Ledger Accounts Loop -->
    @if(empty($ledgerData))
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
            <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-2xl mb-3">
                <i class="bi bi-journal-x"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Tidak Ada Data Buku Besar</h3>
            <p class="text-xs text-slate-500 max-w-md mx-auto mt-1">
                Tidak ditemukan mutasi transaksi atau saldo berjalan pada kriteria cabang dan periode tanggal yang dipilih.
            </p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($ledgerData as $data)
                @php
                    $account = $data['account'];
                    $isDebit = strtoupper($account->normal_balance) === 'DEBIT';
                @endphp
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <!-- Account Ledger Header -->
                    <div class="bg-slate-50 border-b border-slate-200 p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono font-black text-base px-2.5 py-1 bg-slate-900 text-white rounded-lg">
                                    {{ $account->account_code }}
                                </span>
                                <h3 class="text-base font-bold text-slate-900 mb-0">
                                    {{ $account->account_name }}
                                </h3>
                                <span class="badge {{ $account->type_badge_class }}">
                                    {{ $account->account_type }}
                                </span>
                                <span class="text-[11px] font-semibold text-slate-500 bg-white border px-2 py-0.5 rounded">
                                    Saldo Normal: <strong>{{ $account->normal_balance }}</strong>
                                </span>
                            </div>
                            @if($account->description)
                                <p class="text-xs text-slate-500 mb-0">{{ $account->description }}</p>
                            @endif
                        </div>

                        <!-- Mini Metrics Strip for this Account -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-right">
                            <div class="bg-white p-2.5 rounded-xl border border-slate-200 text-left">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Saldo Awal</div>
                                <div class="text-xs font-bold text-slate-800 mt-0.5">
                                    Rp {{ number_format($data['opening_balance'], 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="bg-white p-2.5 rounded-xl border border-slate-200 text-left">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Mutasi Debit</div>
                                <div class="text-xs font-bold text-emerald-700 mt-0.5">
                                    Rp {{ number_format($data['debit_total'], 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="bg-white p-2.5 rounded-xl border border-slate-200 text-left">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Mutasi Kredit</div>
                                <div class="text-xs font-bold text-rose-700 mt-0.5">
                                    Rp {{ number_format($data['credit_total'], 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="bg-slate-900 text-white p-2.5 rounded-xl border border-slate-900 text-left">
                                <div class="text-[10px] text-slate-300 font-bold uppercase">Saldo Akhir</div>
                                <div class="text-xs font-black text-amber-300 mt-0.5">
                                    Rp {{ number_format($data['ending_balance'], 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ledger Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100/70 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="py-2.5 px-3.5 w-24">Tanggal</th>
                                    <th class="py-2.5 px-3.5 w-36">No. Bukti / Jurnal</th>
                                    <th class="py-2.5 px-3.5 w-36">No. Referensi</th>
                                    <th class="py-2.5 px-3.5 w-44">Unit / Cabang</th>
                                    <th class="py-2.5 px-3.5">Keterangan Transaksi</th>
                                    <th class="py-2.5 px-3.5 text-right w-32">Debit (Rp)</th>
                                    <th class="py-2.5 px-3.5 text-right w-32">Kredit (Rp)</th>
                                    <th class="py-2.5 px-3.5 text-right w-36">Saldo Berjalan (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <!-- Opening Balance Line -->
                                <tr class="bg-slate-50/50 font-semibold text-slate-600 italic">
                                    <td class="py-2.5 px-3.5">{{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }}</td>
                                    <td class="py-2.5 px-3.5">-</td>
                                    <td class="py-2.5 px-3.5">-</td>
                                    <td class="py-2.5 px-3.5 text-slate-400">Saldo Awal Periode</td>
                                    <td class="py-2.5 px-3.5">Saldo awal per {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }}</td>
                                    <td class="py-2.5 px-3.5 text-right">-</td>
                                    <td class="py-2.5 px-3.5 text-right">-</td>
                                    <td class="py-2.5 px-3.5 text-right font-bold text-slate-900 not-italic">
                                        Rp {{ number_format($data['opening_balance'], 0, ',', '.') }}
                                    </td>
                                </tr>

                                @forelse($data['entries'] as $entry)
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-2.5 px-3.5 text-slate-700 whitespace-nowrap">{{ $entry['transaction_date'] }}</td>
                                        <td class="py-2.5 px-3.5 font-mono font-bold text-slate-900 whitespace-nowrap">{{ $entry['journal_number'] }}</td>
                                        <td class="py-2.5 px-3.5 font-mono text-slate-600 whitespace-nowrap">
                                            <span class="badge text-bg-light border font-mono">{{ $entry['reference_number'] }}</span>
                                        </td>
                                        <td class="py-2.5 px-3.5">
                                            <div class="font-semibold text-slate-800">{{ $entry['organization_name'] }}</div>
                                            <div class="text-[10px] font-mono text-slate-400">CC: {{ $entry['cost_center_code'] }}</div>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-slate-700">
                                            {{ $entry['description'] }}
                                        </td>
                                        <td class="py-2.5 px-3.5 text-right font-medium text-slate-900 whitespace-nowrap">
                                            {{ $entry['debit'] > 0 ? 'Rp ' . number_format($entry['debit'], 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="py-2.5 px-3.5 text-right font-medium text-slate-900 whitespace-nowrap">
                                            {{ $entry['credit'] > 0 ? 'Rp ' . number_format($entry['credit'], 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="py-2.5 px-3.5 text-right font-bold text-slate-900 whitespace-nowrap bg-slate-50/60">
                                            Rp {{ number_format($entry['running_balance'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-3 px-3.5 text-center text-slate-400 italic">
                                            Tidak ada mutasi transaksi pada periode tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse

                                <!-- Account Subtotal Summary -->
                                <tr class="bg-slate-100 font-bold border-t-2 border-slate-300 text-slate-900">
                                    <td colspan="5" class="py-2.5 px-3.5 uppercase text-right tracking-wider text-[11px]">
                                        Total Mutasi Rekening {{ $account->account_code }}:
                                    </td>
                                    <td class="py-2.5 px-3.5 text-right text-emerald-800 whitespace-nowrap">
                                        Rp {{ number_format($data['debit_total'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3.5 text-right text-rose-800 whitespace-nowrap">
                                        Rp {{ number_format($data['credit_total'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3.5 text-right font-black text-slate-900 whitespace-nowrap bg-slate-200">
                                        Rp {{ number_format($data['ending_balance'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>

<!-- Print Styles -->
<style>
@media print {
    .app-sidebar, .app-header, .btn, form, .breadcrumb {
        display: none !important;
    }
    .app-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .shadow-sm, .border {
        box-shadow: none !important;
    }
    body {
        background: #fff !important;
    }
}
</style>
@endsection
