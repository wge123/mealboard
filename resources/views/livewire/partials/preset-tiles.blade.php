{{--
    One row of preset tiles plus an Other tile that opens a number field.
    Expects: $key (data-test prefix), $label, $presets, $value (stored value),
    $pick (method a tile calls with its number), $model (the field's property),
    $save (method the Other field submits to), $suffix (shown under each number).
--}}
{{-- Six presets plus Other: on a phone (four columns) Other spans two, so the second row has no orphan. --}}
@php $isPreset = in_array((int) $value, $presets, true); @endphp

<div x-data="{ other: false }">
    <h3 class="mb-2 text-sm font-medium">{{ $label }}</h3>
    <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
        @foreach ($presets as $preset)
            <button type="button" data-test="{{ $key }}-{{ $preset }}" aria-pressed="{{ (int) $value === $preset ? 'true' : 'false' }}"
                    class="flex min-h-14 flex-col items-center justify-center rounded-xl border {{ (int) $value === $preset ? 'border-primary bg-primary/10 font-medium' : 'border-base-300 bg-base-100' }}"
                    wire:click="{{ $pick }}({{ $preset }})" x-on:click="other = false">
                <span class="text-lg leading-none font-semibold tabular-nums">{{ $preset }}</span>
                @if ($suffix) <span class="text-[11px] opacity-70">{{ $suffix }}</span> @endif
            </button>
        @endforeach
        <button type="button" data-test="{{ $key }}-other" aria-pressed="{{ $isPreset ? 'false' : 'true' }}"
                class="col-span-2 flex min-h-14 flex-col items-center justify-center rounded-xl border sm:col-span-1 {{ $isPreset ? 'border-dashed border-base-300' : 'border-primary bg-primary/10 font-medium' }}"
                x-on:click="other = ! other">
            @if ($isPreset)
                <span class="text-sm font-medium">Other</span>
            @else
                <span class="text-lg leading-none font-semibold tabular-nums">{{ $value }}</span>
                <span class="text-[11px] opacity-70">Other</span>
            @endif
        </button>
    </div>
    <form x-show="other" x-cloak wire:submit="{{ $save }}" x-on:submit="other = false" class="mt-2 flex gap-2">
        <input type="number" min="1" step="1" inputmode="numeric" aria-label="{{ $label }}, other value"
               class="input min-h-11 w-32" wire:model="{{ $model }}">
        <button type="submit" class="btn btn-primary min-h-11">Save</button>
    </form>
</div>
