{{--
    Select mode's action bar: applies markMany(picked, false|true), or stops
    selecting. Expects: $offLabel, $onLabel. The wrapper must carry
    x-data="tileSelect". It sits above the phone tab bar, as the recipe
    library's add button does.
--}}
<div x-show="selecting" x-cloak x-transition
     class="fixed inset-x-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-40 mx-auto flex max-w-md items-center gap-2 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg lg:bottom-8">
    <x-ui.button square variant="ghost" icon="tabler-x" label="Stop selecting" x-on:click="toggleSelecting()" />
    <span class="flex-1 text-sm"><b x-text="picked.length">0</b> picked</span>
    <x-ui.button x-bind:disabled="picked.length === 0"
            x-on:click="apply(ids => $wire.markMany(ids, false))">{{ $offLabel }}</x-ui.button>
    <x-ui.button variant="primary" x-bind:disabled="picked.length === 0"
            x-on:click="apply(ids => $wire.markMany(ids, true))">{{ $onLabel }}</x-ui.button>
</div>
