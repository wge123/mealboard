{{-- Kit: checklist line. A checkbox (or a leading `icon`), the label, a muted count and an optional trailing action, in a 44px line
     (32px on desktop). Lines flow in columns inside a list: wrap each in <li class="break-inside-avoid">.
     The checkbox needs an `id`; the whole label is the hit area and the attributes you pass (wire:click, x-on:click, aria-label)
     go on the checkbox. `count` is the muted number, `count-label` its words for assistive tech ("used in 3 recipes").
     The `action` slot sits beside the label, not inside it, so a tap on it never ticks the box: give its button an aria-label.
     With `icon` there is no checkbox and the line is a plain row. --}}
@props(['label', 'id' => null, 'checked' => false, 'count' => null, 'countLabel' => null, 'icon' => null, 'action' => null])

@php
    if ($icon === null && blank($id)) {
        throw new InvalidArgumentException('A checklist line needs an id: it binds the checkbox to its label.');
    }
    $line = 'flex min-h-11 min-w-0 flex-1 items-center gap-2.5 rounded-field px-2 sm:min-h-8';
@endphp

<div class="flex items-center">
    @if ($icon)
        <div class="{{ $line }}">
            <x-dynamic-component :component="$icon" class="size-4 shrink-0 text-base-content/70" />
            <span class="flex-1 truncate">{{ $label }}</span>
            @if ($count !== null)<span class="text-xs tabular-nums text-base-content/70" aria-hidden="true">{{ $count }}</span>@if ($countLabel)<span class="sr-only">{{ $countLabel }}</span>@endif @endif
        </div>
    @else
        <label for="{{ $id }}" class="{{ $line }} cursor-pointer hover:bg-base-200">
            <input id="{{ $id }}" type="checkbox" @checked($checked) {{ $attributes->merge(['class' => 'peer checkbox checkbox-primary checkbox-xs shrink-0']) }}>
            <span class="flex-1 truncate peer-[:not(:checked)]:text-base-content/70">{{ $label }}</span>
            @if ($count !== null)<span class="text-xs tabular-nums text-base-content/70" aria-hidden="true">{{ $count }}</span>@if ($countLabel)<span class="sr-only">{{ $countLabel }}</span>@endif @endif
        </label>
    @endif
    @if ($action)
        <div class="shrink-0">{{ $action }}</div>
    @endif
</div>
