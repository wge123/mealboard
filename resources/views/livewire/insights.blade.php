{{-- Insights: a report. Four headline stats in one divided card, then every list as a flush table in a card. No charts;
     a rate gets a thin inline bar in its cell. Two tables per row on the computer, one column on the phone. --}}
@php
    $star = fn () => svg('tabler-star-filled', 'inline size-3.5 -translate-y-px text-primary');
@endphp
<div>
    <x-ui.page-header
        title="Insights"
        :subtitle="$coverage
            ? 'From '.$totals['logs'].' logged '.str('meal')->plural($totals['logs']).' in '.$coverage['weeks'].' planned '.str('week')->plural($coverage['weeks']).' between '.$coverage['from']->format('j M').' and '.$coverage['to']->format('j M').'.'
            : 'Nothing logged yet. Log meals from the plan and this page fills in.'"
    />

    <div class="flex flex-col gap-4">
        <x-ui.card flush>
            <dl class="grid grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-base-300 max-lg:[&>*]:border-base-300 max-lg:[&>*:nth-child(odd)]:border-e max-lg:[&>*:nth-child(-n+2)]:border-b">
                <x-ui.stat label="Meals logged" :value="$totals['logs']"
                           :hint="$coverage ? $coverage['weeks'].' '.str('week')->plural($coverage['weeks']) : null" />
                <x-ui.stat label="Eaten" :value="$totals['ateRate']" suffix="%"
                           :hint="$totals['ate'].' ate · '.$totals['skipped'].' skipped'" />
                <x-ui.stat label="Average rating" :value="$totals['avgRating'] !== null ? number_format($totals['avgRating'], 1) : null" suffix=" / 5"
                           :hint="$totals['rated'].' rated '.str('meal')->plural($totals['rated'])" />
                <x-ui.stat label="Discoveries kept" :value="$discovered['keptRate']" suffix="%"
                           :hint="$discovered['approved'].' kept · '.$discovered['rejected'].' passed'" />
            </dl>
        </x-ui.card>

        @if ($coverage === null)
            <x-ui.card>
                <x-ui.empty-state title="No meals logged yet" icon="tabler-chart-bar-off">
                    Once you mark planned meals as eaten or skipped, the tables show up here.
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
                @foreach (['By meal' => [$slotRates, 'Meal'], 'By weekday' => [$weekdayRates, 'Day']] as $title => [$rows, $first])
                    <x-ui.card :title="$title" flush class="min-w-0">
                        <div class="overflow-x-auto"><x-ui.table head-on-phone>
                            <x-slot:head>
                                <tr class="bg-base-200">
                                    <x-ui.table.th>{{ $first }}</x-ui.table.th>
                                    <x-ui.table.th numeric>Ate</x-ui.table.th>
                                    <x-ui.table.th numeric>Skipped</x-ui.table.th>
                                    <x-ui.table.th class="sm:w-2/5">Eaten</x-ui.table.th>
                                </tr>
                            </x-slot:head>
                            <tbody>
                                @foreach ($rows as $label => $row)
                                    <x-ui.table.row wire:key="{{ Str::slug($title) }}-{{ $label }}">
                                        <td class="whitespace-nowrap font-medium">
                                            @if ($title === 'By meal')
                                                <span class="me-2 inline-block size-2 rounded-full" style="background: var(--meal-{{ $label }})" aria-hidden="true"></span>
                                            @endif
                                            {{ ucfirst($label) }}
                                        </td>
                                        <td class="num">{{ $row['ate'] }}</td>
                                        <td class="num">{{ $row['skipped'] }}</td>
                                        <td>@include('livewire.partials.rate-bar', ['rate' => $row['rate'], 'label' => $label.' eaten'])</td>
                                    </x-ui.table.row>
                                @endforeach
                            </tbody>
                        </x-ui.table></div>
                    </x-ui.card>
                @endforeach

                <x-ui.card title="Top recipes" flush class="min-w-0">
                    @if ($topRecipes->isEmpty())
                        <x-ui.empty-state title="No rated meals yet." />
                    @else
                        <div class="overflow-x-auto"><x-ui.table head-on-phone>
                            <x-slot:head>
                                <tr class="bg-base-200">
                                    <x-ui.table.th class="w-10">#</x-ui.table.th>
                                    <x-ui.table.th>Recipe</x-ui.table.th>
                                    <x-ui.table.th numeric>Rating</x-ui.table.th>
                                    <x-ui.table.th numeric>Rated</x-ui.table.th>
                                </tr>
                            </x-slot:head>
                            <tbody>
                                @foreach ($topRecipes as $row)
                                    <x-ui.table.row wire:key="top-{{ $row['id'] }}">
                                        <td class="tabular-nums text-base-content/70">{{ $loop->iteration }}</td>
                                        <td><a href="{{ route('recipes.show', $row['id']) }}" class="font-medium hover:text-primary">{{ $row['title'] }}</a></td>
                                        <td class="num whitespace-nowrap">{{ $star() }} {{ number_format($row['avg'], 1) }}</td>
                                        <td class="num text-base-content/70">{{ $row['count'] }}×</td>
                                    </x-ui.table.row>
                                @endforeach
                            </tbody>
                        </x-ui.table></div>
                    @endif
                </x-ui.card>

                <x-ui.card title="Most skipped" flush class="min-w-0">
                    @if ($skippedRecipes->isEmpty())
                        <x-ui.empty-state title="Nothing skipped yet." />
                    @else
                        <div class="overflow-x-auto"><x-ui.table head-on-phone>
                            <x-slot:head>
                                <tr class="bg-base-200">
                                    <x-ui.table.th>Recipe</x-ui.table.th>
                                    <x-ui.table.th numeric>Skipped</x-ui.table.th>
                                    <x-ui.table.th numeric>Planned</x-ui.table.th>
                                </tr>
                            </x-slot:head>
                            <tbody>
                                @foreach ($skippedRecipes as $row)
                                    <x-ui.table.row wire:key="skipped-{{ $row['id'] }}">
                                        <td><a href="{{ route('recipes.show', $row['id']) }}" class="font-medium hover:text-primary">{{ $row['title'] }}</a></td>
                                        <td class="num">{{ $row['skipped'] }}</td>
                                        <td class="num text-base-content/70">{{ $row['planned'] }}</td>
                                    </x-ui.table.row>
                                @endforeach
                            </tbody>
                        </x-ui.table></div>
                    @endif
                </x-ui.card>

                <x-ui.card title="Cuisines" flush class="lg:col-span-2">
                    <div class="overflow-x-auto"><x-ui.table head-on-phone class="max-sm:[&_td]:px-2 max-sm:[&_th]:px-2">
                        <x-slot:head>
                            <tr class="bg-base-200">
                                <x-ui.table.th>Cuisine</x-ui.table.th>
                                <x-ui.table.th numeric>Meals</x-ui.table.th>
                                <x-ui.table.th class="sm:w-1/3">Eaten</x-ui.table.th>
                                <x-ui.table.th numeric>Rating</x-ui.table.th>
                            </tr>
                        </x-slot:head>
                        <tbody>
                            @foreach ($cuisines as $row)
                                <x-ui.table.row wire:key="cuisine-{{ $row['cuisine'] }}">
                                    <td class="font-medium max-sm:pe-1">{{ $row['cuisine'] }}</td>
                                    <td class="num">{{ $row['meals'] }}</td>
                                    <td>@include('livewire.partials.rate-bar', ['rate' => $row['rate'], 'label' => $row['cuisine'].' eaten'])</td>
                                    <td class="num whitespace-nowrap">
                                        @if ($row['avg'] !== null){{ $star() }} {{ number_format($row['avg'], 1) }}@else<span class="text-base-content/70">—</span>@endif
                                    </td>
                                </x-ui.table.row>
                            @endforeach
                        </tbody>
                    </x-ui.table></div>
                </x-ui.card>
            </div>
        @endif
    </div>
</div>
