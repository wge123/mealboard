<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Weekly plan</h1>

        <div class="d-flex align-items-center gap-2">
            @if ($plans->isNotEmpty())
                <select class="form-select form-select-sm w-auto" wire:model.live="planId" aria-label="Week">
                    @foreach ($plans as $option)
                        <option value="{{ $option->id }}">
                            Week of {{ $option->week_start_date->format('j M Y') }} ({{ $option->status->value }})
                        </option>
                    @endforeach
                </select>
            @endif
            <button type="button" class="btn btn-primary btn-sm" wire:click="createNextWeek">
                Create next week
            </button>
        </div>
    </div>

    @if (! $plan)
        <p class="text-muted">No meal plans yet. Create next week's draft to get started.</p>
    @else
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="badge text-bg-{{ ['draft' => 'secondary', 'locked' => 'warning', 'completed' => 'success'][$plan->status->value] }}">
                {{ ucfirst($plan->status->value) }}
            </span>
            <span class="text-muted small">
                Mon {{ $plan->week_start_date->format('j M') }} – Fri {{ $plan->week_start_date->copy()->addDays(4)->format('j M Y') }}
            </span>

            <div class="d-flex gap-2 ms-auto">
                @if ($editable)
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="autoFill">
                        Auto-fill empty slots
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" wire:click="lock"
                            wire:confirm="Lock this week? It becomes read-only for both of you.">
                        Lock week
                    </button>
                @endif

                @if ($completable)
                    <button type="button" class="btn btn-success btn-sm" wire:click="markCompleted"
                            wire:confirm="Mark this week as completed?">
                        Mark completed
                    </button>
                @endif
            </div>
        </div>

        {{-- Stacks one day per row on phones, five columns on md+. --}}
        <div class="row row-cols-1 row-cols-md-5 g-3">
            @foreach ($days as $day)
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header py-2">
                            <strong>{{ $day->format('D') }}</strong>
                            <span class="text-muted small">{{ $day->format('j M') }}</span>
                        </div>
                        <div class="card-body p-2 d-flex flex-column gap-2">
                            @foreach ($slots as $slot)
                                @php($meal = $meals->get($day->toDateString().'|'.$slot->value))
                                <div class="border rounded p-2">
                                    <div class="text-uppercase text-muted small">{{ $slot->value }}</div>
                                    @if ($meal)
                                        <a href="{{ route('recipes.show', $meal->recipe) }}" class="d-block small fw-semibold text-decoration-none">
                                            {{ $meal->recipe->title }}
                                        </a>
                                        @if ($editable)
                                            <div class="d-flex gap-1 mt-1">
                                                <button type="button" class="btn btn-outline-secondary btn-sm py-0"
                                                        wire:click="openPicker('{{ $day->toDateString() }}', '{{ $slot->value }}')">
                                                    Swap
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm py-0"
                                                        wire:click="clearSlot('{{ $day->toDateString() }}', '{{ $slot->value }}')">
                                                    Clear
                                                </button>
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-muted small">—</div>
                                        @if ($editable)
                                            <button type="button" class="btn btn-outline-primary btn-sm py-0 mt-1"
                                                    wire:click="openPicker('{{ $day->toDateString() }}', '{{ $slot->value }}')">
                                                Pick
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Recipe picker overlay --}}
        @if ($pickerDate && $pickerSlot)
            <div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                 style="background: rgba(0, 0, 0, .5); z-index: 1050;">
                <div class="card shadow" style="width: min(28rem, 92vw); max-height: 80vh;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            Pick a {{ $pickerSlot }} recipe —
                            {{ \Illuminate\Support\Carbon::parse($pickerDate)->format('D j M') }}
                        </span>
                        <button type="button" class="btn-close" aria-label="Close" wire:click="closePicker"></button>
                    </div>
                    <div class="card-body overflow-auto">
                        <input type="search" class="form-control mb-2" placeholder="Search recipes…"
                               wire:model.live.debounce.300ms="pickerSearch" aria-label="Search recipes">

                        @if ($pickerRecipes->isEmpty())
                            <p class="text-muted small mb-0">No approved recipes match this slot.</p>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach ($pickerRecipes as $recipe)
                                    <button type="button" class="list-group-item list-group-item-action"
                                            wire:click="choose({{ $recipe->id }})">
                                        {{ $recipe->title }}
                                        <span class="text-muted small">({{ $recipe->meal_type->value }})</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
