@props([
    'paginator',
    'perPage' => null,
    'tab' => null,
    'sizes' => [5, 10, 15, 25, 50],
])

@php
    $pageName = $paginator->getPageName();
    $currentPerPage = $perPage ?? request('per_page', $paginator->perPage() ?? 10);
    $allSizes = $sizes;
    if (!in_array((int)$currentPerPage, $allSizes) && (int)$currentPerPage > 0) {
        $allSizes[] = (int)$currentPerPage;
        sort($allSizes);
    }
@endphp

<!-- Standardized Bank Jatim Pagination Footer -->
<div class="card-footer bg-body border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 py-3 px-4">
    <div class="flex-grow-1 w-100">
        {{ $paginator->links('pagination::tailwind') }}
    </div>

    @if($paginator->total() > 0)
        <!-- Per Page Dropdown -->
        <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-auto">
            <span class="text-secondary fs-8 fw-semibold text-nowrap">Baris per halaman:</span>
            <select onchange="window.location.href=this.value" class="form-select form-select-sm fs-8" style="width: 75px;">
                @foreach($allSizes as $sz)
                    @php
                        $query = request()->query();
                        $query['per_page'] = $sz;
                        $query[$pageName] = 1;
                        if ($tab) {
                            $query['tab'] = $tab;
                        }
                    @endphp
                    <option value="{{ url()->current() . '?' . http_build_query($query) }}" {{ (int)$currentPerPage === (int)$sz ? 'selected' : '' }}>
                        {{ $sz }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif
</div>
