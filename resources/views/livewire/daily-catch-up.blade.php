{{-- Single column, capped width: comfortable one-thumb logging on a phone. --}}
<div class="mx-auto w-full max-w-md">
    <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Catch up on yesterday</h1>
    <p class="mt-1 mb-4 text-sm opacity-60">{{ $yesterday->format('l j M') }} — meals you haven't logged yet.</p>

    @if ($meals->isEmpty())
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-14 text-center">
            <p class="text-6xl" aria-hidden="true">🎉</p>
            <p class="mt-4 font-[family-name:var(--font-display)] text-2xl font-semibold">All caught up</p>
            <p class="mt-1 text-sm opacity-60">Nothing left to log from yesterday.</p>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($meals as $meal)
                <div class="slot-row rounded-2xl border-y border-r border-base-300 bg-base-100 p-4"
                     style="--slot-accent: var(--meal-{{ $meal->slot->value }})">
                    <div class="slot-label text-[10px] font-semibold tracking-widest uppercase">{{ $meal->slot->value }}</div>
                    <a href="{{ route('recipes.show', $meal->recipe) }}"
                       class="mt-0.5 block font-[family-name:var(--font-display)] text-lg leading-snug font-semibold hover:text-primary">
                        {{ $meal->recipe->title }}
                    </a>
                    <livewire:meal-log-controls :planned-meal="$meal" :key="'catchup-'.$meal->id" />
                </div>
            @endforeach
        </div>
    @endif
</div>
