@extends('layouts.app')
@section('title', 'Pusat Notifikasi & Tugas')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pusat Notifikasi</li>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-4">

    <!-- Notification List -->
    <div class="space-y-3">
        @forelse($notifications as $notif)
            <div class="p-4 rounded-2xl border {{ $notif->type === 'ACTION_REQUIRED' ? 'border-amber-300 bg-amber-50/40' : ($notif->type === 'ALERT' ? 'border-rose-300 bg-rose-50/40' : 'border-slate-200 bg-white') }} shadow-sm flex items-start justify-between gap-4">
                <div class="flex items-start space-x-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-base flex-shrink-0 mt-0.5
                        @if($notif->type === 'ACTION_REQUIRED') bg-amber-100 text-amber-700
                        @elseif($notif->type === 'ALERT') bg-rose-100 text-rose-700
                        @else bg-sky-100 text-sky-700 @endif">
                        <i class="fa-solid 
                            @if($notif->type === 'ACTION_REQUIRED') fa-clipboard-check
                            @elseif($notif->type === 'ALERT') fa-triangle-exclamation
                            @else fa-circle-info @endif"></i>
                    </div>

                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="font-bold text-xs text-slate-900">{{ $notif->title }}</h3>
                            <span class="text-[10px] font-bold px-2 py-0.2 rounded uppercase
                                @if($notif->priority === 'CRITICAL' || $notif->priority === 'HIGH') bg-rose-100 text-rose-800
                                @elseif($notif->priority === 'WARNING') bg-amber-100 text-amber-800
                                @else bg-slate-100 text-slate-700 @endif">
                                {{ $notif->priority }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $notif->message }}</p>
                        <div class="text-[10px] text-slate-400 mt-1">{{ $notif->created_at->diffForHumans() }}</div>
                    </div>
                </div>

                @if($notif->action_url)
                    <a href="{{ $notif->action_url }}" class="flex-shrink-0 px-3 py-1.5 bg-slate-900 hover:bg-jatim-700 text-white text-xs font-bold rounded-lg transition">
                        Buka Transaksi
                    </a>
                @endif
            </div>
        @empty
            <div class="p-8 text-center bg-white rounded-2xl border border-slate-200 text-slate-400 text-xs">
                <i class="fa-regular fa-bell text-2xl mb-2 block text-slate-300"></i>
                Tidak ada notifikasi baru.
            </div>
        @endforelse
    </div>

    <div class="card shadow-xs rounded-2xl overflow-hidden border border-slate-200">
        <x-pagination-footer :paginator="$notifications" />
    </div>
</div>
@endsection
