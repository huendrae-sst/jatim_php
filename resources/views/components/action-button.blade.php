@props([
    'type' => 'view', // view, edit, delete, custom
    'icon' => null,
    'title' => null,
    'href' => null,
])

@php
    $defaultIcon = match($type) {
        'view' => 'bi bi-eye',
        'edit' => 'bi bi-pencil-square',
        'delete' => 'bi bi-trash',
        default => 'bi bi-three-dots',
    };
    $defaultColor = match($type) {
        'view' => 'text-secondary',
        'edit' => 'text-primary',
        'delete' => 'text-danger',
        default => 'text-secondary',
    };
    $defaultTitle = match($type) {
        'view' => 'Lihat Detail',
        'edit' => 'Edit Data',
        'delete' => 'Hapus Data',
        default => '',
    };
    
    $resolvedIcon = $icon ?? $defaultIcon;
    $resolvedTitle = $title ?? $defaultTitle;
@endphp

@if($href)
    <a href="{{ $href }}" 
       {{ $attributes->merge(['class' => 'btn-action-icon ' . $defaultColor]) }} 
       title="{{ $resolvedTitle }}" 
       aria-label="{{ $resolvedTitle }}">
        <i class="{{ $resolvedIcon }}"></i>
    </a>
@else
    <button type="button" 
            {{ $attributes->merge(['class' => 'btn-action-icon ' . $defaultColor]) }} 
            title="{{ $resolvedTitle }}" 
            aria-label="{{ $resolvedTitle }}">
        <i class="{{ $resolvedIcon }}"></i>
    </button>
@endif
