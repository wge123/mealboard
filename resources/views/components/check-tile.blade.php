{{--
    A text tile that is on or off, with a check box: the pantry staples and the
    avoided ingredients. Classes are spelled out per tone so Tailwind sees them.
--}}
@props(['label', 'on', 'tone' => 'primary'])
@php
    $onTile = ['primary' => 'border-primary bg-primary/10 font-medium', 'error' => 'border-error bg-error/10 font-medium text-error'][$tone];
    $onBox = ['primary' => 'border-primary bg-primary text-primary-content', 'error' => 'border-error bg-error text-error-content'][$tone];
@endphp
{{-- data-test sits right before aria-pressed: the settings tests read the pair together. --}}
<button type="button" {{ $attributes->only('data-test') }} aria-pressed="{{ $on ? 'true' : 'false' }}"
        {{ $attributes->except('data-test')->class(['flex min-h-12 w-full items-center gap-2 rounded-xl border px-3 py-2 text-left text-sm', $on ? $onTile : 'border-base-300 bg-base-100']) }}>
    <span aria-hidden="true" class="grid size-4 shrink-0 place-items-center rounded border text-[10px] {{ $on ? $onBox : 'border-base-300' }}">{{ $on ? '✓' : '' }}</span>
    <span class="line-clamp-2 leading-tight">{{ $label }}</span>
</button>
