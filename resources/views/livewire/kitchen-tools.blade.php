<x-settings.layout>
    <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Kitchen tools</h1>
    <p class="mt-1 mb-5 text-sm opacity-60">The kinds of kitchen tool the app knows. Owned kinds come first.</p>

    <ul class="divide-y divide-base-300 rounded-2xl border border-base-300 bg-base-100">
        @foreach ($kinds as $kind)
            <li class="px-4 py-3" wire:key="kind-{{ $kind->id }}">
                <div class="flex items-center gap-3">
                    <span class="min-w-0 flex-1 truncate font-medium {{ $kind->owned ? '' : 'opacity-50' }}">{{ \Illuminate\Support\Str::title($kind->name) }}</span>

                    {{-- Owned toggle: one tap, 44 px target --}}
                    <label class="flex min-h-11 min-w-11 shrink-0 cursor-pointer items-center justify-end gap-2">
                        <span class="text-xs opacity-60">{{ $kind->owned ? 'Owned' : 'Not owned' }}</span>
                        <input type="checkbox" class="toggle toggle-success"
                               aria-label="Owned: {{ \Illuminate\Support\Str::title($kind->name) }}"
                               @checked($kind->owned)
                               wire:click="toggleOwned(@js($kind->name))">
                    </label>
                </div>

                {{-- Note: for people only, never used in matching --}}
                <form class="mt-1 flex items-center gap-2" wire:submit="saveNote(@js($kind->name))">
                    <input type="text" maxlength="255"
                           class="input input-sm min-h-11 min-w-0 flex-1"
                           placeholder="Add a note, e.g. 12-inch, cast iron"
                           aria-label="Note for {{ \Illuminate\Support\Str::title($kind->name) }}"
                           wire:model="notes.{{ $kind->name }}">
                    <button type="submit" class="btn btn-ghost btn-sm min-h-11 min-w-11">Save</button>
                </form>
            </li>
        @endforeach
    </ul>
</x-settings.layout>
