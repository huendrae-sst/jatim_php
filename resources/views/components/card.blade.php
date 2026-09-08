@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'actions' => null,
    'footer' => null,
    'headerClass' => '',
    'bodyClass' => '',
    'footerClass' => '',
    'noPadding' => false,
])

<div {{ $attributes->merge(['class' => 'card shadow-xs border bg-body rounded-3 mb-0']) }}>
    @if($title || $subtitle || isset($header) || isset($actions))
        <div class="card-header bg-transparent border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 py-3 px-3 px-md-4 {{ $headerClass }}">
            @if(isset($header))
                {{ $header }}
            @else
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <div class="text-truncate">
                        @if($title)
                            <h3 class="card-title fw-semibold mb-0 fs-6">{{ $title }}</h3>
                        @endif
                        @if($subtitle)
                            <p class="text-muted fs-8 mb-0 mt-0.5">{{ $subtitle }}</p>
                        @endif
                    </div>
                </div>
                @if(isset($actions))
                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                        {{ $actions }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    <div class="{{ $noPadding ? 'p-0' : 'card-body p-3 p-md-4' }} {{ $bodyClass }}">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="card-footer bg-body border-top py-2.5 px-3 px-md-4 {{ $footerClass }}">
            {{ $footer }}
        </div>
    @endif
</div>
