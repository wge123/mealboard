<x-settings.layout>
    <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Kitchen tools</h1>
    <p class="mt-1 mb-5 text-sm opacity-60">The kinds of kitchen tool the app knows. Owned kinds come first.</p>

    <ul class="divide-y divide-base-300 rounded-2xl border border-base-300 bg-base-100">
        @foreach ($kinds as $kind)
            <li class="flex items-center gap-3 px-4 py-3" wire:key="kind-{{ $kind->id }}">
                <div class="min-w-0 flex-1">
                    <span class="block truncate font-medium {{ $kind->owned ? '' : 'opacity-50' }}">{{ \Illuminate\Support\Str::title($kind->name) }}</span>
                    @if ($kind->note)
                        <span class="block truncate text-xs opacity-50">{{ $kind->note }}</span>
                    @endif
                </div>

                @if ($kind->owned)
                    <span class="badge badge-soft badge-success badge-sm shrink-0">Owned</span>
                @endif

                @if ($kind->origin === \App\Models\KitchenToolKind::ORIGIN_HOUSEHOLD)
                    <button type="button" class="btn btn-ghost btn-sm min-h-11 shrink-0 text-error"
                        data-test="delete-kind-{{ $kind->id }}"
                        wire:click="deleteKind({{ $kind->id }})"
                        wire:confirm="Delete {{ \Illuminate\Support\Str::title($kind->name) }}?"
                        aria-label="Delete {{ \Illuminate\Support\Str::title($kind->name) }}">Delete</button>
                @endif
            </li>
        @endforeach
    </ul>

    @error('delete')
        <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
    @enderror

    <form wire:submit="addKind" class="mt-5" data-test="add-kind-form">
        <label for="new-kind" class="mb-1 block text-sm font-medium">Add a tool the app doesn't know</label>
        <div class="flex gap-2">
            <input id="new-kind" type="text" wire:model="newKind" placeholder="Tortilla press"
                class="input input-bordered min-h-11 flex-1">
            <button type="submit" class="btn btn-primary min-h-11">Add</button>
        </div>
        @error('newKind')
            <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>
        @enderror
    </form>
</x-settings.layout>
