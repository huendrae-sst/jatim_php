@props([
    'action' => '',
    'method' => 'GET',
    'searchPlaceholder' => 'Cari data...',
    'searchValue' => '',
    'searchName' => 'search',
    'filters' => null,
    'buttons' => null,
    'resetUrl' => null,
])

<form action="{{ $action }}" method="{{ $method }}" {{ $attributes->merge(['class' => 'd-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2.5 w-100']) }}>
    <!-- Search Input Container (Full-width on mobile) -->
    <div class="input-group input-group-sm flex-grow-1" style="min-width: 240px; max-width: 100%;">
        <span class="input-group-text bg-body text-secondary border-end-0">
            <i class="bi bi-search"></i>
        </span>
        <input type="text" 
               name="{{ $searchName }}" 
               value="{{ $searchValue }}" 
               class="form-control form-control-sm border-start-0 ps-0 fs-8 bg-body"
               aria-label="{{ $searchPlaceholder }}">
    </div>

    <!-- Filters & Actions (Stack on mobile, row on tablet/desktop) -->
    <div class="d-flex flex-wrap align-items-center gap-2">
        @if(isset($filters))
            {{ $filters }}
        @endif

        <div class="d-flex align-items-center gap-1.5 ms-auto">
            <button type="submit" class="btn btn-sm btn-danger px-3 fs-8 d-inline-flex align-items-center gap-1">
                <i class="bi bi-funnel"></i>
                <span>Filter</span>
            </button>

            @if($resetUrl)
                <a href="{{ $resetUrl }}" class="btn btn-sm btn-outline-secondary px-2 fs-8" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            @endif

            @if(isset($buttons))
                {{ $buttons }}
            @endif
        </div>
    </div>
</form>
