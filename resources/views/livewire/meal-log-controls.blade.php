{{-- Quick log for one meal: the kit's Ate it / Skipped switch (pressed option green, no red), then small herb-green
     stars and a quiet note field once the meal is answered. The switch only disables while a save runs. --}}
<div class="mt-2">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <x-ui.segmented label="Ate it?" busy="setAte">
            <x-ui.segmented.option tone="primary" :pressed="$ateIt === true" wire:click="setAte(true)"><x-tabler-check class="size-3.5" /> Ate it</x-ui.segmented.option>
            <x-ui.segmented.option tone="primary" :pressed="$ateIt === false" wire:click="setAte(false)"><x-tabler-x class="size-3.5" /> Skipped</x-ui.segmented.option>
        </x-ui.segmented>

        @if ($ateIt !== null)
            {{-- Stars stay small; the 44px buttons pad the hit area. --}}
            <div class="flex" role="group" aria-label="Rating">
                @foreach (range(1, 5) as $star)
                    @php($on = $rating !== null && $star <= $rating)
                    <button type="button"
                            class="grid min-h-11 min-w-8 place-items-center disabled:opacity-60 sm:min-h-8 {{ $on ? 'text-primary' : 'text-base-content/30 hover:text-base-content/60' }}"
                            wire:click="setRating({{ $star }})"
                            wire:loading.attr="disabled" wire:target="setRating"
                            aria-label="Rate {{ $star }} of 5">
                        @if ($on)<x-tabler-star-filled class="size-4" data-rating-star="on" />@else<x-tabler-star class="size-4" data-rating-star="off" />@endif
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($ateIt !== null)
        <input type="text" class="input input-ghost input-sm mt-1 min-h-11 w-full px-1 sm:min-h-8" maxlength="500"
               placeholder="Add a note" wire:model.blur="notes" aria-label="Log note">
    @endif
</div>
