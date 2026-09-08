@props([
    'paginator',
    'perPage' => null,
    'tab' => null,
    'pageName' => null,
    'sizes' => [5, 10, 15, 25, 50],
])

@php
    $actualPageName = $pageName ?? $paginator->getPageName();
    $currentPerPage = $perPage ?? request('per_page', $paginator->perPage() ?? 10);
    $allSizes = $sizes;
    if (!in_array((int)$currentPerPage, $allSizes) && (int)$currentPerPage > 0) {
        $allSizes[] = (int)$currentPerPage;
        sort($allSizes);
    }
@endphp

<!-- Standardized Bank Jatim Pagination Footer -->
<div class="card-footer bg-body border-top py-2.5 px-3 px-md-4">
    {{ $paginator->links('pagination::tailwind', [
        'perPage' => $currentPerPage,
        'tab' => $tab,
        'pageName' => $actualPageName,
        'sizes' => $allSizes,
    ]) }}
</div>

