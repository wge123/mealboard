<div>
    @include('livewire.partials.section-header', [
        'title' => 'Recipe sites',
        'help' => 'Active sites are the websites daily discovery may read for new recipes.',
    ])

    <x-ui.card class="mb-5">
        <form wire:submit="add" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <x-ui.field label="Feed address" name="feedUrl" wire:model="feedUrl" type="url" placeholder="https://…/feed/" />
            <x-ui.field label="Site name" name="name" wire:model="name" />
            <x-ui.button type="submit" variant="primary" icon="tabler-plus" busy="add">Add</x-ui.button>
        </form>
    </x-ui.card>

    @if ($sites->isEmpty())
        <x-ui.card>
            <x-ui.empty-state title="No recipe sites yet" icon="tabler-world">Add a recipe site above to let discovery read it.</x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card flush>
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <x-ui.table.th>Site</x-ui.table.th>
                        <x-ui.table.th>Status</x-ui.table.th>
                        <x-ui.table.th class="w-0"><span class="sr-only">Actions</span></x-ui.table.th>
                    </tr>
                </x-slot:head>
                <tbody>
                    @foreach ($sites as $site)
                        <x-ui.table.row wire:key="recipe-site-{{ $site->id }}" class="{{ $site->active ? '' : 'text-base-content/70' }}">
                            <td class="w-full max-w-0">
                                <span class="block truncate font-medium">{{ $site->name }}</span>
                                <span class="block truncate text-base-content/70">{{ $site->feed_url }}</span>
                                <x-ui.badge :tone="$site->active ? 'primary' : 'neutral'" dot class="mt-1 sm:hidden">{{ $site->active ? 'Read daily' : 'Paused' }}</x-ui.badge>
                            </td>
                            <td class="hidden sm:table-cell">
                                <x-ui.badge :tone="$site->active ? 'primary' : 'neutral'" dot>{{ $site->active ? 'Read daily' : 'Paused' }}</x-ui.badge>
                            </td>
                            <td class="whitespace-nowrap text-end">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- min-h-11 label pads the toggle's hit area to tap-target size. --}}
                                    <label class="flex min-h-11 cursor-pointer items-center px-1 sm:min-h-9">
                                        <input type="checkbox" class="toggle toggle-primary" @checked($site->active)
                                               wire:click="toggle({{ $site->id }})"
                                               wire:loading.attr="disabled" wire:target="toggle"
                                               aria-label="{{ $site->active ? 'Pause' : 'Resume' }} {{ $site->name }}">
                                    </label>
                                    <x-ui.button variant="danger" square icon="tabler-x" :label="'Remove '.$site->name"
                                                 wire:click="remove({{ $site->id }})"
                                                 wire:confirm="Remove {{ $site->name }}?"
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
