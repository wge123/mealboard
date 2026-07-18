<div>
    <h1 class="h3 mb-3">YouTube channels</h1>
    <p class="text-muted">Active channels are polled daily for new recipe videos.</p>

    <form wire:submit="add" class="row g-2 mb-4">
        <div class="col-12 col-md-5">
            <input type="text" class="form-control @error('channelId') is-invalid @enderror"
                   placeholder="Channel ID (UC…)" wire:model="channelId" aria-label="Channel ID">
            @error('channelId') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12 col-md-5">
            <input type="text" class="form-control @error('name') is-invalid @enderror"
                   placeholder="Channel name" wire:model="name" aria-label="Channel name">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12 col-md-2 d-grid">
            <button type="submit" class="btn btn-primary">Add</button>
        </div>
    </form>

    @if ($channels->isEmpty())
        <p class="text-muted">No channels yet — add one above.</p>
    @else
        <ul class="list-group">
            @foreach ($channels as $channel)
                <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="channel-{{ $channel->id }}">
                    <div>
                        <span class="fw-semibold">{{ $channel->name }}</span>
                        <span class="text-muted small ms-2">{{ $channel->channel_id }}</span>
                        @unless ($channel->active)
                            <span class="badge text-bg-secondary ms-2">Inactive</span>
                        @endunless
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="toggle({{ $channel->id }})">
                            {{ $channel->active ? 'Deactivate' : 'Activate' }}
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" wire:click="remove({{ $channel->id }})"
                                wire:confirm="Remove {{ $channel->name }}?">
                            Remove
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
