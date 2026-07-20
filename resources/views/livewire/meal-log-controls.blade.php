{{-- Phone-first quick log: big tap targets, one row of yes/no, stars appear after. --}}
<div class="mt-2 border-t border-base-300 pt-2">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs opacity-60">Ate it?</span>
        <div class="join">
            <button type="button"
                    class="btn join-item btn-sm min-h-11 {{ $ateIt === true ? 'btn-success' : 'btn-ghost border border-base-300' }}"
                    wire:click="setAte(true)"
                    wire:loading.attr="disabled" wire:target="setAte">
                <span wire:loading wire:target="setAte(true)" class="loading loading-spinner loading-xs"></span>
                Yes
            </button>
            <button type="button"
                    class="btn join-item btn-sm min-h-11 {{ $ateIt === false ? 'btn-error' : 'btn-ghost border border-base-300' }}"
                    wire:click="setAte(false)"
                    wire:loading.attr="disabled" wire:target="setAte">
                <span wire:loading wire:target="setAte(false)" class="loading loading-spinner loading-xs"></span>
                No
            </button>
        </div>
    </div>

    @if ($ateIt !== null)
        {{-- Stars stay visually small; the flexed buttons pad the hit area. --}}
        <div class="mt-1 flex" role="group" aria-label="Rating">
            @foreach (range(1, 5) as $star)
                <button type="button"
                        class="flex min-h-11 min-w-9 flex-1 items-center justify-center text-lg {{ $rating !== null && $star <= $rating ? 'text-warning' : 'text-base-content/30' }}"
                        wire:click="setRating({{ $star }})"
                        wire:loading.attr="disabled" wire:target="setRating"
                        aria-label="Rate {{ $star }} of 5">
                    {{ $rating !== null && $star <= $rating ? '★' : '☆' }}
                </button>
            @endforeach
        </div>

        <input type="text" class="input input-ghost input-sm mt-1 min-h-11 w-full px-1" maxlength="500"
               placeholder="Note (optional)" wire:model.blur="notes" aria-label="Log note">
    @endif
</div>
