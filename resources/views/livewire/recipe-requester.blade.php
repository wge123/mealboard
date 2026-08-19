<div class="mx-auto w-full max-w-2xl">
    <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Request a recipe</h1>
    <p class="mt-1 mb-5 text-sm opacity-60">
        Ask for a specific dish and Mealboard searches YouTube and asks Claude for it. Requests ignore the
        30-minute and 10-ingredient limits that shape the daily suggestions.
    </p>

    <form wire:submit="submit" class="mb-6">
        <div class="join join-vertical w-full sm:join-horizontal">
            <input type="text" class="input join-item min-h-11 w-full sm:flex-1 {{ $errors->has('query') ? 'input-error' : '' }}"
                   placeholder="hibachi for 4 on a flat-top griddle" wire:model="query" aria-label="What do you want to cook?">
            <button type="submit" class="btn btn-primary join-item min-h-11"
                    wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading wire:target="submit" class="loading loading-spinner loading-xs"></span>
                Find it
            </button>
        </div>
        @error('query')
            <p class="mt-1 text-sm text-error">{{ $message }}</p>
        @enderror
        <p class="mt-2 text-xs opacity-50" wire:loading wire:target="submit">
            Searching YouTube and asking Claude. This takes a minute or two.
        </p>
    </form>

    @if ($lastRequest !== null)
        <div class="mb-6 rounded-2xl border border-base-300 bg-base-100 px-5 py-4">
            <p class="text-sm opacity-60">Requested</p>
            <p class="font-medium">{{ $lastRequest->query }}</p>

            @if ($lastRequest->status === \App\Enums\RequestStatus::Failed)
                <p class="mt-2 text-sm text-error">Every lane failed, so nothing was stored.</p>
            @elseif ($lastRequest->candidates_found === 0)
                <p class="mt-2 text-sm">
                    Nothing new came back. Everything found was either already in your library or already rejected.
                </p>
            @else
                <p class="mt-2 text-sm">
                    {{ $lastRequest->candidates_found }} new {{ Str::plural('recipe', $lastRequest->candidates_found) }} waiting.
                    <a class="link link-primary" href="{{ route('recipes.approve') }}">Review {{ Str::plural('it', $lastRequest->candidates_found) }} now</a>
                </p>
            @endif

            @if ($lastRequest->error !== null)
                <p class="mt-2 text-xs text-warning">{{ $lastRequest->error }}</p>
            @endif
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <h2 class="mb-2 text-sm font-medium opacity-60">Earlier requests</h2>
        <ul class="divide-y divide-base-300 rounded-2xl border border-base-300 bg-base-100">
            @foreach ($recent as $request)
                <li class="flex items-center gap-3 px-4 py-3" wire:key="request-{{ $request->id }}">
                    <div class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ $request->query }}</span>
                        <span class="block truncate text-xs opacity-50">
                            {{ $request->completed_at?->diffForHumans() ?? 'in progress' }}
                        </span>
                    </div>
                    @if ($request->status === \App\Enums\RequestStatus::Failed)
                        <span class="badge badge-error badge-sm">failed</span>
                    @else
                        <span class="badge badge-ghost badge-sm">{{ $request->recipes_count }} found</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
