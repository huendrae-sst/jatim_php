@props([
    'title',
    'subtitle' => null,
    'icon' => null,
    'actions' => null,
    'breadcrumbs' => null,
])

<div {{ $attributes->merge(['class' => 'app-content-header py-3 px-3 px-md-4 mb-3 border-bottom bg-body shadow-2xs rounded-3']) }}>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <!-- Title & Subtitle -->
        <div class="min-w-0">
            <div class="d-flex align-items-center gap-2">
                @if($icon)
                    <i class="{{ $icon }} text-danger fs-4"></i>
                @endif
                <h1 class="h4 fw-bold mb-0 tracking-tight text-truncate">{{ $title }}</h1>
            </div>
            @if($subtitle)
                <p class="text-secondary fs-8 mb-0 mt-1">{{ $subtitle }}</p>
            @endif
        </div>

        <!-- Breadcrumbs or Actions Container -->
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2.5 ms-md-auto w-100 w-md-auto justify-content-md-end">
            @if(isset($actions))
                <div class="d-flex align-items-center gap-2 flex-wrap w-100 w-sm-auto">
                    {{ $actions }}
                </div>
            @endif

            @if(isset($breadcrumbs))
                <nav aria-label="breadcrumb" class="fs-8">
                    <ol class="breadcrumb mb-0 bg-transparent p-0">
                        {{ $breadcrumbs }}
                    </ol>
                </nav>
            @endif
        </div>
    </div>
</div>
