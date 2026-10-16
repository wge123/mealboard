{{-- Kit: datagrid, a record's facts as label-over-value pairs in a <dl>. Items are <x-ui.datagrid.item label="…">.
     `cols` sets the columns from the sm breakpoint up (a phone always gets two). --}}
@props(['cols' => 4])

@php
    $columns = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-4'];

    if (! isset($columns[$cols])) {
        throw new InvalidArgumentException("Datagrid columns must be 1 to 4, got [{$cols}].");
    }
@endphp

<dl {{ $attributes->merge(['class' => "grid grid-cols-2 gap-x-6 gap-y-3 {$columns[$cols]}"]) }}>
    {{ $slot }}
</dl>
