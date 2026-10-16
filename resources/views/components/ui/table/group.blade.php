{{-- Kit: table group row. The whole row is one 44px button with a chevron, the label and done/total. A complete group folds
     itself (`fold-when-complete`, default on); a manual fold lasts until the row is re-rendered, and the screen keys the row on its
     done count (wire:key="g-label-done") so a tick re-creates it and the auto rule takes over again.
     `cols` is the table's column count. A default slot replaces the done/total with the screen's own words ("2 of 5 on hand").
     `sticky` pins the row under the top bar while its rows scroll (give it a string of `top-*` classes to clear anything else sticky above it).
     Leave `done` out for a plain group that only counts its rows (shows the total, never folds itself). Rows beneath it are <x-ui.table.row group="label">. --}}
@props(['label', 'done' => null, 'total' => 0, 'foldWhenComplete' => true, 'cols' => 99, 'sticky' => false])

@php
    $complete = $done !== null && $total > 0 && $done >= $total;
    $auto = $complete && $foldWhenComplete;
    $pin = $sticky ? 'sticky z-10 '.(is_string($sticky) ? $sticky : 'top-0 lg:top-14') : '';
@endphp

<tr {{ $attributes }} x-init="auto[@js($label)] = @js($auto); delete fold[@js($label)]">
    <th colspan="{{ $cols }}" scope="colgroup" class="{{ $pin }} bg-base-200 p-0 text-start text-xs font-semibold uppercase tracking-wide text-base-content/70">
        <button
            type="button"
            class="flex min-h-11 w-full items-center gap-2 px-4 text-start uppercase sm:min-h-9"
            aria-expanded="{{ $auto ? 'false' : 'true' }}"
            :aria-expanded="open(@js($label)) ? 'true' : 'false'"
            @click="fold[@js($label)] = ! open(@js($label))"
        >
            <span class="flex transition" :class="open(@js($label)) ? '' : '-rotate-90'"><x-tabler-chevron-down /></span>
            {{ $label }}
            <span class="tabular-nums normal-case">@if ($slot->isNotEmpty()){{ $slot }}@else{{ $done === null ? $total : $done.'/'.$total }}@endif</span>
            @if ($complete)
                <x-ui.badge tone="primary" class="ms-1 normal-case tracking-normal">Done</x-ui.badge>
            @endif
        </button>
    </th>
</tr>
