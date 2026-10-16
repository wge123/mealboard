<div>
    @include('livewire.partials.section-header', [
        'title' => 'YouTube channels',
        'help' => 'Active channels are checked daily for new recipe videos.',
    ])

    <form wire:submit="add" class="mb-5">
        <div class="flex flex-col gap-2 sm:flex-row">
            <input type="text" class="input min-h-11 w-full sm:flex-1 {{ $errors->has('channelId') ? 'input-error' : '' }}"
                   placeholder="Channel ID (UC…)" wire:model="channelId" aria-label="Channel ID">
            <input type="text" class="input min-h-11 w-full sm:flex-1 {{ $errors->has('name') ? 'input-error' : '' }}"
                   placeholder="Channel name" wire:model="name" aria-label="Channel name">
            <button type="submit" class="btn btn-primary min-h-11"
                    wire:loading.attr="disabled" wire:target="add">
                <span wire:loading wire:target="add" class="loading loading-spinner loading-xs"></span>
                Add
            </button>
        </div>
        @error('channelId')
            <p class="mt-1 text-sm text-error">{{ $message }}</p>
        @enderror
        @error('name')
            <p class="mt-1 text-sm text-error">{{ $message }}</p>
        @enderror
    </form>

    @if ($channels->isEmpty())
        <div class="rounded-box border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-display text-xl font-semibold">No channels yet</p>
            <p class="mt-1 text-sm text-base-content/70">Add a YouTube channel above to start discovering recipes.</p>
        </div>
    @else
        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($channels as $channel)
                <li class="flex items-center gap-3 rounded-box border border-base-300 bg-base-100 p-3 {{ $channel->active ? '' : 'text-base-content/70' }}" wire:key="channel-{{ $channel->id }}">
                    <span aria-hidden="true" class="grid size-10 shrink-0 place-items-center rounded-full bg-secondary font-semibold text-secondary-content">{{ mb_strtoupper(mb_substr($channel->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ $channel->name }}</span>
                        <span class="block truncate text-xs text-base-content/70">{{ $channel->active ? 'Polled daily' : 'Inactive' }} · {{ $channel->channel_id }}</span>
                    </div>

                    {{-- min-h-11 label pads the toggle's hit area to tap-target size. --}}
                    <label class="flex min-h-11 shrink-0 cursor-pointer items-center">
                        <input type="checkbox" class="toggle toggle-primary" @checked($channel->active)
                               wire:click="toggle({{ $channel->id }})"
                               wire:loading.attr="disabled" wire:target="toggle"
                               aria-label="{{ $channel->active ? 'Deactivate' : 'Activate' }} {{ $channel->name }}">
                    </label>

                    <button type="button" class="btn btn-ghost btn-sm min-h-11 min-w-11 shrink-0 text-error"
                            wire:click="remove({{ $channel->id }})"
                            wire:confirm="Remove {{ $channel->name }}?"
                            wire:loading.attr="disabled" wire:target="remove"
                            aria-label="Remove {{ $channel->name }}"><x-tabler-x class="size-4" /></button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
