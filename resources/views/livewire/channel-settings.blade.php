<div>
    @include('livewire.partials.section-header', [
        'title' => 'YouTube channels',
        'help' => 'Active channels are checked daily for new recipe videos.',
    ])

    <x-ui.card class="mb-5">
        <form wire:submit="add" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <x-ui.field label="Channel ID" name="channelId" wire:model="channelId" placeholder="UC…" />
            <x-ui.field label="Channel name" name="name" wire:model="name" />
            <x-ui.button type="submit" variant="primary" icon="tabler-plus" busy="add">Add</x-ui.button>
        </form>
    </x-ui.card>

    @if ($channels->isEmpty())
        <x-ui.card>
            <x-ui.empty-state title="No channels yet" icon="tabler-brand-youtube">Add a YouTube channel above to start discovering recipes.</x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card flush>
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <x-ui.table.th>Channel</x-ui.table.th>
                        <x-ui.table.th>Status</x-ui.table.th>
                        <x-ui.table.th class="w-0"><span class="sr-only">Actions</span></x-ui.table.th>
                    </tr>
                </x-slot:head>
                <tbody>
                    @foreach ($channels as $channel)
                        <x-ui.table.row wire:key="channel-{{ $channel->id }}" class="{{ $channel->active ? '' : 'text-base-content/70' }}">
                            <td class="w-full max-w-0">
                                <span class="block truncate font-medium">{{ $channel->name }}</span>
                                <span class="block truncate text-base-content/70">{{ $channel->channel_id }}</span>
                                <x-ui.badge :tone="$channel->active ? 'primary' : 'neutral'" dot class="mt-1 sm:hidden">{{ $channel->active ? 'Polled daily' : 'Inactive' }}</x-ui.badge>
                            </td>
                            <td class="hidden sm:table-cell">
                                <x-ui.badge :tone="$channel->active ? 'primary' : 'neutral'" dot>{{ $channel->active ? 'Polled daily' : 'Inactive' }}</x-ui.badge>
                            </td>
                            <td class="whitespace-nowrap text-end">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- min-h-11 label pads the toggle's hit area to tap-target size. --}}
                                    <label class="flex min-h-11 cursor-pointer items-center px-1 sm:min-h-9">
                                        <input type="checkbox" class="toggle toggle-primary" @checked($channel->active)
                                               wire:click="toggle({{ $channel->id }})"
                                               wire:loading.attr="disabled" wire:target="toggle"
                                               aria-label="{{ $channel->active ? 'Deactivate' : 'Activate' }} {{ $channel->name }}">
                                    </label>
                                    <x-ui.button variant="danger" square icon="tabler-x" :label="'Remove '.$channel->name"
                                                 wire:click="remove({{ $channel->id }})"
                                                 wire:confirm="Remove {{ $channel->name }}?"
                                                 busy="remove" />
                                </div>
                            </td>
                        </x-ui.table.row>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
