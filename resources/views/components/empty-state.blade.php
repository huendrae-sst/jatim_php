@props([
    'icon' => 'bi-inbox',
    'title' => 'Data tidak ditemukan',
    'subtitle' => 'Tidak ada data yang sesuai dengan kriteria pencarian atau filter.',
    'actionText' => null,
    'actionLink' => null,
    'colspan' => null,
])

@if($colspan)
    <tr>
        <td colspan="{{ $colspan }}" class="text-center py-5 text-secondary">
            <div class="d-flex flex-column align-items-center justify-content-center p-3">
                <i class="bi {{ $icon }} fs-1 d-block mb-2 text-secondary-subtle"></i>
                <h6 class="fw-bold mb-1 text-body">{{ $title }}</h6>
                @if($subtitle)
                    <p class="fs-8 text-muted mb-0 max-w-md">{{ $subtitle }}</p>
                @endif
                @if(isset($action))
                    <div class="mt-3">
                        {{ $action }}
                    </div>
                @elseif($actionText && $actionLink)
                    <a href="{{ $actionLink }}" class="btn btn-sm btn-outline-danger mt-3 fs-8">
                        {{ $actionText }}
                    </a>
                @endif
            </div>
        </td>
    </tr>
@else
    <div class="text-center py-5 text-secondary">
        <div class="d-flex flex-column align-items-center justify-content-center p-3">
            <i class="bi {{ $icon }} fs-1 d-block mb-2 text-secondary-subtle"></i>
            <h6 class="fw-bold mb-1 text-body">{{ $title }}</h6>
            @if($subtitle)
                <p class="fs-8 text-muted mb-0 max-w-md">{{ $subtitle }}</p>
            @endif
            @if(isset($action))
                <div class="mt-3">
                    {{ $action }}
                </div>
            @elseif($actionText && $actionLink)
                <a href="{{ $actionLink }}" class="btn btn-sm btn-outline-danger mt-3 fs-8">
                    {{ $actionText }}
                </a>
            @endif
        </div>
    </div>
@endif
