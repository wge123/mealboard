{{--
    A text tile that is on or off, with a check box: the pantry staples and the
    avoided ingredients. The look follows aria-pressed, so a tap can flip it
    before the server answers. Classes are spelled out per tone so Tailwind
    sees them.
--}}
@props(['label', 'on', 'tone' => 'primary'])
@php
    $onTile = ['primary' => 'aria-pressed:border-primary aria-pressed:bg-primary/10', 'error' => 'aria-pressed:border-error aria-pressed:bg-error/10 aria-pressed:text-error'][$tone];
    $onBox = ['primary' => 'group-aria-pressed:border-primary group-aria-pressed:bg-primary group-aria-pressed:text-primary-content', 'error' => 'group-aria-pressed:border-error group-aria-pressed:bg-error group-aria-pressed:text-error-content'][$tone];
@endphp
{{-- data-test sits right before aria-pressed: the settings tests read the pair together. --}}
<button type="button" {{ $attributes->only('data-test') }} aria-pressed="{{ $on ? 'true' : 'false' }}"
        {{ $attributes->except('data-test')->class(['group flex min-h-12 w-full items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-left text-sm aria-pressed:font-medium', $onTile]) }}>
    <span aria-hidden="true" class="grid size-4 shrink-0 place-items-center rounded border border-base-300 text-[10px] {{ $onBox }}"><span class="invisible group-aria-pressed:visible">✓</span></span>
    <span class="line-clamp-2 leading-tight">{{ $label }}</span>
</button>
