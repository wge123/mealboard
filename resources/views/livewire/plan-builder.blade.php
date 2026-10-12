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

        @php
            $cellKey = fn ($day, $slot) => $day->toDateString().'|'.$slot->value;
            $openCell = $pickerDate && $pickerSlot ? $pickerDate.'|'.$pickerSlot : null;
            $minutesOf = fn ($recipe) => (int) $recipe->prep_minutes + (int) $recipe->cook_minutes;
            // The pinned meal column: its shadows paint over the gutter and the gap beside it, so scrolled days never peek out.
            $pin = 'sticky left-0 z-10 bg-base-100 shadow-[-1.5rem_0_0_var(--color-base-100),0.5rem_0_0_var(--color-base-100)]';
        @endphp

        <p class="mb-2 text-sm text-base-content/70">{{ $meals->count() }} of {{ $days->count() * count($slots) }} meals planned</p>

        {{-- Meals by days. On phones the meal column stays put and the days
             scroll sideways, snapping to whole days. The scroll padding is
             the gutter, the meal column and two cell gaps, so a snapped day
             starts just right of the meal column. --}}
        <div class="-mx-4 snap-x snap-mandatory scroll-ps-[6.25rem] overflow-x-auto px-4 pb-2 lg:mx-0 lg:overflow-visible lg:px-0">
            <table class="w-full min-w-[calc(4.5rem+5*9.5rem)] table-fixed border-separate border-spacing-1.5">
                <colgroup><col class="w-18"></colgroup>
                <thead>
                    <tr>
                        <th class="{{ $pin }}"><span class="sr-only">Meal</span></th>
                        @foreach ($days as $day)
                            <th scope="col" class="snap-start text-left text-sm font-semibold {{ $day->isToday() ? 'text-primary' : '' }}">
                                {{ $day->format('D') }} <span class="font-normal text-base-content/60">{{ $day->format('j M') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($slots as $slot)
                        <tr style="--slot-accent: var(--meal-{{ $slot->value }})">
                            <th scope="row" class="slot-label {{ $pin }} pe-1 text-left text-[10px] font-semibold tracking-widest uppercase">{{ $slot->value }}</th>
                            @foreach ($days as $day)
                                @php
                                    $meal = $meals->get($cellKey($day, $slot));
                                    $label = $slot->value.' '.$day->format('l');
                                    $open = $openCell === $cellKey($day, $slot);
                                    $tile = 'flex h-24 w-full flex-col justify-between gap-1 rounded-xl p-2 text-left transition '.($open ? 'ring-2 ring-primary ring-offset-2 ring-offset-base-100 ' : '');
                                @endphp
                                <td class="align-top">
                                    @if ($meal)
                                        @php
                                            $minutes = $minutesOf($meal->recipe);
                                            $facts = collect([$minutes > 0 ? $minutes.' min' : null, $meal->recipe->cuisine ? \Illuminate\Support\Str::ucfirst($meal->recipe->cuisine) : null])->filter()->implode(' · ');
                                        @endphp
                                        @if ($editable || ($loggable && $day->lte(today())))
                                            <button type="button" class="slot-tile {{ $tile }}" aria-label="{{ $label }}: {{ $meal->recipe->title }}"
                                                    wire:click="{{ $editable ? 'openPicker' : 'openMeal' }}('{{ $day->toDateString() }}', '{{ $slot->value }}')">
                                                <span class="line-clamp-3 text-sm leading-tight font-semibold">{{ $meal->recipe->title }}</span>
                                                <span class="truncate text-[11px] text-base-content/70">{{ $facts }}</span>
                                            </button>
                                        @else
                                            <a href="{{ route('recipes.show', $meal->recipe) }}" class="slot-tile {{ $tile }} hover:brightness-95">
                                                <span class="line-clamp-3 text-sm leading-tight font-semibold">{{ $meal->recipe->title }}</span>
                                                <span class="truncate text-[11px] text-base-content/70">{{ $facts }}</span>
                                            </a>
                                        @endif
                                    @elseif ($editable)
                                        <button type="button" class="{{ $tile }} items-center justify-center border border-dashed border-base-300 text-xl text-base-content/50 hover:border-base-content/40"
                                                wire:click="openPicker('{{ $day->toDateString() }}', '{{ $slot->value }}')"
                                                aria-label="Add {{ $label }}">+</button>
                                    @else
                                        <div class="grid h-24 place-items-center rounded-xl border border-dashed border-base-300 text-base-content/40">—</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr>
                        <th scope="row" class="{{ $pin }} pe-1 text-left text-[10px] font-semibold tracking-widest text-base-content/60 uppercase">Cook time</th>
                        @foreach ($days as $day)
                            @php
                                $dayMinutes = collect($slots)->sum(fn ($slot) => $meals->has($cellKey($day, $slot)) ? $minutesOf($meals->get($cellKey($day, $slot))->recipe) : 0);
                            @endphp
                            <td class="px-2 text-xs text-base-content/70">{{ $dayMinutes > 0 ? $dayMinutes.' min' : '—' }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- The tray: recipes to pick on a draft week, the meal to log on a locked one.
             It sits above the phone tab bar. --}}
        @if ($openCell)
            @php
                $trayDay = \Illuminate\Support\Carbon::parse($pickerDate);
                $trayMeal = $meals->get($openCell);
            @endphp
            <div class="fixed inset-x-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-40 max-h-[50vh] overflow-y-auto rounded-t-3xl lg:max-h-[60vh] border-t border-base-300 bg-base-100 px-4 pt-4 pb-6 shadow-2xl lg:bottom-0"
                 role="dialog" aria-label="{{ ucfirst($pickerSlot) }} {{ $trayDay->format('l') }}"
                 x-data x-on:keydown.escape.window="$wire.closePicker()">
                <div class="mx-auto max-w-5xl">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <div class="me-auto" style="--slot-accent: var(--meal-{{ $pickerSlot }})">
                            <div class="slot-label text-[10px] font-semibold tracking-widest uppercase">{{ $pickerSlot }}</div>
                            <h3 class="font-[family-name:var(--font-display)] text-lg font-semibold">{{ $trayDay->format('D j M') }}</h3>
                        </div>
                        @if ($editable)
                            <input type="search" class="input min-h-11 w-44 sm:w-64" placeholder="Search recipes…"
                                   wire:model.live.debounce.300ms="pickerSearch" aria-label="Search recipes">
                            @if ($trayMeal)
                                <button type="button" class="btn btn-ghost min-h-11 text-error"
                                        wire:click="clearSlot('{{ $pickerDate }}', '{{ $pickerSlot }}')"
                                        wire:loading.attr="disabled" wire:target="clearSlot">Clear</button>
                            @endif
                        @endif
                        <button type="button" class="btn btn-circle btn-ghost min-h-11 min-w-11" wire:click="closePicker" aria-label="Close">&times;</button>
                    </div>

                    @if (! $editable && $trayMeal)
                        <a href="{{ route('recipes.show', $trayMeal->recipe) }}" class="font-[family-name:var(--font-display)] text-xl font-semibold hover:text-primary">{{ $trayMeal->recipe->title }}</a>
                        <div data-test="log-meal-{{ $trayMeal->id }}">
                            <livewire:meal-log-controls :planned-meal="$trayMeal" :key="'log-'.$trayMeal->id" />
                        </div>
                    @elseif ($pickerRecipes->isEmpty())
                        <p class="py-4 text-center text-sm text-base-content/70">No approved recipes match this slot.</p>
                    @else
                        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                            @foreach ($pickerRecipes as $recipe)
                                @php
                                    $minutes = $minutesOf($recipe);
                                    $uses = $usesThisWeek->get($recipe->id, 0);
                                @endphp
                                <li wire:key="pick-{{ $recipe->id }}">
                                    <button type="button"
                                            class="flex h-full min-h-24 w-full flex-col justify-between gap-1 rounded-xl border border-base-300 p-2 text-left hover:border-primary aria-pressed:border-primary aria-pressed:bg-primary/10"
                                            aria-pressed="{{ $trayMeal?->recipe_id === $recipe->id ? 'true' : 'false' }}"
                                            wire:click="choose({{ $recipe->id }})"
                                            wire:loading.attr="disabled" wire:target="choose">
                                        <span class="line-clamp-3 text-sm leading-tight font-medium">{{ $recipe->title }}</span>
                                        {{-- Badges wrap onto two lines inside a narrow tile instead of overflowing. --}}
                                        <span class="flex flex-wrap gap-1 [&_.badge]:h-auto [&_.badge]:py-0.5 [&_.badge]:text-left [&_.badge]:leading-tight">
                                            @include('livewire.partials.missing-tools-badge', ['missing' => $missingTools[$recipe->id]])
                                            @foreach ($avoidedIngredients[$recipe->id] as $word)
                                                <span class="badge badge-error badge-soft badge-sm" data-avoided-badge>contains: {{ $word }}</span>
                                            @endforeach
                                        </span>
                                        <span class="flex justify-between gap-1 text-[11px] text-base-content/70">
                                            <span>{{ $minutes > 0 ? $minutes.' min' : '' }}</span>
                                            @if ($uses > 0)
                                                <span>×{{ $uses }} this week</span>
                                            @endif
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
