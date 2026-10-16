{{-- The Light / Dark / System switch of the account menus. Reads `theme` and `setTheme` from the Alpine scope on <body> (layouts/app). --}}
<x-ui.segmented label="Theme" {{ $attributes->merge(['class' => 'w-full']) }}>
    @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $key => $label)
        <x-ui.segmented.option class="flex-1" x-bind:aria-pressed="theme === '{{ $key }}'" x-on:click="setTheme('{{ $key }}')">{{ $label }}</x-ui.segmented.option>
    @endforeach
</x-ui.segmented>
