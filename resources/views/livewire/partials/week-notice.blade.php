{{-- The in-screen notice: what the app prepared for this week, and the one thing to do next. Needs $state, $plan, $meals, $total, $completable. --}}
@php $gaps = $total - $meals->count(); @endphp
<x-ui.alert class="mb-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            @if ($state === 'suggested')
                <p class="font-semibold">Mealboard filled this week for you</p>
                <p class="text-base-content/70">
                    Look it over and change anything you don't fancy, then lock it. Locking makes the shopping list.
                    @if ($gaps > 0) {{ $gaps }} {{ \Illuminate\Support\Str::plural('meal', $gaps) }} still empty. @endif
                </p>
            @elseif ($state === 'empty')
                <p class="font-semibold">Nothing planned for this week yet</p>
                <p class="text-base-content/70">Let Mealboard fill it from your approved recipes, or pick meals one by one.</p>
            @elseif ($completable)
                <p class="font-semibold">This week is over</p>
                <p class="text-base-content/70">Log anything you missed, then mark it done.</p>
            @elseif ($state === 'locked')
                <p class="font-semibold">Locked. The shopping list is ready.</p>
                <p class="text-base-content/70">Tap a meal once you've had it to log it.</p>
            @else
                <p class="font-semibold">This week is done</p>
                <p class="text-base-content/70">Tap a meal to log it late.</p>
            @endif
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            @if ($state === 'suggested')
                @if ($gaps > 0)
                    <x-ui.button icon="tabler-wand" wire:click="openCheckIn" busy="openCheckIn">Fill the gaps</x-ui.button>
                @endif
                {{-- Outlined until every day is ticked, then solid. The ticks guide; Lock always works. --}}
                <x-ui.button :variant="$allReviewed ? 'primary' : 'secondary'" icon="tabler-lock" wire:click="lock" busy="lock"
                             wire:confirm="Lock this week? It becomes read-only for both of you.">{{ $allReviewed ? 'Lock week' : 'Lock week · '.$reviewed->count().' of '.$days->count().' days checked' }}</x-ui.button>
            @elseif ($state === 'empty')
                <x-ui.button variant="primary" icon="tabler-wand" wire:click="openCheckIn" busy="openCheckIn">Fill the week</x-ui.button>
            @else
                @if ($completable)
                    <x-ui.button icon="tabler-check" wire:click="markCompleted" busy="markCompleted"
                                 wire:confirm="Mark this week as completed?">Mark done</x-ui.button>
                @endif
                <x-ui.button variant="primary" icon="tabler-shopping-cart" :href="route('plan.shopping-list', $plan)">Shopping list</x-ui.button>
            @endif
        </div>
    </div>
</x-ui.alert>
