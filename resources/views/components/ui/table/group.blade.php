{{-- Kit: table group row. The whole row is one 44px button with a chevron, the label and done/total. A complete group folds
     itself (`fold-when-complete`, default on); a manual fold lasts until the row is re-rendered, and the screen keys the row on its
     done count (wire:key="g-label-done") so a tick re-creates it and the auto rule takes over again.
     `cols` is the table's column count. Leave `done` out for a plain group that only counts its rows (shows the total, never folds itself). Rows beneath it are <x-ui.table.row group="label">. --}}
@props(['label', 'done' => null, 'total' => 0, 'foldWhenComplete' => true, 'cols' => 99])

@php
    $complete = $done !== null && $total > 0 && $done >= $total;
    $auto = $complete && $foldWhenComplete;
@endphp

<tr {{ $attributes }} x-init="auto[@js($label)] = @js($auto); delete fold[@js($label)]">
    <th colspan="{{ $cols }}" scope="colgroup" class="bg-base-200 p-0 text-start text-xs font-semibold uppercase tracking-wide text-base-content/70">
        <button
            type="button"
            class="flex min-h-11 w-full items-center gap-2 px-4 text-start uppercase sm:min-h-9"
            aria-expanded="{{ $auto ? 'false' : 'true' }}"
            :aria-expanded="open(@js($label)) ? 'true' : 'false'"
            @click="fold[@js($label)] = ! open(@js($label))"
        >
            <span class="flex transition" :class="open(@js($label)) ? '' : '-rotate-90'"><x-tabler-chevron-down /></span>
            {{ $label }}
            <span class="tabular-nums">{{ $done === null ? $total : $done.'/'.$total }}</span>
            @if ($complete)
                <x-ui.badge tone="primary" class="ms-1 normal-case tracking-normal">Done</x-ui.badge>
            @endif
        </button>
    </th>
</tr>
