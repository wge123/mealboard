{{-- Kit: segmented toggle, an either-or switch. `label` names the group for assistive tech. `busy` (a wire:target) disables every
     option while that action runs; options are <x-ui.segmented.option>. --}}
@props(['label', 'busy' => null])

<div role="group" aria-label="{{ $label }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 rounded-field border border-base-300 bg-base-200 p-0.5']) }}>
    {{ $slot }}
</div>
