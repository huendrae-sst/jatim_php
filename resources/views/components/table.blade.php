@props([
    'striped' => false,
    'hover' => true,
    'small' => false,
    'borderless' => false,
    'tableClass' => '',
])

<div class="table-responsive w-100 overflow-x-auto" style="-webkit-overflow-scrolling: touch;">
    <table {{ $attributes->merge(['class' => 'table align-middle mb-0 ' . ($striped ? 'table-striped ' : '') . ($hover ? 'table-hover ' : '') . ($small ? 'table-sm ' : '') . ($borderless ? 'table-borderless ' : '') . $tableClass]) }}>
        {{ $slot }}
    </table>
</div>
