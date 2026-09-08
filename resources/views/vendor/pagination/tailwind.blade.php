@php
    $actualPageName = $pageName ?? (isset($paginator) ? $paginator->getPageName() : 'page');
    $currentPerPage = $perPage ?? request('per_page', isset($paginator) ? ($paginator->perPage() ?? 10) : 10);
    $sizesList = $sizes ?? [5, 10, 15, 25, 50];
    if (!in_array((int)$currentPerPage, $sizesList) && (int)$currentPerPage > 0) {
        $sizesList[] = (int)$currentPerPage;
        sort($sizesList);
    }
@endphp

<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="w-100">
    <div class="row align-items-center justify-content-between w-100 g-2 my-0">
        <!-- 1. Left: Data Counter Info -->
        <div class="col-12 col-md-4 text-start d-flex align-items-center justify-content-md-start justify-content-center order-3 order-md-1">
            <p class="text-xs text-secondary mb-0">
                @if (isset($paginator) && $paginator->total() > 0)
                    Menampilkan
                    <span class="fw-bold text-body">{{ $paginator->firstItem() ?? 0 }}</span>
                    sampai
                    <span class="fw-bold text-body">{{ $paginator->lastItem() ?? 0 }}</span>
                    dari
                    <span class="fw-bold text-body">{{ number_format($paginator->total()) }}</span>
                    data
                @else
                    Menampilkan <span class="fw-bold text-body">0</span> data
                @endif
            </p>
        </div>

        <!-- 2. Center: Baris Per Halaman (Rows Per Page) -->
        <div class="col-12 col-md-4 text-center d-flex align-items-center justify-content-center order-2 order-md-2">
            @if (isset($paginator) && ($paginator->total() > 0 || (int)$currentPerPage > 0))
                <div class="d-inline-flex align-items-center gap-2">
                    <span class="text-secondary fs-8 fw-semibold text-nowrap">Baris per halaman:</span>
                    <select onchange="window.location.href=this.value" class="form-select form-select-sm fs-8 shadow-2xs py-1" style="width: 75px;">
                        @foreach($sizesList as $sz)
                            @php
                                $query = request()->query();
                                $query['per_page'] = $sz;
                                $query[$actualPageName] = 1;
                                if (isset($tab) && $tab) {
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

        <!-- 3. Right: Pagination Navigation Links -->
        <div class="col-12 col-md-4 text-end d-flex align-items-center justify-content-md-end justify-content-center order-1 order-md-3">
            @if (isset($paginator) && $paginator->hasPages())
                <span class="relative z-0 inline-flex items-center space-x-1">
                    {{-- First Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="First" class="relative inline-flex items-center px-2 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 border border-slate-200 cursor-default rounded-lg">
                            <i class="bi bi-chevron-double-left fs-8" aria-hidden="true"></i>
                        </span>
                    @else
                        <a href="{{ $paginator->url(1) }}" rel="first" aria-label="First" class="relative inline-flex items-center px-2 py-1.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-jatim-700 transition shadow-2xs" title="Halaman Pertama">
                            <i class="bi bi-chevron-double-left fs-8" aria-hidden="true"></i>
                        </a>
                    @endif

                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="relative inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 border border-slate-200 cursor-default rounded-lg">
                            <i class="bi bi-chevron-left fs-8" aria-hidden="true"></i>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="relative inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-jatim-700 transition shadow-2xs" title="Halaman Sebelumnya">
                            <i class="bi bi-chevron-left fs-8" aria-hidden="true"></i>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true" class="relative inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-400 bg-white border border-slate-200 cursor-default rounded-lg">{{ $element }}</span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="relative inline-flex items-center px-3 py-1.5 text-xs font-bold text-white bg-jatim-700 border border-jatim-700 rounded-lg shadow-xs cursor-default">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-jatim-700 transition shadow-2xs">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="relative inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-jatim-700 transition shadow-2xs" title="Halaman Berikutnya">
                            <i class="bi bi-chevron-right fs-8" aria-hidden="true"></i>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="relative inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 border border-slate-200 cursor-default rounded-lg">
                            <i class="bi bi-chevron-right fs-8" aria-hidden="true"></i>
                        </span>
                    @endif

                    {{-- Last Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->url($paginator->lastPage()) }}" rel="last" aria-label="Last" class="relative inline-flex items-center px-2 py-1.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-jatim-700 transition shadow-2xs" title="Halaman Terakhir">
                            <i class="bi bi-chevron-double-right fs-8" aria-hidden="true"></i>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="Last" class="relative inline-flex items-center px-2 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 border border-slate-200 cursor-default rounded-lg">
                            <i class="bi bi-chevron-double-right fs-8" aria-hidden="true"></i>
                        </span>
                    @endif
                </span>
            @endif
        </div>
    </div>
</nav>
