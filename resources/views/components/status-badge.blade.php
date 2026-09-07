@props([
    'status' => '',
    'size' => 'fs-8', // fs-8, fs-9, etc.
    'pill' => false,
])

@php
    $normalized = strtoupper(trim((string) $status));
    $bgClass = match($normalized) {
        '1', 'TRUE', 'ACTIVE', 'AKTIF', 'APPROVED', 'COMPLETED', 'RECEIVED', 'VALIDATED' => 'text-bg-success',
        'WAITING_APPROVAL', 'PENDING', 'SUBMITTED', 'WARNING' => 'text-bg-warning',
        'IN_TRANSIT', 'ALLOCATED', 'READY_TO_SHIP', 'INFO' => 'text-bg-info',
        'PRIMARY', 'PARTIALLY_ORDERED', 'FULLY_ORDERED' => 'text-bg-primary',
        '0', 'FALSE', 'INACTIVE', 'NON-AKTIF', 'REJECTED', 'DANGER', 'KURANG' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
    $label = match($normalized) {
        '1', 'TRUE', 'ACTIVE', 'AKTIF' => 'Aktif',
        '0', 'FALSE', 'INACTIVE', 'NON-AKTIF' => 'Non-Aktif',
        default => str_replace('_', ' ', $status),
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge ' . $bgClass . ' ' . $size . ' ' . ($pill ? 'rounded-pill' : 'rounded-1') . ' fw-medium text-uppercase tracking-wider']) }}>
    {{ $slot->isEmpty() ? $label : $slot }}
</span>
