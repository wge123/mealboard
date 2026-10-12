{{--
    Select mode's action bar: applies markMany(picked, false|true).
    Expects: $offLabel, $onLabel. The wrapper must carry x-data="tileSelect".
--}}
<div x-show="selecting" x-cloak x-transition
     class="fixed inset-x-4 bottom-24 z-40 mx-auto flex max-w-md items-center gap-2 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-xl lg:bottom-8">
    <span class="flex-1 px-2 text-sm"><b x-text="picked.length">0</b> picked</span>
    <button type="button" class="btn btn-sm min-h-11" :disabled="picked.length === 0"
            x-on:click="apply(ids => $wire.markMany(ids, false))">{{ $offLabel }}</button>
    <button type="button" class="btn btn-primary btn-sm min-h-11" :disabled="picked.length === 0"
            x-on:click="apply(ids => $wire.markMany(ids, true))">{{ $onLabel }}</button>
</div>
