<div>
    <x-ui.page-header
        title="Review Walmart matches"
        subtitle="Proposed products wait here until you review them; they never reach the cart link before that. Leave a row alone to confirm it, tick it to reject that product, or paste the right product URL."
        :back="route('shop')"
    />

    @if ($summary)
        <x-ui.alert tone="info" class="mb-5">{{ $summary }}</x-ui.alert>
    @endif

    @if ($proposals->isEmpty())
        <x-ui.card>
            <x-ui.empty-state title="Nothing to review">No proposed matches are waiting.</x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card flush title="Proposed matches" :count="$proposals->count()">
            <ul class="divide-y divide-base-300">
                @foreach ($proposals as $proposal)
                    <li class="flex items-start gap-3 px-4 py-3" wire:key="proposal-{{ $proposal->id }}">
                        <label class="flex min-h-11 shrink-0 cursor-pointer items-center sm:min-h-9">
                            <input type="checkbox" class="checkbox checkbox-primary checkbox-sm"
                                   aria-label="Replace the product for {{ $proposal->ingredient->name }}"
                                   wire:model="replace.{{ $proposal->id }}">
                        </label>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ $proposal->ingredient->name }}</span>
                                @if ($proposal->confidence)
                                    <x-ui.badge :tone="['high' => 'primary', 'medium' => 'warning', 'low' => 'error'][$proposal->confidence] ?? 'neutral'" :dot="true">{{ $proposal->confidence }}</x-ui.badge>
                                @endif
                            </div>
                            <a href="{{ $proposal->product_url }}" target="_blank" rel="noopener"
                               class="link link-primary block">{{ $proposal->product_name }}</a>
                            <div class="mt-2 max-w-sm">
                                <x-ui.field label="Found it? Paste the right product URL for {{ $proposal->ingredient->name }}"
                                            name="foundUrls.{{ $proposal->id }}" id="found-{{ $proposal->id }}"
                                            type="url" placeholder="Paste Walmart product URL"
                                            wire:model="foundUrls.{{ $proposal->id }}" />
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <x-slot:footer>
                <x-ui.button variant="primary" class="w-full" busy="submit" wire:click="submit">Save review ({{ $proposals->count() }})</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    @endif
</div>
