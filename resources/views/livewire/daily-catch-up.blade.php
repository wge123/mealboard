{{-- Single column, capped width: comfortable one-thumb logging on a phone. --}}
<div class="mx-auto w-full max-w-md">
    <x-ui.page-header title="Catch up on yesterday"
                      :subtitle="$yesterday->format('l j M').($meals->isEmpty() ? '' : ' · '.$meals->count().' '.\Illuminate\Support\Str::plural('meal', $meals->count()).' to log')" />

    @if ($meals->isEmpty())
        <x-ui.card>
            <x-ui.empty-state title="All caught up" icon="tabler-circle-check">Nothing left to log from yesterday.</x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card flush class="divide-y divide-base-300 overflow-hidden">
            @foreach ($meals as $meal)
                <div class="px-4 py-3" data-log-row>
                    <div class="flex items-center gap-1.5 text-xs font-medium tracking-wide text-base-content/70 uppercase">
                        <span class="size-2 rounded-full" style="background: var(--meal-{{ $meal->slot->value }})"></span>{{ $meal->slot->value }}
                    </div>
                    <a href="{{ route('recipes.show', $meal->recipe) }}" class="mt-0.5 block font-semibold hover:text-primary">{{ $meal->recipe->title }}</a>
                    <livewire:meal-log-controls :planned-meal="$meal" :key="'catchup-'.$meal->id" />
                </div>
            @endforeach
        </x-ui.card>
    @endif
</div>
