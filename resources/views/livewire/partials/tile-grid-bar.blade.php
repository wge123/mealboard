{{--
    Select mode's action bar: applies markMany(picked, false|true), or stops
    selecting. Expects: $offLabel, $onLabel. The wrapper must carry
    x-data="tileSelect". It sits above the phone tab bar, as the recipe
    library's add button does.
--}}
<div x-show="selecting" x-cloak x-transition
     class="fixed inset-x-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-40 mx-auto flex max-w-md items-center gap-2 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg lg:bottom-8">
    <button type="button" class="btn btn-ghost btn-circle btn-sm min-h-11 min-w-11" x-on:click="toggleSelecting()" aria-label="Stop selecting"><x-tabler-x class="size-5" /></button>
    <span class="flex-1 text-sm"><b x-text="picked.length">0</b> picked</span>
    <button type="button" class="btn btn-sm min-h-11" :disabled="picked.length === 0"
            x-on:click="apply(ids => $wire.markMany(ids, false))">{{ $offLabel }}</button>
    <button type="button" class="btn btn-primary btn-sm min-h-11" :disabled="picked.length === 0"
            x-on:click="apply(ids => $wire.markMany(ids, true))">{{ $onLabel }}</button>
</div>
