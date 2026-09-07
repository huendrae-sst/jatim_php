@props([
    'id',
    'title' => '',
    'subtitle' => null,
    'icon' => null,
    'size' => 'modal-lg', // modal-sm, modal-md, modal-lg, modal-xl
    'footer' => null,
    'scrollable' => true,
    'fullscreenMobile' => true,
])

<div class="modal fade" 
     id="{{ $id }}" 
     tabindex="-1" 
     aria-labelledby="{{ $id }}Label" 
     aria-hidden="true" 
     {{ $attributes }}>
    <div class="modal-dialog modal-dialog-centered {{ $size }} {{ $scrollable ? 'modal-dialog-scrollable' : '' }} {{ $fullscreenMobile ? 'modal-fullscreen-sm-down' : '' }}">
        <div class="modal-content shadow-lg border-0 rounded-3">
            <div class="modal-header border-bottom py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    @if($icon)
                        <i class="{{ $icon }} text-danger fs-5 flex-shrink-0"></i>
                    @endif
                    <div class="text-truncate">
                        <h5 class="modal-title fs-6 fw-bold mb-0 text-truncate" id="{{ $id }}Label">{{ $title }}</h5>
                        @if($subtitle)
                            <p class="text-muted fs-8 mb-0 mt-0.5">{{ $subtitle }}</p>
                        @endif
                    </div>
                </div>
                <button type="button" class="btn-close fs-8" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-3 p-md-4">
                {{ $slot }}
            </div>

            @if(isset($footer))
                <div class="modal-footer border-top py-2.5 px-3 px-md-4 d-flex flex-column flex-sm-row justify-content-end gap-2">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
