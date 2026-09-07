@extends('layouts.app')
@section('title', 'Audit Trail & Transaction Logs')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Master & Admin</li>
    <li class="breadcrumb-item active" aria-current="page">Audit Trail</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Audit Logs Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider font-sans">
                    <tr>
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Aksi Audit</th>
                        <th class="py-3 px-4">Model & ID</th>
                        <th class="py-3 px-4">Pelaksana (User)</th>
                        <th class="py-3 px-4">IP Address</th>
                        <th class="py-3 px-4">Perubahan Data (Snapshot)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($logs as $l)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 text-slate-500 text-[11px]">{{ $l->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="py-3.5 px-4">
                                <span class="bg-slate-100 text-slate-800 px-2 py-0.5 rounded font-bold text-[10px]">{{ $l->action }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-indigo-700 font-semibold">{{ class_basename($l->auditable_type) }} #{{ $l->auditable_id }}</td>
                            <td class="py-3.5 px-4 font-sans font-bold text-slate-800">{{ $l->user->name ?? 'System' }}</td>
                            <td class="py-3.5 px-4 text-slate-400">{{ $l->ip_address }}</td>
                            <td class="py-3.5 px-4 text-[10px] text-slate-600 max-w-sm truncate">
                                {{ json_encode($l->new_values ?? $l->old_values) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination-footer :paginator="$logs" />
    </div>
</div>
@endsection
