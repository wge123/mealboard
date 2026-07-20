<div>
    <h1 class="mb-5 font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Insights</h1>

    {{-- Top-line stat cards. --}}
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs font-medium tracking-wide uppercase opacity-60">Meals logged</div>
            <div class="mt-1 font-[family-name:var(--font-display)] text-3xl font-semibold">{{ $totals['logs'] }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs font-medium tracking-wide uppercase opacity-60">Ate rate</div>
            <div class="mt-1 font-[family-name:var(--font-display)] text-3xl font-semibold">
                {{ $totals['ateRate'] !== null ? $totals['ateRate'].'%' : '—' }}
            </div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs font-medium tracking-wide uppercase opacity-60">Avg rating</div>
            <div class="mt-1 font-[family-name:var(--font-display)] text-3xl font-semibold">
                @if ($totals['avgRating'] !== null)
                    {{ $totals['avgRating'] }} <span class="text-xl text-warning" aria-hidden="true">★</span>
                @else
                    —
                @endif
            </div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs font-medium tracking-wide uppercase opacity-60">Rejection rate</div>
            <div class="mt-1 font-[family-name:var(--font-display)] text-3xl font-semibold">
                {{ $discovered['rate'] !== null ? $discovered['rate'].'%' : '—' }}
            </div>
            <div class="text-xs opacity-50">discovered recipes</div>
        </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
        {{-- Ate vs skipped by slot: meal-accent progress bars. --}}
        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 lg:p-5">
            <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Ate vs skipped by slot</h2>
            @forelse ($slotRates as $label => $row)
                <div class="mt-3">
                    <div class="flex items-baseline justify-between gap-2 text-sm">
                        <span class="slot-label font-medium" style="--slot-accent: var(--meal-{{ $label }})">{{ ucfirst($label) }}</span>
                        <span class="opacity-60">{{ $row['ate'] }} ate · {{ $row['skipped'] }} skipped · {{ $row['rate'] }}%</span>
                    </div>
                    {{-- daisyUI progress fills with currentColor; mix the meal accent toward base-content for contrast. --}}
                    <progress class="progress mt-1 w-full"
                              style="color: color-mix(in oklab, var(--meal-{{ $label }}) 70%, var(--color-base-content))"
                              value="{{ $row['rate'] }}" max="100" aria-label="{{ ucfirst($label) }} ate rate"></progress>
                </div>
            @empty
                <p class="mt-3 text-sm opacity-60">No meal logs yet.</p>
            @endforelse
        </section>

        {{-- Ate vs skipped by weekday: neutral progress bars. --}}
        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 lg:p-5">
            <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Ate vs skipped by weekday</h2>
            @forelse ($weekdayRates as $label => $row)
                <div class="mt-3">
                    <div class="flex items-baseline justify-between gap-2 text-sm">
                        <span class="font-medium">{{ $label }}</span>
                        <span class="opacity-60">{{ $row['ate'] }} ate · {{ $row['skipped'] }} skipped · {{ $row['rate'] }}%</span>
                    </div>
                    <progress class="progress mt-1 w-full" value="{{ $row['rate'] }}" max="100"
                              aria-label="{{ $label }} ate rate"></progress>
                </div>
            @empty
                <p class="mt-3 text-sm opacity-60">No meal logs yet.</p>
            @endforelse
        </section>

        {{-- Top recipes by average rating. --}}
        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 lg:p-5">
            <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Top recipes by average rating</h2>
            @if ($topRecipes->isEmpty())
                <p class="mt-3 text-sm opacity-60">No rated meals yet.</p>
            @else
                <ol class="mt-2 divide-y divide-base-300">
                    @foreach ($topRecipes as $row)
                        <li class="flex min-h-11 items-center gap-3 py-2">
                            <span class="w-7 shrink-0 text-center font-[family-name:var(--font-display)] text-lg font-semibold opacity-50">
                                {{ $loop->iteration }}
                            </span>
                            <a href="{{ route('recipes.show', $row['id']) }}" class="min-w-0 flex-1 truncate font-medium hover:text-primary">
                                {{ $row['title'] }}
                            </a>
                            <span class="shrink-0 text-sm text-warning" aria-hidden="true">{{ str_repeat('★', (int) round($row['avg'])) }}{{ str_repeat('☆', 5 - (int) round($row['avg'])) }}</span>
                            <span class="shrink-0 text-sm tabular-nums opacity-70">{{ $row['avg'] }}</span>
                            <span class="badge badge-ghost badge-sm shrink-0">{{ $row['count'] }} log{{ $row['count'] === 1 ? '' : 's' }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        {{-- Discovered-recipe rejection rate: radial progress. --}}
        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 lg:p-5">
            <h2 class="font-[family-name:var(--font-display)] text-xl font-semibold">Discovered-recipe rejection rate</h2>
            @if ($discovered['rate'] === null)
                <p class="mt-3 text-sm opacity-60">No discovery verdicts yet.</p>
            @else
                <div class="mt-4 flex items-center gap-5">
                    <div class="radial-progress shrink-0 text-error"
                         style="--value: {{ $discovered['rate'] }}; --size: 6rem; --thickness: 0.5rem;"
                         role="progressbar" aria-valuenow="{{ $discovered['rate'] }}" aria-valuemin="0" aria-valuemax="100">
                        <span class="font-[family-name:var(--font-display)] text-xl font-semibold">{{ $discovered['rate'] }}%</span>
                    </div>
                    <p class="text-sm leading-relaxed opacity-80">
                        {{ $discovered['rejected'] }} rejected vs {{ $discovered['approved'] }} approved —
                        <strong>{{ $discovered['rate'] }}% rejected</strong>
                    </p>
                </div>
            @endif
        </section>
    </div>
</div>
