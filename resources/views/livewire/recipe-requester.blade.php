<div class="mx-auto w-full max-w-2xl">
    <x-ui.page-header title="Request a recipe"
        subtitle="Ask for a specific dish and Mise searches YouTube for it. Requests ignore the weekday limits that shape the daily suggestions." />

    <x-ui.card class="mb-6">
        <form wire:submit="submit" class="flex flex-col gap-3">
            <x-ui.field label="What do you want to cook?" name="query" wire:model="query"
                        placeholder="hibachi for 4 on a flat-top griddle" />
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button type="submit" variant="primary" icon="tabler-search" busy="submit" busy-label="Searching…">Find it</x-ui.button>
                <p class="hidden text-base-content/70" wire:loading.class.remove="hidden" wire:target="submit" aria-live="polite">
                    Searching YouTube. This takes a minute or two.
                </p>
            </div>
        </form>
    </x-ui.card>

    @if ($lastRequest !== null)
        <x-ui.card title="Requested" class="mb-6" wire:loading.delay.class="opacity-50 pointer-events-none" wire:loading.attr="aria-busy" wire:target="submit">
            <p class="font-medium">{{ $lastRequest->query }}</p>

            @if ($lastRequest->status === \App\Enums\RequestStatus::Failed)
                <x-ui.alert tone="error" class="mt-3">The YouTube search failed, so nothing was stored.</x-ui.alert>
            @elseif ($lastRequest->candidates_found === 0)
                <p class="mt-2">
                    Nothing new came back. Everything found was either already in your library or already rejected.
                </p>
            @else
                <p class="mt-2">
                    {{ $lastRequest->candidates_found }} new {{ Str::plural('recipe', $lastRequest->candidates_found) }} waiting.
                    <a class="link link-primary" href="{{ route('recipes.approve') }}">Review {{ Str::plural('it', $lastRequest->candidates_found) }} now</a>
                </p>
            @endif

            @if ($lastRequest->error !== null)
                <x-ui.alert tone="warning" class="mt-3">{{ $lastRequest->error }}</x-ui.alert>
            @endif
        </x-ui.card>
    @endif

    @if ($recent->isNotEmpty())
        <x-ui.card title="Earlier requests" flush>
            <x-ui.table>
                <tbody>
                    @foreach ($recent as $request)
                        <x-ui.table.row wire:key="request-{{ $request->id }}">
                            <td class="w-full max-w-0">
                                <span class="block truncate font-medium">{{ $request->query }}</span>
                                <span class="block truncate text-base-content/70">
                                    {{ $request->completed_at?->diffForHumans() ?? 'in progress' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if ($request->status === \App\Enums\RequestStatus::Failed)
                                    <x-ui.badge tone="error" dot>failed</x-ui.badge>
                                @else
                                    <x-ui.badge>{{ $request->recipes_count }} found</x-ui.badge>
                                @endif
                            </td>
                        </x-ui.table.row>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
