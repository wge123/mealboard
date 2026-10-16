<div>
    @php
        $ordered = $plans->sortBy('week_start_date')->values();
        $at = $plan ? $ordered->search(fn ($p) => $p->id === $plan->id) : false;
        $prevPlan = $at !== false ? $ordered->get($at - 1) : null;
        $nextPlan = $at !== false ? $ordered->get($at + 1) : null;

        $cellKey = fn ($day, $slot) => $day->toDateString().'|'.$slot->value;
        $openCell = $pickerDate && $pickerSlot ? $pickerDate.'|'.$pickerSlot : null;
        $minutesOf = fn ($recipe) => (int) $recipe->prep_minutes + (int) $recipe->cook_minutes;
        $dayMinutes = fn ($day) => collect($slots)->sum(fn ($slot) => $meals->has($cellKey($day, $slot)) ? $minutesOf($meals->get($cellKey($day, $slot))->recipe) : 0);
        $hm = fn (int $min) => $min >= 60 ? intdiv($min, 60).'h'.($min % 60 ? ' '.($min % 60).'m' : '') : $min.' min';
        $total = $days->count() * count($slots);
        $weekMinutes = $days->sum(fn ($day) => $dayMinutes($day));

        // The week's state, in the words the screen uses. A draft with meals in it is a suggestion to confirm.
        $state = match (true) {
            ! $plan => 'none',
            $plan->status === \App\Enums\MealPlanStatus::Draft && $meals->isEmpty() => 'empty',
            $plan->status === \App\Enums\MealPlanStatus::Draft => 'suggested',
            $plan->status === \App\Enums\MealPlanStatus::Locked => 'locked',
            default => 'completed',
        };
        $badge = [
            'empty' => ['Draft', 'neutral'],
            'suggested' => ['Suggested', 'primary'],
            'locked' => ['Locked', 'neutral'],
            'completed' => ['Done', 'neutral'],
        ][$state] ?? null;

        // A past meal of a locked or completed week is eaten, skipped, or not logged yet; anything else has no mark.
        $logState = function ($meal, $day) use ($loggable) {
            if (! $loggable || $day->isFuture()) {
                return null;
            }

            return $meal->logs->isEmpty() ? 'open' : ($meal->logs->contains('ate_it', true) ? 'ate' : 'skipped');
        };
    @endphp

    <x-ui.page-header
        :title="$plan ? 'Week of '.$plan->week_start_date->format('j M') : 'Plan'"
        :subtitle="$plan ? 'Mon '.$plan->week_start_date->format('j M').' – Fri '.$plan->week_start_date->copy()->addDays(4)->format('j M Y').' · '.$meals->count().' of '.$total.' meals'.($weekMinutes ? ' · '.$hm($weekMinutes).' of cooking' : '') : null"
    >
        <x-slot:actions>
            @if ($badge)
                <x-ui.badge :tone="$badge[1]">{{ $badge[0] }}</x-ui.badge>
            @endif
            @if ($plans->isNotEmpty())
                <div class="flex items-center gap-1">
                    @if ($prevPlan)
                        <x-ui.button square label="Previous week" icon="tabler-chevron-left" wire:click="$set('planId', {{ $prevPlan->id }})" />
                    @else
                        <x-ui.button square label="Previous week" icon="tabler-chevron-left" disabled />
                    @endif
                    <select class="select min-h-11 w-auto sm:min-h-9" wire:model.live="planId" aria-label="Week">
                        @foreach ($plans as $option)
                            <option value="{{ $option->id }}">
                                Week of {{ $option->week_start_date->format('j M Y') }} ({{ $option->status->value }})
                            </option>
                        @endforeach
                    </select>
                    @if ($nextPlan)
                        <x-ui.button square label="Next week" icon="tabler-chevron-right" wire:click="$set('planId', {{ $nextPlan->id }})" />
                    @else
                        <x-ui.button square label="Next week" icon="tabler-chevron-right" disabled />
                    @endif
                </div>
            @endif
            <x-ui.button icon="tabler-plus" wire:click="createNextWeek" busy="createNextWeek">Plan next week</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($publishWarning)
        <x-ui.alert tone="warning" class="mb-4">{{ $publishWarning }}</x-ui.alert>
    @endif

    @if (! $plan)
        <x-ui.card>
            <x-ui.empty-state title="Nothing planned yet" icon="tabler-calendar">
                No meal plans yet. Create next week's draft to get started.
            </x-ui.empty-state>
        </x-ui.card>
    @else
        @if ($breakfastsOftenSkipped)
            <x-ui.alert class="mb-4">Breakfasts often skipped — picking faster ones.</x-ui.alert>
        @endif

        @include('livewire.partials.week-notice')

        {{-- Desktop: the ledger, a row per day. --}}
        <x-ui.card flush class="hidden lg:block">
            <x-ui.table class="table-fixed" aria-label="Meals by day">
                <x-slot:head>
                    <tr>
                        <x-ui.table.th class="w-36">Day</x-ui.table.th>
                        @foreach ($slots as $slot)
                            <x-ui.table.th class="border-s border-base-300">
                                <span class="flex items-center gap-2"><span class="size-2 shrink-0 rounded-full" style="background: var(--meal-{{ $slot->value }})" aria-hidden="true"></span>{{ $slot->value }}</span>
                            </x-ui.table.th>
                        @endforeach
                        <x-ui.table.th numeric class="w-28 border-s border-base-300">Cook</x-ui.table.th>
                        {{-- Ticket 10: the "Looks good" tick column goes here, on a suggested draft only. --}}
                    </tr>
                </x-slot:head>
                <tbody>
                    @foreach ($days as $day)
                        <x-ui.table.row wire:key="day-{{ $day->toDateString() }}" class="{{ $day->isToday() ? 'bg-primary/5' : '' }}">
                            <th scope="row" class="px-4 py-2.5 text-start align-top font-medium {{ $day->isToday() ? 'text-primary' : '' }}">
                                {{ $day->format('D j M') }}
                                @if ($day->isToday())<span class="block text-xs font-normal">Today</span>@endif
                            </th>
                            @foreach ($slots as $slot)
                                <td class="h-14 border-s border-base-300 p-0! align-top">
                                    @include('livewire.partials.week-cell', ['class' => 'flex size-full min-h-14 flex-col justify-between gap-0.5 px-4 py-2.5 text-start hover:bg-base-200'])
                                </td>
                            @endforeach
                            <td class="num border-s border-base-300 align-top text-base-content/70">{{ $dayMinutes($day) ? $hm($dayMinutes($day)) : '—' }}</td>
                        </x-ui.table.row>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>

        {{-- Phone: the same ledger as a grouped list, a day per group. --}}
        <x-ui.card flush class="overflow-hidden lg:hidden">
            @foreach ($days as $day)
                <div wire:key="day-list-{{ $day->toDateString() }}" class="border-b border-base-300 last:border-b-0">
                    <div class="flex min-h-11 items-center gap-2 bg-base-200 px-4 font-medium">
                        <span class="{{ $day->isToday() ? 'text-primary' : '' }}">{{ $day->format('D j M') }}</span>
                        @if ($day->isToday())<x-ui.badge>Today</x-ui.badge>@endif
                        <span class="num ms-auto text-base-content/70">{{ $dayMinutes($day) ? $hm($dayMinutes($day)) : '' }}</span>
                        {{-- Ticket 10: the day's "Looks good" tick goes here, on a suggested draft only. --}}
                    </div>
                    @foreach ($slots as $slot)
                        <div class="flex min-h-11 items-stretch border-t border-base-300">
                            <span class="flex w-10 shrink-0 items-center justify-center"><span class="size-2 shrink-0 rounded-full" style="background: var(--meal-{{ $slot->value }})"></span><span class="sr-only">{{ ucfirst($slot->value) }}</span></span>
                            @include('livewire.partials.week-cell', ['class' => 'flex min-h-11 flex-1 items-center justify-between gap-3 py-2 pe-4 text-start [&>span:last-child]:shrink-0'])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </x-ui.card>

        {{-- The tray: recipes to pick on a draft week, the meal to log on a locked one.
             It sits above the phone tab bar. --}}
        @if ($openCell)
            @php
                $trayDay = \Illuminate\Support\Carbon::parse($pickerDate);
                $trayMeal = $meals->get($openCell);
            @endphp
            <div class="fixed inset-x-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-40 max-h-[50vh] overflow-y-auto rounded-t-box lg:max-h-[60vh] border-t border-base-300 bg-base-100 px-4 pt-4 pb-6 shadow-lg lg:bottom-0"
                 role="dialog" aria-label="{{ ucfirst($pickerSlot) }} {{ $trayDay->format('l') }}"
                 x-data x-on:keydown.escape.window="$wire.closePicker()">
                <div class="mx-auto max-w-5xl">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <div class="me-auto" style="--slot-accent: var(--meal-{{ $pickerSlot }})">
                            <div class="slot-label text-xs font-semibold tracking-widest uppercase">{{ $pickerSlot }}</div>
                            <h3 class="font-display text-lg font-semibold">{{ $trayDay->format('D j M') }}</h3>
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
                        <button type="button" class="btn btn-circle btn-ghost min-h-11 min-w-11" wire:click="closePicker" aria-label="Close"><x-tabler-x class="size-5" /></button>
                    </div>

                    @if (! $editable && $trayMeal)
                        <a href="{{ route('recipes.show', $trayMeal->recipe) }}" class="font-display text-xl font-semibold hover:text-primary">{{ $trayMeal->recipe->title }}</a>
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
                                            class="flex h-full min-h-24 w-full flex-col justify-between gap-1 rounded-box border border-base-300 p-2 text-left hover:border-primary aria-pressed:border-primary aria-pressed:bg-primary/10"
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
                                        <span class="flex justify-between gap-1 text-xs text-base-content/70">
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
