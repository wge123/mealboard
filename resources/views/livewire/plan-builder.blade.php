<div>
    {{-- Header: week title + status, week picker, actions. --}}
    <div class="mb-1 flex flex-wrap items-center gap-x-3 gap-y-2">
        <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">
            {{ $plan ? 'Week of '.$plan->week_start_date->format('j M') : 'Plan' }}
        </h1>

        @if ($plan)
            <span class="badge badge-soft {{ ['draft' => 'badge-warning', 'locked' => 'badge-success', 'completed' => 'badge-neutral'][$plan->status->value] }}">
                {{ ucfirst($plan->status->value) }}
            </span>
        @endif

        @if ($plans->isNotEmpty())
            <select class="select select-sm ms-auto min-h-11 w-auto" wire:model.live="planId" aria-label="Week">
                @foreach ($plans as $option)
                    <option value="{{ $option->id }}">
                        Week of {{ $option->week_start_date->format('j M Y') }} ({{ $option->status->value }})
                    </option>
                @endforeach
            </select>
        @endif
    </div>

    @if ($plan)
        <p class="mb-4 text-sm opacity-60">
            Mon {{ $plan->week_start_date->format('j M') }} – Fri {{ $plan->week_start_date->copy()->addDays(4)->format('j M Y') }}
        </p>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($plan && $editable)
            <button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" wire:click="autoFill"
                    wire:loading.attr="disabled" wire:target="autoFill">
                <span wire:loading wire:target="autoFill" class="loading loading-spinner loading-xs"></span>
                Auto-fill empty slots
            </button>
            <button type="button" class="btn btn-primary btn-sm min-h-11" wire:click="lock"
                    wire:confirm="Lock this week? It becomes read-only for both of you."
                    wire:loading.attr="disabled" wire:target="lock">
                <span wire:loading wire:target="lock" class="loading loading-spinner loading-xs"></span>
                Lock week
            </button>
        @endif

        @if ($plan && $completable)
            <button type="button" class="btn btn-success btn-sm min-h-11" wire:click="markCompleted"
                    wire:confirm="Mark this week as completed?"
                    wire:loading.attr="disabled" wire:target="markCompleted">
                <span wire:loading wire:target="markCompleted" class="loading loading-spinner loading-xs"></span>
                Mark completed
            </button>
        @endif

        @if ($plan && ! $editable)
            <a class="btn btn-primary btn-sm min-h-11" href="{{ route('plan.shopping-list', $plan) }}">
                Shopping list
            </a>
        @endif

        <button type="button" class="btn btn-ghost btn-sm min-h-11 border border-base-300" wire:click="createNextWeek"
                wire:loading.attr="disabled" wire:target="createNextWeek">
            <span wire:loading wire:target="createNextWeek" class="loading loading-spinner loading-xs"></span>
            Create next week
        </button>
    </div>

    @if ($publishWarning)
        <div class="alert alert-warning mb-4" role="alert">{{ $publishWarning }}</div>
    @endif

    @if (! $plan)
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">Nothing planned yet</p>
            <p class="mt-1 text-sm opacity-60">No meal plans yet. Create next week's draft to get started.</p>
        </div>
    @else
        @if ($breakfastsOftenSkipped)
            <div class="alert alert-info alert-soft mb-4 py-2 text-sm" role="status">
                Breakfasts often skipped — picking faster ones.
            </div>
        @endif

        {{-- Day cards: stacked on phones, five columns on lg+. --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 lg:gap-3">
            @foreach ($days as $day)
                @php($isToday = $day->isToday())
                <section class="rounded-2xl border bg-base-200 {{ $isToday ? 'border-primary/50' : 'border-base-300' }}">
                    <header class="flex items-center justify-between px-3 pt-3 pb-1">
                        <h2 class="text-sm font-semibold {{ $isToday ? 'text-primary' : '' }}">
                            {{ $day->format('D') }}
                            <span class="font-normal opacity-60">{{ $day->format('j M') }}</span>
                        </h2>
                        @if ($isToday)
                            <span class="badge badge-primary badge-sm">Today</span>
                        @endif
                    </header>

                    <div class="flex flex-col gap-2 p-3 pt-2">
                        @foreach ($slots as $slot)
                            @php($meal = $meals->get($day->toDateString().'|'.$slot->value))
                            <div class="slot-row rounded-xl bg-base-100 p-3" style="--slot-accent: var(--meal-{{ $slot->value }})">
                                <div class="slot-label text-[10px] font-semibold tracking-widest uppercase">{{ $slot->value }}</div>

                                @if ($meal)
                                    <a href="{{ route('recipes.show', $meal->recipe) }}"
                                       class="mt-0.5 block font-[family-name:var(--font-display)] leading-snug font-semibold hover:text-primary">
                                        {{ $meal->recipe->title }}
                                    </a>

                                    @php($minutes = (int) $meal->recipe->prep_minutes + (int) $meal->recipe->cook_minutes)
                                    @if ($minutes > 0)
                                        <span class="badge badge-ghost badge-sm mt-1.5">{{ $minutes }} min</span>
                                    @endif

                                    @if ($editable)
                                        <div class="mt-1.5 flex gap-1">
                                            <button type="button" class="btn btn-ghost btn-sm min-h-11 px-2"
                                                    wire:click="openPicker('{{ $day->toDateString() }}', '{{ $slot->value }}')">
                                                Swap
                                            </button>
                                            <button type="button" class="btn btn-ghost btn-sm min-h-11 px-2 text-error"
                                                    wire:click="clearSlot('{{ $day->toDateString() }}', '{{ $slot->value }}')"
                                                    wire:loading.attr="disabled" wire:target="clearSlot">
                                                Clear
                                            </button>
                                        </div>
                                    @endif

                                    {{-- Past slots (date-only, today included) on locked/completed weeks get quick-log controls. --}}
                                    @if ($loggable && $day->lte(today()))
                                        <livewire:meal-log-controls :planned-meal="$meal" :key="'log-'.$meal->id" />
                                    @endif
                                @else
                                    @if ($editable)
                                        <button type="button"
                                                class="btn btn-ghost mt-1.5 min-h-11 w-full justify-start border border-dashed border-base-300 font-normal text-base-content/60"
                                                wire:click="openPicker('{{ $day->toDateString() }}', '{{ $slot->value }}')">
                                            + Add
                                        </button>
                                    @else
                                        <div class="mt-1 text-sm text-base-content/40">—</div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Recipe picker: Livewire-driven daisyUI modal. --}}
        @if ($pickerDate && $pickerSlot)
            <div class="modal modal-open modal-bottom sm:modal-middle" role="dialog" aria-modal="true">
                <div class="modal-box border border-base-300">
                    <div class="slot-label text-[10px] font-semibold tracking-widest uppercase" style="--slot-accent: var(--meal-{{ $pickerSlot }})">
                        {{ $pickerSlot }}
                    </div>
                    <h3 class="mt-0.5 font-[family-name:var(--font-display)] text-xl font-semibold">
                        Pick a recipe — {{ \Illuminate\Support\Carbon::parse($pickerDate)->format('D j M') }}
                    </h3>

                    <input type="search" class="input mt-3 min-h-11 w-full" placeholder="Search recipes…"
                           wire:model.live.debounce.300ms="pickerSearch" aria-label="Search recipes">

                    <div class="mt-3 max-h-80 overflow-y-auto">
                        @if ($pickerRecipes->isEmpty())
                            <p class="py-4 text-center text-sm opacity-60">No approved recipes match this slot.</p>
                        @else
                            <div class="flex flex-col gap-1.5">
                                @foreach ($pickerRecipes as $recipe)
                                    @php($minutes = (int) $recipe->prep_minutes + (int) $recipe->cook_minutes)
                                    <button type="button"
                                            class="slot-row flex min-h-11 w-full items-center justify-between gap-2 rounded-lg bg-base-200 px-3 py-2 text-left hover:bg-base-300"
                                            style="--slot-accent: var(--meal-{{ $recipe->meal_type->value }})"
                                            wire:click="choose({{ $recipe->id }})"
                                            wire:loading.attr="disabled" wire:target="choose">
                                        <span class="font-medium">{{ $recipe->title }}</span>
                                        @if ($minutes > 0)
                                            <span class="badge badge-ghost badge-sm shrink-0">{{ $minutes }} min</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost min-h-11" wire:click="closePicker">Cancel</button>
                    </div>
                </div>
                <button type="button" class="modal-backdrop" wire:click="closePicker" aria-label="Close"></button>
            </div>
        @endif
    @endif
</div>
