{{-- Kit: stat, one headline figure. Put stats inside a <dl> (several divide neatly inside one card). The value is in tabular
     figures; `suffix` follows it quietly (include its own leading space, e.g. " / 5"); no value shows a dash. `hint` is the
     one line beneath. No trend arrow, by design. --}}
@props(['label', 'value' => null, 'suffix' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => 'min-w-0 p-4']) }}>
    <dt class="text-xs font-medium uppercase tracking-wide text-base-content/70">{{ $label }}</dt>
    <dd class="mt-1 text-2xl font-semibold tabular-nums">
        @if ($value === null || $value === '')
            —
        @else
            {{ $value }}@if ($suffix !== null)<span class="text-base font-normal text-base-content/70">{{ $suffix }}</span>@endif
        @endif
    </dd>
    @if ($hint !== null)
        <dd class="mt-0.5 tabular-nums text-base-content/70">{{ $hint }}</dd>
    @endif
</div>
