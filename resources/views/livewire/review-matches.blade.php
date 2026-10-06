<div>
    <div class="mb-4">
        <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Review Walmart matches</h1>
        <p class="mt-0.5 text-sm opacity-60">
            Proposed products wait here until you review them; they never reach the cart link before that.
            Leave a row alone to confirm it, tick it to reject that product, or paste the right product URL.
        </p>
    </div>

    @if ($summary)
        <div role="status" class="alert alert-success alert-soft mb-4">{{ $summary }}</div>
    @endif

    @if ($proposals->isEmpty())
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">Nothing to review</p>
            <p class="mt-1 text-sm opacity-60">No proposed matches are waiting.</p>
        </div>
    @else
        <ul class="mb-5 divide-y divide-base-300 rounded-2xl border border-base-300 bg-base-100">
            @foreach ($proposals as $proposal)
                <li class="px-3 py-2.5" wire:key="proposal-{{ $proposal->id }}">
                    <div class="flex items-start gap-3">
                        <label class="flex min-h-11 shrink-0 cursor-pointer items-center">
                            <input type="checkbox" class="checkbox checkbox-error checkbox-lg"
                                   aria-label="Replace the product for {{ $proposal->ingredient->name }}"
                                   wire:model="replace.{{ $proposal->id }}">
                        </label>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ $proposal->ingredient->name }}</span>
                                @if ($proposal->confidence)
                                    <span class="badge badge-soft badge-sm {{ ['high' => 'badge-success', 'medium' => 'badge-warning', 'low' => 'badge-error'][$proposal->confidence] }}">
                                        {{ $proposal->confidence }}
                                    </span>
                                @endif
                            </div>
                            <a href="{{ $proposal->product_url }}" target="_blank" rel="noopener"
                               class="link link-primary block text-sm">↗ {{ $proposal->product_name }}</a>
                            <input type="url" class="input input-sm mt-2 min-h-11 w-full max-w-sm"
                                   placeholder="Found it? Paste the right product URL"
                                   aria-label="Replacement Walmart product URL for {{ $proposal->ingredient->name }}"
                                   wire:model="foundUrls.{{ $proposal->id }}">
                            @error('foundUrls.'.$proposal->id)
                                <div class="mt-1 text-sm text-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="sticky bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 rounded-2xl border border-base-300 bg-base-100 p-2 lg:bottom-4">
            <button type="button" class="btn btn-primary min-h-11 w-full"
                    wire:click="submit" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading wire:target="submit" class="loading loading-spinner loading-xs"></span>
                Save review ({{ $proposals->count() }})
            </button>
        </div>
    @endif
</div>
