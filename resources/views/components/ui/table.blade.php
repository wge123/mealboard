{{-- Kit: table shell. Put it inside <x-ui.card flush>. `head` is the <tr> of <x-ui.table.th>. Cells are styled here, once
     (px-4 py-2.5, a hairline between rows); a cell that holds a 44px <label> hit area opts out with `p-0!`.
     Fold state lives in Alpine on the table: `fold` holds the groups a person toggled, `auto` the groups that fold
     themselves when complete (set by <x-ui.table.group>), and open(key) is what <x-ui.table.row group="key"> shows by. --}}
@props(['head' => null, 'headOnPhone' => false])

<table
    x-data="{ fold: {}, auto: {}, open(key) { return this.fold[key] ?? ! this.auto[key] } }"
    {{ $attributes->merge(['class' => 'w-full text-start [&_td]:px-4 [&_td]:py-2.5 [&_tbody_tr]:border-b [&_tbody_tr]:border-base-300 [&_tbody:last-of-type_tr:last-child]:border-b-0']) }}
>
    @if ($head)
        <thead @class(['hidden sm:table-header-group' => ! $headOnPhone, '[&_tr]:border-b [&_tr]:border-base-300'])>{{ $head }}</thead>
    @endif
    {{ $slot }}
</table>
