{{-- /kit: every kit component in every variant. Local only (routes/web.php). Flip the theme in the nav to check dark. --}}
@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Kit"
        pretitle="Local only"
        subtitle="Every shared component in every variant. Flip the theme to check light and dark."
        back="{{ url('/') }}"
    >
        <x-slot:actions>
            <x-ui.button icon="tabler-plus">Action</x-ui.button>
            <x-ui.button variant="primary" icon="tabler-check">Primary action</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-col gap-6">
        <x-ui.card title="Page header">
            <div class="flex flex-col gap-6">
                <x-ui.page-header title="Title only" class="mb-0" />
                <x-ui.page-header title="Everything" pretitle="Week of 13 Jul" subtitle="One help line in muted text." back="#" class="mb-0">
                    <x-slot:actions><x-ui.button>Secondary</x-ui.button></x-slot:actions>
                </x-ui.page-header>
            </div>
        </x-ui.card>

        <x-ui.card title="Button" count="4 variants">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button variant="primary">Primary</x-ui.button>
                    <x-ui.button>Secondary</x-ui.button>
                    <x-ui.button variant="ghost">Ghost</x-ui.button>
                    <x-ui.button variant="danger">Danger</x-ui.button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button variant="primary" icon="tabler-lock">With icon</x-ui.button>
                    <x-ui.button icon="tabler-shopping-cart">With icon</x-ui.button>
                    <x-ui.button variant="ghost" icon="tabler-copy">With icon</x-ui.button>
                    <x-ui.button variant="danger" icon="tabler-trash">With icon</x-ui.button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button href="#" icon="tabler-arrow-right">As a link</x-ui.button>
                    <x-ui.button square icon="tabler-plus" label="Add" />
                    <x-ui.button square variant="ghost" icon="tabler-dots" label="More" />
                    <x-ui.button square variant="primary" icon="tabler-check" label="Done" />
                    <x-ui.button disabled>Disabled</x-ui.button>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/70">Busy (action class)</p>
                    <p class="text-base-content/70">On a screen the prop is <code>busy="wireTarget"</code> (and <code>busy-label="Parsing…"</code> for slow AI actions). Below, the busy markup frozen in its working state.</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button variant="primary" disabled><span class="loading loading-spinner loading-xs" aria-hidden="true"></span> Lock week</x-ui.button>
                        <x-ui.button disabled><span class="loading loading-spinner loading-xs" aria-hidden="true"></span> Parsing…</x-ui.button>
                        <x-ui.button square disabled label="Saving"><span class="loading loading-spinner loading-xs" aria-hidden="true"></span></x-ui.button>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Card" count="3">
            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.card>Plain card, no header.</x-ui.card>
                <x-ui.card title="Titled" :count="12">With a title and a count.</x-ui.card>
                <x-ui.card title="Actions and footer">
                    Body text.
                    <x-slot:actions><x-ui.button variant="ghost" icon="tabler-pencil">Edit</x-ui.button></x-slot:actions>
                    <x-slot:footer>Footer slot</x-slot:footer>
                </x-ui.card>
                <x-ui.card title="Flush" :count="2" flush class="md:col-span-3">
                    <div class="divide-y divide-base-300">
                        <div class="px-4 py-2.5">A row that runs edge to edge</div>
                        <div class="px-4 py-2.5">Another row</div>
                    </div>
                </x-ui.card>
            </div>
        </x-ui.card>

        <x-ui.card title="Empty state" count="2">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.card>
                    <x-ui.empty-state title="Nothing here yet" />
                </x-ui.card>
                <x-ui.card>
                    <x-ui.empty-state title="No week planned" icon="tabler-calendar-week">
                        Let Mise fill it from your approved recipes.
                        <x-slot:action><x-ui.button variant="primary" icon="tabler-wand">Fill the week</x-ui.button></x-slot:action>
                    </x-ui.empty-state>
                </x-ui.card>
            </div>
        </x-ui.card>

        <x-ui.card title="Alert" count="3 tones">
            <div class="flex flex-col gap-3">
                <x-ui.alert tone="info">
                    <p class="font-semibold">Info (role status)</p>
                    <p class="text-base-content/70">What the app prepared, and the one thing to do next.</p>
                </x-ui.alert>
                <x-ui.alert tone="warning">
                    <p class="font-semibold">Warning (role alert)</p>
                    <p class="text-base-content/70">This list is from an earlier week.</p>
                </x-ui.alert>
                <x-ui.alert tone="error">
                    <p class="font-semibold">Error (role alert)</p>
                    <p class="text-base-content/70">The recipe could not be read. Try again.</p>
                </x-ui.alert>
            </div>
        </x-ui.card>

        <x-ui.card title="Badge" count="5 tones">
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge>Neutral</x-ui.badge>
                    <x-ui.badge tone="primary">Primary</x-ui.badge>
                    <x-ui.badge tone="info">Info</x-ui.badge>
                    <x-ui.badge tone="warning">Warning</x-ui.badge>
                    <x-ui.badge tone="error">Error</x-ui.badge>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge dot>Neutral</x-ui.badge>
                    <x-ui.badge tone="primary" dot>Matched</x-ui.badge>
                    <x-ui.badge tone="info" dot>Search</x-ui.badge>
                    <x-ui.badge tone="warning" dot>Missing tool</x-ui.badge>
                    <x-ui.badge tone="error" dot>Error</x-ui.badge>
                </div>
            </div>
        </x-ui.card>

        @php
            $kitRows = [
                'Produce' => [['Basil', 2, 'bunch', true], ['Cherry tomatoes', 400, 'g', false], ['Lemons', 3, '', false]],
                'Dairy' => [['Feta', 200, 'g', true], ['Greek yoghurt', 500, 'g', true]],
                'Pantry' => [['Orzo', 300, 'g', false], ['Olive oil', 1, 'bottle', false]],
            ];
            $sortBy = request('sort') === 'item' ? 'item' : 'amount';
            $sortDir = request('dir') === 'asc' ? 'asc' : 'desc';
            $sortUrl = fn (string $col) => '?sort='.$col.'&dir='.($sortBy === $col && $sortDir === 'asc' ? 'desc' : 'asc');
            $kitRows = array_map(function ($items) use ($sortBy, $sortDir) {
                usort($items, fn ($a, $b) => ($sortBy === 'item' ? $a[0] <=> $b[0] : $a[1] <=> $b[1]) * ($sortDir === 'asc' ? 1 : -1));

                return $items;
            }, $kitRows);
        @endphp

        <x-ui.card title="Table" count="sortable, numeric, folding groups" flush>
            <div>
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <x-ui.table.th sortable :sort="$sortBy === 'item' ? $sortDir : null" onclick="location.search = '{{ $sortUrl('item') }}'">Item</x-ui.table.th>
                            <x-ui.table.th numeric :sort="$sortBy === 'amount' ? $sortDir : null" onclick="location.search = '{{ $sortUrl('amount') }}'">Amount</x-ui.table.th>
                            <x-ui.table.th class="hidden md:table-cell">Unit</x-ui.table.th>
                            <x-ui.table.th numeric>Have</x-ui.table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($kitRows as $aisle => $items)
                        @php $kitDone = collect($items)->where(3, true)->count(); @endphp
                        <tbody wire:key="g-{{ $aisle }}-{{ $kitDone }}">
                            <x-ui.table.group :label="$aisle" :done="$kitDone" :total="count($items)" :cols="4" />
                            @foreach ($items as [$name, $amount, $unit, $have])
                                <x-ui.table.row :group="$aisle">
                                    <td class="p-0!">
                                        <label class="flex min-h-11 cursor-pointer items-center gap-3 px-4 py-1.5">
                                            <input type="checkbox" class="checkbox checkbox-primary checkbox-sm" @checked($have)>
                                            {{ $name }}
                                        </label>
                                    </td>
                                    <td class="num">{{ $amount }}</td>
                                    <td class="hidden text-base-content/70 md:table-cell">{{ $unit }}</td>
                                    <td class="num">{{ $have ? 'Yes' : 'No' }}</td>
                                </x-ui.table.row>
                            @endforeach
                        </tbody>
                    @endforeach
                </x-ui.table>
            </div>
            <x-slot:footer>
                Tap a group to fold it; Dairy is complete, so it starts folded. Click Item or Amount to sort (the header shows the direction).
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card title="Chip" count="2 states">
            <div class="flex flex-col gap-4" x-data="{ on: ['Dinner'] }">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach (['Breakfast', 'Lunch', 'Dinner', 'Snack'] as $meal)
                        <x-ui.chip x-bind:aria-pressed="on.includes('{{ $meal }}') ? 'true' : 'false'"
                                   x-bind:class="on.includes('{{ $meal }}') ? 'border-primary bg-primary/10 text-primary' : 'border-base-300 bg-base-100'"
                                   @click="on = on.includes('{{ $meal }}') ? on.filter(m => m !== '{{ $meal }}') : [...on, '{{ $meal }}']">{{ $meal }}</x-ui.chip>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.chip pressed icon="tabler-check">Pressed</x-ui.chip>
                    <x-ui.chip>Resting</x-ui.chip>
                    <x-ui.chip busy="kit" disabled>Busy (disabled)</x-ui.chip>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Filter menu" count="3 states">
            <div class="flex flex-wrap items-center gap-2" x-data="{ meals: ['Dinner'] }">
                <x-ui.filter-menu label="Meal">
                    @foreach (['Breakfast', 'Lunch', 'Dinner', 'Snack'] as $meal)
                        <x-ui.filter-menu.option x-bind:aria-pressed="meals.includes('{{ $meal }}') ? 'true' : 'false'"
                                                 @click="meals = meals.includes('{{ $meal }}') ? meals.filter(m => m !== '{{ $meal }}') : [...meals, '{{ $meal }}']">{{ $meal }}</x-ui.filter-menu.option>
                    @endforeach
                </x-ui.filter-menu>
                <x-ui.filter-menu label="Cuisine" :count="2">
                    <x-ui.filter-menu.option pressed>Thai</x-ui.filter-menu.option>
                    <x-ui.filter-menu.option pressed>Italian</x-ui.filter-menu.option>
                    <x-ui.filter-menu.option>French</x-ui.filter-menu.option>
                </x-ui.filter-menu>
                <x-ui.filter-menu label="Time" single value="≤ 20 min">
                    <x-ui.filter-menu.option>Any time</x-ui.filter-menu.option>
                    <x-ui.filter-menu.option pressed>≤ 20 min</x-ui.filter-menu.option>
                    <x-ui.filter-menu.option>≤ 40 min</x-ui.filter-menu.option>
                </x-ui.filter-menu>
            </div>
            <p class="mt-3 text-base-content/70">A button that opens a list of options. Multi-pick menus show how many are on; a one-of-N menu (<code>single</code>) shows its choice and closes after a pick. Active menus are tinted and announced in the button's name.</p>
        </x-ui.card>

        <x-ui.card title="Datagrid" count="label over value">
            <x-ui.datagrid>
                <x-ui.datagrid.item label="Cuisine">Thai</x-ui.datagrid.item>
                <x-ui.datagrid.item label="Time">25 min</x-ui.datagrid.item>
                <x-ui.datagrid.item label="Serves">4</x-ui.datagrid.item>
                <x-ui.datagrid.item label="Source"><a class="link link-hover" href="#">example.com</a></x-ui.datagrid.item>
                <x-ui.datagrid.item label="Approved" />
            </x-ui.datagrid>
        </x-ui.card>

        <x-ui.card title="Segmented toggle" count="2 states">
            <div class="flex flex-col items-start gap-4" x-data="{ view: 'group' }">
                <x-ui.segmented label="View">
                    <x-ui.segmented.option x-bind:aria-pressed="view === 'group' ? 'true' : 'false'" x-bind:class="view === 'group' ? 'bg-base-100 text-base-content ring-1 ring-base-300' : 'text-base-content/70'" @click="view = 'group'">Group by</x-ui.segmented.option>
                    <x-ui.segmented.option x-bind:aria-pressed="view === 'shelves' ? 'true' : 'false'" x-bind:class="view === 'shelves' ? 'bg-base-100 text-base-content ring-1 ring-base-300' : 'text-base-content/70'" @click="view = 'shelves'">Shelves</x-ui.segmented.option>
                </x-ui.segmented>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.segmented label="Outcome (static)">
                        <x-ui.segmented.option pressed>Ate it</x-ui.segmented.option>
                        <x-ui.segmented.option>Skipped</x-ui.segmented.option>
                    </x-ui.segmented>
                    <x-ui.segmented label="Outcome (busy, disabled)" busy="kit">
                        <x-ui.segmented.option pressed disabled>Ate it</x-ui.segmented.option>
                        <x-ui.segmented.option disabled>Skipped</x-ui.segmented.option>
                    </x-ui.segmented>
                </div>
            </div>
        </x-ui.card>

        @php
            view()->share('errors', (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['kit_error' => ['Enter a quantity between 1 and 20.']])));
        @endphp
        <x-ui.card title="Field" count="3 states">
            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.field label="Recipe name" name="kit_plain" placeholder="Pad thai" />
                <x-ui.field label="Servings" name="kit_hint" type="number" hint="How many people you cook for." value="4" />
                <x-ui.field label="Quantity" name="kit_error" type="number" value="40" hint="Whole numbers only." />
            </div>
        </x-ui.card>

        <x-ui.card title="Refresh (page convention)">
            <p class="mb-3 text-base-content/70">
                Lists that update wrap their results in <code>wire:loading.delay.class="opacity-50 pointer-events-none"</code>
                with <code>wire:loading.attr="aria-busy"</code> and an explicit <code>wire:target</code>. It dims after 200ms, so quick round trips never flicker. Shown here frozen in its dimmed state.
            </p>
            <div class="flex gap-6">
                <div class="flex-1">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-base-content/70">Resting</p>
                    <div class="divide-y divide-base-300 rounded-box border border-base-300">
                        <div class="px-4 py-2.5">Pad thai</div>
                        <div class="px-4 py-2.5">Shakshuka</div>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-base-content/70">Refreshing</p>
                    <div class="pointer-events-none divide-y divide-base-300 rounded-box border border-base-300 opacity-50" aria-busy="true">
                        <div class="px-4 py-2.5">Pad thai</div>
                        <div class="px-4 py-2.5">Shakshuka</div>
                    </div>
                </div>
            </div>
        </x-ui.card>
        <x-ui.card title="Summary row" count="closed, open" flush>
            <x-ui.summary-row id="kit-time" label="Weekday time limit" value="30" suffix="min" help="Prep and cook, in total.">
                <div class="flex flex-wrap gap-2">
                    @foreach ([15, 20, 30, 45, 60] as $minutes)
                        <x-ui.chip :pressed="$minutes === 30" class="tabular-nums">{{ $minutes }}</x-ui.chip>
                    @endforeach
                    <x-ui.chip class="border-dashed">Other</x-ui.chip>
                </div>
            </x-ui.summary-row>
            <x-ui.summary-row id="kit-size" label="Household size" value="2" suffix="people" help="How many people you cook for." open>
                <div class="flex flex-wrap gap-2">
                    @foreach ([1, 2, 3, 4] as $size)
                        <x-ui.chip :pressed="$size === 2" class="tabular-nums">{{ $size }}</x-ui.chip>
                    @endforeach
                </div>
            </x-ui.summary-row>
            <x-slot:footer>
                Tap a row to open it in place. Below: the ink tone of the chip, used for avoided ingredients.
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-ui.chip tone="ink" pressed icon="tabler-x">Cilantro</x-ui.chip>
                    <x-ui.chip tone="ink" icon="tabler-plus">Olive</x-ui.chip>
                </div>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card title="Segmented toggle, green" count="tone=primary">
            <x-ui.segmented label="Outcome (green)">
                <x-ui.segmented.option tone="primary" pressed><x-tabler-check class="size-3.5" /> Ate it</x-ui.segmented.option>
                <x-ui.segmented.option tone="primary"><x-tabler-x class="size-3.5" /> Skipped</x-ui.segmented.option>
            </x-ui.segmented>
        </x-ui.card>

        <x-ui.card title="Stat" count="4 in one card, plus the empty value" flush>
            <dl class="grid grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-base-300 max-lg:[&>*]:border-base-300 max-lg:[&>*:nth-child(odd)]:border-e max-lg:[&>*:nth-child(-n+2)]:border-b">
                <x-ui.stat label="Meals logged" value="42" hint="6 weeks" />
                <x-ui.stat label="Eaten" value="75" suffix="%" hint="31 ate · 11 skipped" />
                <x-ui.stat label="Average rating" value="3.7" suffix=" / 5" hint="18 rated meals" />
                <x-ui.stat label="Discoveries kept" :value="null" suffix="%" hint="0 kept · 0 passed" />
            </dl>
            <x-slot:footer>Label over a figure in tabular digits, an optional suffix and a one-line hint. A stat with no value shows a dash. No trend arrows.</x-slot:footer>
        </x-ui.card>
        <x-ui.card title="Checklist line" count="checkbox, icon, action" flush>
            <p class="px-4 py-3 text-base-content/70">
                A 44px line (32px on desktop): checkbox, name, muted count and an optional trailing action. Under a sticky, folding
                <code>table.group</code> row they flow in columns. Tap the name to tick the box.
            </p>
            <x-ui.table class="border-t border-base-300">
                <tbody>
                    <x-ui.table.group label="Produce" :cols="1" sticky="top-0">
                        <span>2</span> of 4 on hand
                    </x-ui.table.group>
                    <x-ui.table.row group="Produce" class="hover:bg-transparent!">
                        <td class="p-0!">
                            <ul class="gap-x-6 px-2 py-1 sm:columns-2">
                                <li class="break-inside-avoid"><x-ui.checklist-line id="kit-line-1" label="Basil" :count="9" count-label="used in 9 recipes" checked /></li>
                                <li class="break-inside-avoid"><x-ui.checklist-line id="kit-line-2" label="Garlic" :count="14" count-label="used in 14 recipes" checked /></li>
                                <li class="break-inside-avoid"><x-ui.checklist-line id="kit-line-3" label="Lemon" :count="4" count-label="used in 4 recipes" /></li>
                                <li class="break-inside-avoid"><x-ui.checklist-line id="kit-line-4" label="Shallot" :count="1" count-label="used in 1 recipe" /></li>
                            </ul>
                        </td>
                    </x-ui.table.row>
                </tbody>
            </x-ui.table>
            <div class="grid gap-x-6 border-t border-base-300 px-2 py-1 sm:grid-cols-2">
                <x-ui.checklist-line id="kit-line-5" label="Wok" checked>
                    <x-slot:action><x-ui.button square variant="ghost" icon="tabler-dots" label="Details for Wok" /></x-slot:action>
                </x-ui.checklist-line>
                <x-ui.checklist-line label="Icon instead of a checkbox" icon="tabler-flame" :count="3" count-label="3 recipes" />
                <x-ui.checklist-line id="kit-line-6" label="Air Fryer" hint="5 L" :count="4" count-label="used in 4 recipes, and you don't have it" warn>
                    <x-slot:leading><x-tabler-flame /></x-slot:leading>
                </x-ui.checklist-line>
            </div>
        </x-ui.card>
    </div>
@endsection
