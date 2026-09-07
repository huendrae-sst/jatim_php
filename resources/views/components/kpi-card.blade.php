@props([
    'title',
    'value',
    'subtext' => null,
    'icon' => 'bi-bar-chart-fill',
    'color' => 'danger', // danger, warning, info, success, primary, secondary
    'link' => null,
    'linkText' => null,
    'col' => 'col-12 col-sm-6 col-xl-3',
])

@php
    $bgClass = match($color) {
        'danger' => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        'info' => 'text-bg-info',
        'success' => 'text-bg-success',
        'primary' => 'text-bg-primary',
        default => 'text-bg-secondary',
    };
    $subtextColor = in_array($color, ['warning', 'light']) ? 'text-dark-emphasis' : 'text-white-50';
    $linkColor = in_array($color, ['warning', 'light']) ? 'link-dark' : 'link-light';
@endphp

<div class="{{ $col }}">
    <div class="small-box {{ $bgClass }} shadow-xs rounded-3 h-100 d-flex flex-column justify-content-between mb-0">
        <div class="inner p-3 p-md-3.5">
            <h3 class="fw-bold fs-3 mb-1 tracking-tight">{{ $value }}</h3>
            <p class="mb-1 fw-semibold fs-7">{{ $title }}</p>
            @if($subtext)
                <div class="fs-8 {{ $subtextColor }} text-truncate">{{ $subtext }}</div>
            @endif
        </div>
        <i class="small-box-icon bi {{ $icon }} opacity-25"></i>
        @if($link)
            <a href="{{ $link }}" class="small-box-footer {{ $linkColor }} link-underline-opacity-0 link-underline-opacity-50-hover py-1.5 px-3 fs-8 fw-medium d-flex align-items-center justify-content-between">
                <span>{{ $linkText ?? 'Lihat Rincian' }}</span>
                <i class="bi bi-arrow-right-circle ms-1"></i>
            </a>
        @endif
    </div>
</div>
