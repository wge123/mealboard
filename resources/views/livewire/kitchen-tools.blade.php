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
            </li>
        @endforeach
    </ul>
</x-settings.layout>
