{{-- Phone-first quick log: big tap targets, one row of yes/no, stars appear after. --}}
<div class="mt-1">
    <div class="d-flex align-items-center gap-1 flex-wrap">
        <span class="text-muted small">Ate it?</span>
        <button type="button"
                class="btn btn-sm py-0 {{ $ateIt === true ? 'btn-success' : 'btn-outline-success' }}"
                wire:click="setAte(true)">
            Yes
        </button>
        <button type="button"
                class="btn btn-sm py-0 {{ $ateIt === false ? 'btn-danger' : 'btn-outline-danger' }}"
                wire:click="setAte(false)">
            No
        </button>
    </div>

    @if ($ateIt !== null)
        <div class="d-flex align-items-center mt-1" role="group" aria-label="Rating">
            @foreach (range(1, 5) as $star)
                <button type="button"
                        class="btn btn-sm py-0 px-1 border-0 fs-6 {{ $rating !== null && $star <= $rating ? 'text-warning' : 'text-secondary' }}"
                        wire:click="setRating({{ $star }})"
                        aria-label="Rate {{ $star }} of 5">
                    {{ $rating !== null && $star <= $rating ? '★' : '☆' }}
                </button>
            @endforeach
        </div>

        <input type="text" class="form-control form-control-sm mt-1" maxlength="500"
               placeholder="Note (optional)" wire:model.blur="notes" aria-label="Log note">
    @endif
</div>
