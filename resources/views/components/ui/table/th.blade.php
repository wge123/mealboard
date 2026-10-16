{{-- Kit: table header cell. `sort` (asc|desc) marks the sorted column with aria-sort; `sortable` (implied by `sort`) wraps the
     label in a 44px button that takes the click attributes (wire:click) while class stays on the cell. `numeric` right-aligns. --}}
@props(['sort' => null, 'sortable' => false, 'numeric' => false])

@php
    if ($sort !== null && ! in_array($sort, ['asc', 'desc'], true)) {
        throw new InvalidArgumentException("Unknown sort direction [{$sort}].");
    }
    $sortable = $sortable || $sort !== null;
    $icon = ['asc' => 'tabler-chevron-up', 'desc' => 'tabler-chevron-down'][$sort] ?? 'tabler-arrows-sort';
@endphp

<th
    scope="col"
    @if ($sort) aria-sort="{{ $sort === 'asc' ? 'ascending' : 'descending' }}" @endif
    {{ $attributes->only('class')->merge(['class' => 'px-4 py-2 text-xs font-semibold uppercase tracking-wide text-base-content/70 '.($numeric ? 'num' : 'text-start')]) }}
>
    @if ($sortable)
        <button type="button" {{ $attributes->except('class') }} class="-mx-2 inline-flex min-h-11 items-center gap-1 rounded-field px-2 uppercase tracking-wide hover:text-base-content sm:min-h-9 {{ $sort ? 'text-base-content' : '' }}">
            {{ $slot }}
            <x-dynamic-component :component="$icon" class="{{ $sort ? '' : 'opacity-60' }}" />
        </button>
    @else
        {{ $slot }}
    @endif
</th>
