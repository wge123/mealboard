<div class="mx-auto" style="max-width: 480px;">
    <h1 class="h4 mb-3 text-center">Approve recipes</h1>

    @if ($recipe === null)
        <div class="text-center text-muted py-5">
            <p class="display-6 mb-2">All caught up</p>
            <p class="mb-3">Nothing pending to review.</p>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('recipes.index') }}">Back to recipes</a>
        </div>
    @else
        @php($videoId = $recipe->youtubeVideoId())

        {{-- wire:key swaps the element per recipe so Alpine re-initializes and the fade-in runs between cards. --}}
        <div class="card shadow-sm" wire:key="card-{{ $recipe->id }}"
             x-data="{ shown: false }"
             x-init="$nextTick(() => shown = true)"
             x-show="shown"
             x-transition.opacity.duration.300ms
             @keydown.window="
                 if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || $event.target.isContentEditable) return;
                 if ($event.key === 'a' || $event.key === 'A') $wire.approve({{ $recipe->id }});
                 if ($event.key === 'r' || $event.key === 'R') $wire.reject({{ $recipe->id }});
             ">
            @if ($videoId !== null)
                <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener">
                    <img src="https://i.ytimg.com/vi/{{ $videoId }}/hqdefault.jpg" class="card-img-top"
                         alt="Video thumbnail for {{ $recipe->title }}">
                </a>
            @endif

            <div class="card-body">
                <h2 class="h5 card-title mb-2">{{ $recipe->title }}</h2>

                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge text-bg-light border">{{ $recipe->prep_minutes + $recipe->cook_minutes }} min total</span>
                    <span class="badge text-bg-light border">{{ ucfirst($recipe->meal_type->value) }}</span>
                    @if ($recipe->cuisine)
                        <span class="badge text-bg-light border">{{ ucfirst($recipe->cuisine) }}</span>
                    @endif
                </div>

                <p class="card-text">{{ $recipe->description }}</p>

                @if ($videoId !== null)
                    <p class="mb-2">
                        <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener" class="text-decoration-none">
                            Watch on YouTube &rarr;
                        </a>
                    </p>
                @endif

                @if ($recipe->ingredients->isNotEmpty())
                    <p class="text-muted small mb-1">Key ingredients</p>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @foreach ($recipe->ingredients as $ingredient)
                            <span class="badge text-bg-light border">{{ $ingredient->name }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="d-grid gap-2 d-sm-flex">
                    <button type="button" class="btn btn-outline-danger btn-lg flex-fill"
                            wire:click="reject({{ $recipe->id }})">
                        Reject <kbd class="ms-1">R</kbd>
                    </button>
                    <button type="button" class="btn btn-success btn-lg flex-fill"
                            wire:click="approve({{ $recipe->id }})">
                        Approve <kbd class="ms-1">A</kbd>
                    </button>
                </div>
            </div>
        </div>

        <p class="text-center text-muted small mt-3 d-none d-sm-block">
            Press <kbd>A</kbd> to approve, <kbd>R</kbd> to reject.
        </p>
    @endif
</div>
