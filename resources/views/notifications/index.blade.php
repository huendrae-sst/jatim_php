@extends('layouts.app')
@section('title', 'Pusat Notifikasi & Tugas Operasional')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pusat Notifikasi</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-body mb-1">
                Pusat Notifikasi & Tugas
            </h4>
            <p class="text-muted fs-7 mb-0">Monitor seluruh aktivitas, tugas tindakan, dan peringatan operasional persediaan Anda.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if(($unreadCount ?? 0) > 0)
                <form action="{{ route('notifications.mark_all_read') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1.5 shadow-xs">
                        <i class="bi bi-check2-all"></i>
                        <span>Tandai Semua Dibaca</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="card shadow-xs border-0 rounded-3 mb-3">
        <div class="card-body p-2">
            <ul class="nav nav-pills nav-fill flex-column flex-sm-row gap-1">
                <li class="nav-item">
                    <a class="nav-link fs-7 py-2 {{ ($tab ?? 'all') === 'all' ? 'active bg-danger fw-bold' : 'text-body' }}" 
                       href="{{ route('notifications.index', ['tab' => 'all']) }}">
                        <i class="bi bi-collection me-1"></i> Semua
                        <span class="badge {{ ($tab ?? 'all') === 'all' ? 'text-bg-light text-danger' : 'text-bg-secondary' }} ms-1.5">{{ $totalCount ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fs-7 py-2 {{ ($tab ?? '') === 'action_required' ? 'active bg-warning text-dark fw-bold' : 'text-body' }}" 
                       href="{{ route('notifications.index', ['tab' => 'action_required']) }}">
                        <i class="bi bi-clipboard-check me-1 text-warning"></i> Perlu Tindakan
                        <span class="badge {{ ($tab ?? '') === 'action_required' ? 'text-bg-dark' : 'text-bg-warning text-dark' }} ms-1.5">{{ $actionRequiredCount ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fs-7 py-2 {{ ($tab ?? '') === 'alert' ? 'active bg-danger fw-bold' : 'text-body' }}" 
                       href="{{ route('notifications.index', ['tab' => 'alert']) }}">
                        <i class="bi bi-exclamation-triangle me-1 text-danger"></i> Peringatan
                        <span class="badge {{ ($tab ?? '') === 'alert' ? 'text-bg-light text-danger' : 'text-bg-danger' }} ms-1.5">{{ $alertCount ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fs-7 py-2 {{ ($tab ?? '') === 'info' ? 'active bg-info text-dark fw-bold' : 'text-body' }}" 
                       href="{{ route('notifications.index', ['tab' => 'info']) }}">
                        <i class="bi bi-info-circle me-1 text-info"></i> Informasi
                        <span class="badge {{ ($tab ?? '') === 'info' ? 'text-bg-dark' : 'text-bg-info text-dark' }} ms-1.5">{{ $infoCount ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fs-7 py-2 {{ ($tab ?? '') === 'unread' ? 'active bg-secondary fw-bold' : 'text-body' }}" 
                       href="{{ route('notifications.index', ['tab' => 'unread']) }}">
                        <i class="bi bi-envelope-badge me-1"></i> Belum Dibaca
                        <span class="badge {{ ($tab ?? '') === 'unread' ? 'text-bg-light text-secondary' : 'text-bg-secondary' }} ms-1.5">{{ $unreadCount ?? 0 }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Notification List -->
    <div class="vstack gap-2 mb-4">
        @forelse($notifications as $notif)
            <div class="card shadow-xs border-0 rounded-3 transition-all {{ !$notif->is_read ? 'border-start border-4 border-danger bg-body-tertiary' : 'bg-body' }}">
                <div class="card-body p-3">
                    <div class="d-flex flex-column flex-md-row align-items-start justify-content-between gap-3">
                        <div class="d-flex align-items-start gap-3 flex-grow-1">
                            <!-- Icon -->
                            <div class="rounded-3 p-2 d-flex align-items-center justify-content-center flex-shrink-0
                                @if($notif->type === 'ACTION_REQUIRED') bg-warning-subtle text-warning
                                @elseif($notif->type === 'ALERT') bg-danger-subtle text-danger
                                @else bg-info-subtle text-info @endif" style="width: 42px; height: 42px;">
                                <i class="bi 
                                    @if($notif->type === 'ACTION_REQUIRED') bi-clipboard-check-fill fs-5
                                    @elseif($notif->type === 'ALERT') bi-exclamation-triangle-fill fs-5
                                    @else bi-info-circle-fill fs-5 @endif"></i>
                            </div>

                            <!-- Content -->
                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h6 class="fw-bold mb-0 text-body fs-7">{{ $notif->title }}</h6>
                                    
                                    <!-- Priority Badge -->
                                    <span class="badge fs-8 
                                        @if($notif->priority === 'CRITICAL') text-bg-danger
                                        @elseif($notif->priority === 'HIGH') text-bg-danger
                                        @elseif($notif->priority === 'WARNING') text-bg-warning text-dark
                                        @else text-bg-secondary @endif">
                                        {{ $notif->priority }}
                                    </span>

                                    <!-- Reference Transaction Type -->
                                    @if($notif->reference_transaction_type)
                                        <span class="badge text-bg-light border fs-8 text-secondary">
                                            {{ $notif->reference_transaction_type }}
                                            @if($notif->reference_transaction_id) #{{ $notif->reference_transaction_id }} @endif
                                        </span>
                                    @endif

                                    <!-- Status Baca -->
                                    @if(!$notif->is_read)
                                        <span class="badge text-bg-danger fs-8">Baru</span>
                                    @endif
                                </div>
                                <p class="text-body-secondary fs-7 mb-1.5 leading-relaxed">{{ $notif->message }}</p>
                                <div class="d-flex align-items-center gap-3 text-muted fs-8">
                                    <span><i class="bi bi-clock me-1"></i>{{ $notif->created_at->diffForHumans() }} ({{ $notif->created_at->format('d M Y, H:i') }})</span>
                                    @if($notif->targetOrganization)
                                        <span><i class="bi bi-building me-1"></i>{{ $notif->targetOrganization->name }}</span>
                                    @endif
                                    @if($notif->target_role)
                                        <span><i class="bi bi-person-badge me-1"></i>{{ $notif->target_role }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex align-items-center gap-2 flex-shrink-0 align-self-md-center">
                            @if(!$notif->is_read)
                                <form action="{{ route('notifications.read', $notif->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary fs-8 py-1.5 px-2.5" title="Tandai sudah dibaca">
                                        <i class="bi bi-check2"></i>
                                    </button>
                                </form>
                            @endif

                            @if($notif->action_url)
                                <a href="{{ route('notifications.open', $notif->id) }}" class="btn btn-sm btn-danger fs-8 py-1.5 px-3 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs">
                                    <span>Buka Transaksi</span>
                                    <i class="bi bi-arrow-right-short fs-6"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card shadow-xs border-0 rounded-3 py-5 text-center bg-body">
                <div class="card-body">
                    <div class="rounded-circle bg-light d-inline-flex p-3 text-muted mb-3">
                        <i class="bi bi-bell-slash fs-2 text-secondary"></i>
                    </div>
                    <h6 class="fw-bold text-body mb-1">Tidak Ada Notifikasi</h6>
                    <p class="text-muted fs-7 mb-0">Saat ini tidak ada notifikasi yang sesuai dengan filter yang dipilih.</p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($notifications->hasPages())
        <div class="card shadow-xs border-0 rounded-3">
            <x-pagination-footer :paginator="$notifications" />
        </div>
    @endif
</div>
@endsection
