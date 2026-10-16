{{-- /kit: every kit component in every variant. Local only (routes/web.php). Flip the theme in the nav to check dark. --}}
@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Kit"
        pretitle="Local only"
        subtitle="Every shared component in every variant. Flip the theme to check light and dark."
        back="{{ url('/') }}"
    >
        <x-slot:actions>
            <x-ui.button icon="tabler-plus">Action</x-ui.button>
            <x-ui.button variant="primary" icon="tabler-check">Primary action</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-col gap-6">
        <x-ui.card title="Page header">
            <div class="flex flex-col gap-6">
                <x-ui.page-header title="Title only" class="mb-0" />
                <x-ui.page-header title="Everything" pretitle="Week of 13 Jul" subtitle="One help line in muted text." back="#" class="mb-0">
                    <x-slot:actions><x-ui.button>Secondary</x-ui.button></x-slot:actions>
                </x-ui.page-header>
            </div>
        </x-ui.card>

        <x-ui.card title="Button" count="4 variants">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button variant="primary">Primary</x-ui.button>
                    <x-ui.button>Secondary</x-ui.button>
                    <x-ui.button variant="ghost">Ghost</x-ui.button>
                    <x-ui.button variant="danger">Danger</x-ui.button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button variant="primary" icon="tabler-lock">With icon</x-ui.button>
                    <x-ui.button icon="tabler-shopping-cart">With icon</x-ui.button>
                    <x-ui.button variant="ghost" icon="tabler-copy">With icon</x-ui.button>
                    <x-ui.button variant="danger" icon="tabler-trash">With icon</x-ui.button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button href="#" icon="tabler-arrow-right">As a link</x-ui.button>
                    <x-ui.button square icon="tabler-plus" label="Add" />
                    <x-ui.button square variant="ghost" icon="tabler-dots" label="More" />
                    <x-ui.button square variant="primary" icon="tabler-check" label="Done" />
                    <x-ui.button disabled>Disabled</x-ui.button>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/70">Busy (action class)</p>
                    <p class="text-base-content/70">On a screen the prop is <code>busy="wireTarget"</code> (and <code>busy-label="Parsing…"</code> for slow AI actions). Below, the busy markup frozen in its working state.</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button variant="primary" disabled><span class="loading loading-spinner loading-xs" aria-hidden="true"></span> Lock week</x-ui.button>
                        <x-ui.button disabled><span class="loading loading-spinner loading-xs" aria-hidden="true"></span> Parsing…</x-ui.button>
                        <x-ui.button square disabled label="Saving"><span class="loading loading-spinner loading-xs" aria-hidden="true"></span></x-ui.button>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Card" count="3">
            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.card>Plain card, no header.</x-ui.card>
                <x-ui.card title="Titled" :count="12">With a title and a count.</x-ui.card>
                <x-ui.card title="Actions and footer">
                    Body text.
                    <x-slot:actions><x-ui.button variant="ghost" icon="tabler-pencil">Edit</x-ui.button></x-slot:actions>
                    <x-slot:footer>Footer slot</x-slot:footer>
                </x-ui.card>
                <x-ui.card title="Flush" :count="2" flush class="md:col-span-3">
                    <div class="divide-y divide-base-300">
                        <div class="px-4 py-2.5">A row that runs edge to edge</div>
                        <div class="px-4 py-2.5">Another row</div>
                    </div>
                </x-ui.card>
            </div>
        </x-ui.card>

        <x-ui.card title="Empty state" count="2">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.card>
                    <x-ui.empty-state title="Nothing here yet" />
                </x-ui.card>
                <x-ui.card>
                    <x-ui.empty-state title="No week planned" icon="tabler-calendar-week">
                        Let Mealboard fill it from your approved recipes.
                        <x-slot:action><x-ui.button variant="primary" icon="tabler-wand">Fill the week</x-ui.button></x-slot:action>
                    </x-ui.empty-state>
                </x-ui.card>
            </div>
        </x-ui.card>

        <x-ui.card title="Alert" count="3 tones">
            <div class="flex flex-col gap-3">
                <x-ui.alert tone="info">
                    <p class="font-semibold">Info (role status)</p>
                    <p class="text-base-content/70">What the app prepared, and the one thing to do next.</p>
                </x-ui.alert>
                <x-ui.alert tone="warning">
                    <p class="font-semibold">Warning (role alert)</p>
                    <p class="text-base-content/70">This list is from an earlier week.</p>
                </x-ui.alert>
                <x-ui.alert tone="error">
                    <p class="font-semibold">Error (role alert)</p>
                    <p class="text-base-content/70">The recipe could not be read. Try again.</p>
                </x-ui.alert>
            </div>
        </x-ui.card>

        <x-ui.card title="Badge" count="5 tones">
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge>Neutral</x-ui.badge>
                    <x-ui.badge tone="primary">Primary</x-ui.badge>
                    <x-ui.badge tone="info">Info</x-ui.badge>
                    <x-ui.badge tone="warning">Warning</x-ui.badge>
                    <x-ui.badge tone="error">Error</x-ui.badge>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge dot>Neutral</x-ui.badge>
                    <x-ui.badge tone="primary" dot>Matched</x-ui.badge>
                    <x-ui.badge tone="info" dot>Search</x-ui.badge>
                    <x-ui.badge tone="warning" dot>Missing tool</x-ui.badge>
                    <x-ui.badge tone="error" dot>Error</x-ui.badge>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Refresh (page convention)">
            <p class="mb-3 text-base-content/70">
                Lists that update wrap their results in <code>wire:loading.delay.class="opacity-50 pointer-events-none"</code>
                with <code>wire:loading.attr="aria-busy"</code> and an explicit <code>wire:target</code>. It dims after 200ms, so quick round trips never flicker. Shown here frozen in its dimmed state.
            </p>
            <div class="flex gap-6">
                <div class="flex-1">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-base-content/70">Resting</p>
                    <div class="divide-y divide-base-300 rounded-box border border-base-300">
                        <div class="px-4 py-2.5">Pad thai</div>
                        <div class="px-4 py-2.5">Shakshuka</div>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-base-content/70">Refreshing</p>
                    <div class="pointer-events-none divide-y divide-base-300 rounded-box border border-base-300 opacity-50" aria-busy="true">
                        <div class="px-4 py-2.5">Pad thai</div>
                        <div class="px-4 py-2.5">Shakshuka</div>
                    </div>
                </div>
            </div>
        </x-ui.card>
    </div>
@endsection
