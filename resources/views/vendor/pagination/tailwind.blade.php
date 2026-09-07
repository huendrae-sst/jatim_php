@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between w-full">
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-slate-400 bg-slate-100 border border-slate-200 cursor-default rounded-lg">
                    Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-2xs">
                    Sebelumnya
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition shadow-2xs">
                    Selanjutnya
                </a>
            @else
                <span class="relative inline-flex items-center px-3 py-1.5 text-xs font-semibold text-slate-400 bg-slate-100 border border-slate-200 cursor-default rounded-lg">
                    Selanjutnya
                </span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs text-slate-500">
                    Menampilkan
                    <span class="font-bold text-slate-800">{{ $paginator->firstItem() ?? 0 }}</span>
                    sampai
                    <span class="font-bold text-slate-800">{{ $paginator->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-bold text-slate-800">{{ $paginator->total() }}</span>
                    data
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex items-center space-x-1">
                    {{-- First Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="First" class="relative inline-flex items-center px-2 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 border border-slate-200 cursor-default rounded-lg">
                            <i class="fa-solid fa-angles-left text-[10px]"></i>
                        </span>
                    @else
                        <a href="{{ $paginator->url(1) }}" rel="first" aria-label="First" class="relative inline-flex items-center px-2 py-1.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-jatim-700 transition shadow-2xs" title="Halaman Pertama">
                            <i class="fa-solid fa-angles-left text-[10px]"></i>
                        </a>
                    @endif

                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="relative inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 border border-slate-200 cursor-default rounded-lg">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="relative inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-jatim-700 transition shadow-2xs" title="Halaman Sebelumnya">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
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
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="relative inline-flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 border border-slate-200 cursor-default rounded-lg">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </span>
                    @endif

                    {{-- Last Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->url($paginator->lastPage()) }}" rel="last" aria-label="Last" class="relative inline-flex items-center px-2 py-1.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-jatim-700 transition shadow-2xs" title="Halaman Terakhir">
                            <i class="fa-solid fa-angles-right text-[10px]"></i>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="Last" class="relative inline-flex items-center px-2 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 border border-slate-200 cursor-default rounded-lg">
                            <i class="fa-solid fa-angles-right text-[10px]"></i>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@elseif(isset($paginator) && $paginator->total() > 0)
    <div class="flex items-center justify-between text-xs text-slate-500 w-full py-1">
        <p>
            Menampilkan
            <span class="font-bold text-slate-800">{{ $paginator->total() }}</span>
            data (Semua)
        </p>
    </div>
@endif
