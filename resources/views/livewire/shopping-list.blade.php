@php
    $all = collect($lines)->flatten(1);
    $total = $all->count();
    $done = $all->where('checked', true)->count();
    $cartCount = collect($this->shoppingList['buy_list'])->whereNotNull('product_url')->count();
    $weeksStale = $mealPlan->weeksStale();
    $fmtQty = fn ($qty) => $qty === null ? '' : rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');
    $erroredNames = $all->pluck('name')->filter(fn ($name) => $errors->has('foundUrls.'.$name))->values()->all();
@endphp
<div>
    <x-ui.page-header
        title="Shopping list"
        :pretitle="'Week of '.$mealPlan->week_start_date->format('j M Y')"
        :subtitle="$lines !== [] ? 'Tick what you already have, then add the rest to your Walmart cart.' : null"
        :back="route('plan.builder')"
    >
        @if ($lines !== [])
            <x-slot:actions>
                <div class="hidden items-center gap-2 lg:flex">
                    @include('livewire.partials.shopping-list-copy-menu')
                    @if ($cartUrl !== null)
                        <x-ui.button variant="primary" :href="$cartUrl" icon="tabler-shopping-cart" target="_blank" rel="noopener">Add {{ $cartCount }} to Walmart cart</x-ui.button>
                    @endif
                </div>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($weeksStale > 0)
        {{-- Stale week: dates already past, nothing newer locked (GLOSSARY). --}}
        <x-ui.alert tone="warning" class="mb-5">
            <strong>Stale week.</strong>
            This week ended {{ $weeksStale }} {{ Str::plural('week', $weeksStale) }} ago and no newer week has been locked — lock the current week before shopping.
        </x-ui.alert>
    @endif

    @if ($proposedCount > 0)
        {{-- Matcher guesses stay off the cart link until reviewed. --}}
        <x-ui.alert tone="info" class="mb-5">
            <a href="{{ route('walmart.review') }}" class="font-medium underline">{{ $proposedCount }} proposed Walmart {{ Str::plural('match', $proposedCount) }} waiting for review &rarr;</a>
        </x-ui.alert>
    @endif

    <x-ui.card flush title="Items" :count="$total">
        <x-slot:actions>
            <label class="flex min-h-11 cursor-pointer items-center gap-2 sm:min-h-9">
                <input type="checkbox" class="toggle toggle-primary toggle-sm" wire:model.live="includeStaples">
                <span>Pantry staples</span>
            </label>
        </x-slot:actions>

        <div wire:loading.delay.class="opacity-50 pointer-events-none" wire:loading.attr="aria-busy" wire:target="includeStaples">
            @if ($lines === [])
                <x-ui.empty-state title="Nothing to buy">The week has no planned meals with ingredients.</x-ui.empty-state>
            @else
                <div class="flex items-center gap-3 border-b border-base-300 px-4 py-3">
                    <progress class="progress progress-primary h-1.5 flex-1" value="{{ $done }}" max="{{ max($total, 1) }}" aria-label="Items ticked"></progress>
                    <span class="text-base-content/70 tabular-nums">{{ $done }}/{{ $total }}</span>
                </div>

                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <x-ui.table.th>Item</x-ui.table.th>
                            <x-ui.table.th numeric>Amount</x-ui.table.th>
                            <x-ui.table.th class="hidden md:table-cell">Notes</x-ui.table.th>
                            <x-ui.table.th numeric>Walmart</x-ui.table.th>
                        </tr>
                    </x-slot:head>

                    @foreach ($lines as $category => $categoryLines)
                        @php($aisleDone = collect($categoryLines)->where('checked', true)->count())
                        {{-- The key carries the ticked count, so a tick re-creates the aisle and the auto-fold rule takes over again. --}}
                        <tbody wire:key="aisle-{{ $category }}-{{ $aisleDone }}" x-data="{ paste: Object.fromEntries(@js($erroredNames).map(n => [n, true])) }">
                            <x-ui.table.group :label="ucfirst($category)" :done="$aisleDone" :total="count($categoryLines)" :cols="4" />
                            @foreach ($categoryLines as $line)
                                @php($matched = $line['product_url'] !== null)
                                <x-ui.table.row :group="ucfirst($category)" :class="$line['checked'] ? 'text-base-content/70' : ''">
                                    <td class="p-0!">
                                        <label class="flex min-h-11 cursor-pointer items-center gap-3 px-4 py-1.5">
                                            <input type="checkbox" class="checkbox checkbox-primary checkbox-sm shrink-0"
                                                   wire:click="toggleItem(@js($line['key']))" @checked($line['checked'])>
                                            <span class="min-w-0">
                                                <span class="font-medium {{ $line['checked'] ? 'line-through' : '' }}">{{ $line['name'] }}</span>
                                                @if ($line['notes'] !== [])
                                                    <span class="block text-xs text-base-content/70 md:hidden">{{ implode('; ', $line['notes']) }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    </td>
                                    <td class="num whitespace-nowrap px-2! sm:px-4!">{{ $fmtQty($line['qty']) }} <span class="text-base-content/70">{{ $line['unit'] !== 'count' ? $line['unit'] : '' }}</span></td>
                                    <td class="hidden text-base-content/70 md:table-cell">{{ implode('; ', $line['notes']) }}</td>
                                    <td class="whitespace-nowrap px-2! text-end sm:px-4!">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ $line['product_url'] ?? $line['search_url'] }}" target="_blank" rel="noopener"
                                               class="inline-flex min-h-11 items-center sm:min-h-9"
                                               aria-label="Walmart {{ $matched ? 'product' : 'search' }} for {{ $line['name'] }}">
                                                <x-ui.badge :tone="$matched ? 'primary' : 'neutral'" :dot="true">{{ $matched ? 'Matched' : 'Search' }}</x-ui.badge>
                                            </a>
                                            @unless ($matched)
                                                <x-ui.button variant="ghost" square icon="tabler-plus"
                                                             :label="'Save a Walmart product for '.$line['name']"
                                                             @click="paste[{{ Js::from($line['name']) }}] = ! paste[{{ Js::from($line['name']) }}]" />
                                            @endunless
                                        </div>
                                    </td>
                                </x-ui.table.row>
                                @unless ($matched)
                                    <tr x-show="open(@js(ucfirst($category))) && paste[@js($line['name'])]" x-cloak>
                                        <td colspan="4" class="px-4 pb-3">
                                            <div class="flex items-start gap-2">
                                                <div class="flex-1">
                                                    <x-ui.field label="Walmart product URL for {{ $line['name'] }}" name="foundUrls.{{ $line['name'] }}" id="found-{{ $loop->parent->index }}-{{ $loop->index }}"
                                                                type="url" placeholder="Paste Walmart product URL" wire:model="foundUrls.{{ $line['name'] }}" />
                                                </div>
                                                <x-ui.button variant="primary" class="mt-6" busy="saveMatch" wire:click="saveMatch({{ Js::from($line['name']) }})">Save</x-ui.button>
                                            </div>
                                        </td>
                                    </tr>
                                @endunless
                            @endforeach
                        </tbody>
                    @endforeach
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>

    @if ($lines !== [])
        {{-- Phone bottom bar: floats above the tab bar. --}}
        <div class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 mt-4 flex gap-2 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg lg:hidden">
            @include('livewire.partials.shopping-list-copy-menu', ['up' => true, 'iconOnly' => true])
            @if ($cartUrl !== null)
                <x-ui.button variant="primary" class="flex-1" :href="$cartUrl" icon="tabler-shopping-cart" target="_blank" rel="noopener">Add {{ $cartCount }} to Walmart cart</x-ui.button>
            @endif
        </div>
    @endif
</div>
