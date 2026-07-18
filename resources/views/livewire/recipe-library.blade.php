<div>
    <h1 class="h3 mb-3">Recipes</h1>

    <div class="row g-2 mb-3">
        <div class="col-12 col-md-4">
            <input type="search" class="form-control" placeholder="Search title or description…"
                   wire:model.live.debounce.300ms="search" aria-label="Search recipes">
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" wire:model.live="mealType" aria-label="Meal type">
                <option value="">Any meal</option>
                @foreach ($mealTypes as $type)
                    <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" wire:model.live="cuisine" aria-label="Cuisine">
                <option value="">Any cuisine</option>
                @foreach ($cuisines as $option)
                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" wire:model.live="tag" aria-label="Tag">
                <option value="">Any tag</option>
                @foreach ($tags as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" wire:model.live="minRating" aria-label="Minimum rating">
                <option value="">Any rating</option>
                @foreach ([5, 4, 3, 2] as $stars)
                    <option value="{{ $stars }}">{{ $stars }}★ &amp; up</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($recipes->isEmpty())
        <p class="text-muted">No recipes match.</p>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Meal</th>
                        <th>Cuisine</th>
                        <th>Time</th>
                        <th>Tags</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recipes as $recipe)
                        <tr>
                            <td>
                                <a class="text-decoration-none fw-semibold" href="{{ url('/recipes/'.$recipe->id) }}">
                                    {{ $recipe->title }}
                                </a>
                            </td>
                            <td>{{ ucfirst($recipe->meal_type->value) }}</td>
                            <td>{{ $recipe->cuisine ? ucfirst($recipe->cuisine) : '—' }}</td>
                            <td class="text-nowrap">{{ $recipe->prep_minutes + $recipe->cook_minutes }} min</td>
                            <td>
                                @foreach ($recipe->tags ?? [] as $recipeTag)
                                    <span class="badge text-bg-light border">{{ $recipeTag }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
