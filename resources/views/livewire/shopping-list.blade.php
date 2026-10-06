<div>
    {{-- Header: page title + week subtitle, back link. --}}
    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Shopping list</h1>
            <p class="mt-0.5 text-sm opacity-60">
                Week of {{ $mealPlan->week_start_date->format('j M Y') }} · {{ ucfirst($mealPlan->status->value) }}
            </p>
        </div>
        <a href="{{ route('plan.builder') }}" class="btn btn-ghost btn-sm min-h-11 border border-base-300">
            &larr; Back to plan
        </a>
    </div>

    {{-- Pantry staples toggle. --}}
    <label class="mb-5 flex min-h-11 w-fit cursor-pointer items-center gap-3">
        <input type="checkbox" class="toggle toggle-primary" wire:model.live="includeStaples">
        <span class="text-sm font-medium">Include pantry staples</span>
    </label>

    @forelse ($items as $category => $lines)
        <section>
            {{-- Sticky section header: stays visible while scrolling the category. --}}
            <h2 class="sticky top-0 z-10 flex items-center gap-2 bg-base-100 py-2 text-xs font-semibold tracking-wide text-base-content/60 uppercase">
                {{ ucfirst($category) }}
                <span class="badge badge-ghost badge-sm">{{ count($lines) }}</span>
            </h2>

            <ul class="mb-5 divide-y divide-base-300 rounded-2xl border border-base-300 bg-base-100">
                @foreach ($lines as $item)
                    @php($key = $item['name'].'|'.($item['unit'] ?? ''))
                    @php($isChecked = in_array($key, $checked, true))
                    @php($link = $links[$item['name']])
                    {{-- A flagged product needs a replacement, so it gets the paste flow too. --}}
                    @php($flagged = $link['availability'] !== null && $link['availability'] !== \App\Enums\WalmartAvailability::Ok)
                    @php($canPaste = ! $link['matched'] || $flagged)
                    <li class="px-3" @if ($canPaste) x-data="{ paste: @js($errors->has('foundUrls.'.$item['name'])) }" @endif>
                        <div class="flex items-center gap-2">
                            {{-- Whole label row toggles the checkbox — big in-store tap target. --}}
                            <label class="flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-3 py-2.5">
                                <input type="checkbox" class="checkbox checkbox-primary checkbox-lg shrink-0"
                                       wire:click="toggleItem('{{ $key }}')" @checked($isChecked)>
                                <span class="min-w-0 {{ $isChecked ? 'line-through opacity-50' : '' }}">
                                    <span class="font-medium">{{ $item['name'] }}</span>
                                    @if ($item['qty'] !== null || ($item['unit'] !== null && $item['unit'] !== 'count'))
                                        <span class="ms-1 text-sm opacity-60">
                                            @if ($item['qty'] !== null){{ rtrim(rtrim(number_format($item['qty'], 2, '.', ''), '0'), '.') }}@endif
                                            @if ($item['unit'] !== null && $item['unit'] !== 'count') {{ $item['unit'] }}@endif
                                        </span>
                                    @endif
                                    @if ($flagged)
                                        <span class="badge badge-sm ms-1 {{ $link['availability'] === \App\Enums\WalmartAvailability::OutOfStock ? 'badge-warning' : 'badge-error' }}"
                                              title="Seen at the {{ $link['seenAt'] }} cart import">
                                            {{ $link['availability'] === \App\Enums\WalmartAvailability::OutOfStock ? 'out of stock' : 'ship-only' }} · {{ $link['seenAt'] }}
                                        </span>
                                    @endif
                                    @if ($item['notes'] !== [])
                                        <span class="block text-xs opacity-50">{{ implode('; ', $item['notes']) }}</span>
                                    @endif
                                </span>
                            </label>

                            {{-- Walmart link chip — deliberately NOT the whole row (row = toggle). --}}
                            <a href="{{ $link['href'] }}" target="_blank" rel="noopener"
                               class="btn btn-ghost btn-sm min-h-11 shrink-0 px-2 font-normal text-primary"
                               aria-label="Walmart {{ $link['matched'] ? 'product' : 'search' }} for {{ $item['name'] }}">↗ {{ $link['matched'] ? 'product' : 'search' }}</a>
                        </div>

                        @if ($canPaste)
                            {{-- Found-it flow collapsed behind a small toggle so the checklist stays a checklist. --}}
                            <div class="pb-1 ps-10">
                                <button type="button" class="btn btn-ghost btn-sm min-h-11 px-2 font-normal text-base-content/60"
                                        @click="paste = !paste">
                                    {{ $link['matched'] ? '+ replace product' : '+ save product' }}
                                </button>
                                <div x-show="paste" x-cloak class="pb-2">
                                    <div class="join w-full max-w-sm">
                                        <input type="url" class="input join-item input-sm min-h-11 w-full" placeholder="Paste product URL"
                                               aria-label="Walmart product URL for {{ $item['name'] }}"
                                               wire:model="foundUrls.{{ $item['name'] }}">
                                        <button type="button" class="btn btn-outline join-item btn-success btn-sm min-h-11"
                                                wire:click="saveMatch('{{ $item['name'] }}')"
                                                wire:loading.attr="disabled" wire:target="saveMatch">
                                            <span wire:loading wire:target="saveMatch" class="loading loading-spinner loading-xs"></span>
                                            Found it
                                        </button>
                                    </div>
                                    @error('foundUrls.'.$item['name'])
                                        <div class="mt-1 text-sm text-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">Nothing to buy</p>
            <p class="mt-1 text-sm opacity-60">The week has no planned meals with ingredients.</p>
        </div>
    @endforelse

    @if (collect($links)->contains('matched', true))
        {{-- After a cart import: paste what Walmart could not put in the pickup order. --}}
        <details class="mb-5 rounded-2xl border border-base-300 bg-base-100 px-3 py-2" @if ($errors->has('availability')) open @endif>
            <summary class="min-h-11 cursor-pointer content-center text-sm font-medium">Report cart import</summary>
            <div class="flex flex-col gap-2 pb-2">
                <label class="text-sm">
                    <span class="opacity-60">"Unable to add to Cart" list (out of stock)</span>
                    <textarea class="textarea mt-1 w-full" rows="3" wire:model="outOfStockPaste"></textarea>
                </label>
                <label class="text-sm">
                    <span class="opacity-60">Cart's shipping section (ship-only, not pickup)</span>
                    <textarea class="textarea mt-1 w-full" rows="3" wire:model="shipOnlyPaste"></textarea>
                </label>
                @error('availability')
                    <div class="text-sm text-error">{{ $message }}</div>
                @enderror
                <button type="button" class="btn btn-outline btn-primary btn-sm min-h-11 w-fit" wire:click="recordAvailability">
                    Record availability
                </button>
            </div>
        </details>
    @endif

    @if ($availabilityReport !== null)
        <div class="mb-5 rounded-2xl border border-base-300 bg-base-200 px-3 py-2 text-sm" role="status">
            Recorded: {{ count($availabilityReport['out-of-stock']) }} out of stock, {{ count($availabilityReport['ship-only']) }} ship-only, {{ $availabilityReport['ok'] }} ok.
            @foreach (['out-of-stock' => 'Out of stock', 'ship-only' => 'Ship-only'] as $kind => $label)
                @if ($availabilityReport[$kind] !== [])
                    <span class="block opacity-70">{{ $label }}: {{ implode('; ', $availabilityReport[$kind]) }}</span>
                @endif
            @endforeach
        </div>
    @endif

    @if ($items !== [])
        {{-- Sticky action bar: sits above the fixed bottom tab bar on phones. --}}
        <div class="sticky bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 mt-6 flex flex-col gap-2 rounded-2xl border border-base-300 bg-base-100 p-2 lg:bottom-4">
            @if ($cartUrl !== null)
                {{-- Affiliate add-to-cart: the human clicks it, nothing here ever carts. --}}
                <a href="{{ $cartUrl }}" target="_blank" rel="noopener"
                   class="btn btn-primary min-h-11 w-full">
                    Add matched items to Walmart cart
                </a>
            @endif
            <div class="flex gap-2">
                <button type="button" class="btn btn-outline btn-primary min-h-11 flex-1" x-data="{ copied: false }"
                        x-text="copied ? 'Copied!' : 'Copy as markdown'"
                        @click="navigator.clipboard.writeText(@js($markdown)); copied = true; setTimeout(() => copied = false, 1500)">
                </button>
                <button type="button" class="btn btn-outline btn-primary min-h-11 flex-1" x-data="{ copied: false }"
                        x-text="copied ? 'Copied!' : 'Copy as plain list'"
                        @click="navigator.clipboard.writeText(@js($plain)); copied = true; setTimeout(() => copied = false, 1500)">
                </button>
            </div>
        </div>
    @endif
</div>
