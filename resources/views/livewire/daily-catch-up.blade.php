<div>
    <h1 class="h3 mb-1">Catch up on yesterday</h1>
    <p class="text-muted small mb-3">{{ $yesterday->format('l j M') }} — meals you haven't logged yet.</p>

    @if ($meals->isEmpty())
        <p class="text-muted">All caught up — nothing left to log from yesterday.</p>
    @else
        {{-- Single column, capped width: comfortable one-thumb logging on a phone. --}}
        <div class="d-flex flex-column gap-2" style="max-width: 28rem;">
            @foreach ($meals as $meal)
                <div class="card">
                    <div class="card-body p-2">
                        <div class="text-uppercase text-muted small">{{ $meal->slot->value }}</div>
                        <a href="{{ route('recipes.show', $meal->recipe) }}" class="d-block small fw-semibold text-decoration-none">
                            {{ $meal->recipe->title }}
                        </a>
                        <livewire:meal-log-controls :planned-meal="$meal" :key="'catchup-'.$meal->id" />
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
