<div class="mx-auto w-full max-w-2xl">
    <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">YouTube channels</h1>
    <p class="mt-1 mb-5 text-sm opacity-60">Active channels are polled daily for new recipe videos.</p>

    {{-- Add-channel form: joined inputs stack vertically on phones. --}}
    <form wire:submit="add" class="mb-6">
        <div class="join join-vertical w-full sm:join-horizontal">
            <input type="text" class="input join-item min-h-11 w-full sm:flex-1 {{ $errors->has('channelId') ? 'input-error' : '' }}"
                   placeholder="Channel ID (UC…)" wire:model="channelId" aria-label="Channel ID">
            <input type="text" class="input join-item min-h-11 w-full sm:flex-1 {{ $errors->has('name') ? 'input-error' : '' }}"
                   placeholder="Channel name" wire:model="name" aria-label="Channel name">
            <button type="submit" class="btn btn-primary join-item min-h-11"
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
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-12 text-center">
            <p class="font-[family-name:var(--font-display)] text-xl font-semibold">No channels yet</p>
            <p class="mt-1 text-sm opacity-60">Add a YouTube channel above to start discovering recipes.</p>
        </div>
    @else
        <ul class="divide-y divide-base-300 rounded-2xl border border-base-300 bg-base-100">
            @foreach ($channels as $channel)
                <li class="flex items-center gap-3 px-4 py-3" wire:key="channel-{{ $channel->id }}">
                    <div class="min-w-0 flex-1">
                        <span class="block truncate font-medium {{ $channel->active ? '' : 'opacity-50' }}">{{ $channel->name }}</span>
                        <span class="block truncate text-xs opacity-50">{{ $channel->channel_id }}</span>
                    </div>

                    @unless ($channel->active)
                        <span class="badge badge-soft badge-neutral badge-sm shrink-0">Inactive</span>
                    @endunless

                    {{-- min-h-11 label pads the toggle's hit area to tap-target size. --}}
                    <label class="flex min-h-11 shrink-0 cursor-pointer items-center">
                        <input type="checkbox" class="toggle toggle-primary" @checked($channel->active)
                               wire:click="toggle({{ $channel->id }})"
                               wire:loading.attr="disabled" wire:target="toggle"
                               aria-label="{{ $channel->active ? 'Deactivate' : 'Activate' }} {{ $channel->name }}">
                    </label>

                    <button type="button" class="btn btn-ghost btn-sm min-h-11 shrink-0 text-error"
                            wire:click="remove({{ $channel->id }})"
                            wire:confirm="Remove {{ $channel->name }}?"
                            wire:loading.attr="disabled" wire:target="remove">
                        <span wire:loading wire:target="remove" class="loading loading-spinner loading-xs"></span>
                        Remove
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
