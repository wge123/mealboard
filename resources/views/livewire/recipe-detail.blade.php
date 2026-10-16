<div>
    @if (! $editing)
        @php
            $minutes = (int) $recipe->prep_minutes + (int) $recipe->cook_minutes;
            $ingredientCount = $recipe->ingredients->count();
            $stepCount = $recipe->cookingSteps->count();
            $missingCount = $recipe->recipeTools->filter(fn ($tool) => $missingToolIds->has($tool->id))->count();
            $host = $recipe->source_url ? (parse_url($recipe->source_url, PHP_URL_HOST) ?: $recipe->source_url) : null;
            $fractions = ['0.25' => '¼', '0.50' => '½', '0.75' => '¾', '0.33' => '⅓', '0.67' => '⅔'];
            // Amounts as a cook reads them: ½ not 0.5, and no "count" unit.
            $amount = function ($ingredient) use ($fractions) {
                $qty = $ingredient->pivot->qty;
                $unit = $ingredient->pivot->unit !== 'count' ? (string) $ingredient->pivot->unit : '';
                if ($qty === null) {
                    return $unit;
                }
                $whole = (int) floor((float) $qty);
                $part = $fractions[number_format((float) $qty - $whole, 2)] ?? null;

                return trim(($part ? ($whole ? $whole.$part : $part) : (string) (float) $qty).' '.$unit);
            };
            $statusTone = ['pending' => 'warning', 'approved' => 'neutral', 'rejected' => 'error', 'archived' => 'neutral'][$recipe->status->value];
            $statusMoves = ['approved' => ['Approve', 'tabler-check'], 'rejected' => ['Reject', 'tabler-x'], 'archived' => ['Archive', 'tabler-archive']];
            // A group row: sticky and folds on tap on the phone; a plain heading on desktop.
            $groupRow = 'sticky top-0 z-10 flex min-h-11 w-full items-center gap-2 border-b border-base-300 bg-base-200 px-3 text-left text-xs font-medium tracking-wide text-base-content/70 uppercase lg:pointer-events-none lg:static lg:min-h-0 lg:py-2';
        @endphp

        <div x-data="{ open: { mise: true, cook: true, details: false }, ready: [], done: [] }" class="pb-20 lg:pb-0">
            <x-ui.page-header :title="$recipe->title" :back="route('recipes.index')" class="mb-3">
                <x-slot:actions>
                    <div class="hidden items-center gap-2 lg:flex">
                        <x-ui.button variant="primary" icon="tabler-calendar-plus" :href="route('plan.builder')">Add to a week</x-ui.button>
                        <x-ui.button icon="tabler-pencil" wire:click="startEditing">Edit</x-ui.button>
                        <div class="dropdown dropdown-end">
                            <x-ui.button square variant="ghost" icon="tabler-dots" label="More actions" tabindex="0" />
                            <ul tabindex="0" class="dropdown-content menu z-30 mt-1 w-44 rounded-box border border-base-300 bg-base-100 p-1">
                                @foreach ($statusMoves as $status => [$label, $icon])
                                    @if ($recipe->status->value !== $status)
                                        <li><button type="button" class="min-h-9" wire:click="setStatus('{{ $status }}')" wire:loading.attr="disabled" wire:target="setStatus"><x-dynamic-component :component="$icon" /> {{ $label }}</button></li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </x-slot:actions>
            </x-ui.page-header>

            {{-- The fact line: what you need to know before you start. --}}
            <p class="-mt-3 mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-base-content/70">
                @if ($recipe->status !== \App\Enums\RecipeStatus::Approved)
                    <x-ui.badge :tone="$statusTone">{{ ucfirst($recipe->status->value) }}</x-ui.badge>
                @endif
                <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: var(--meal-{{ $recipe->meal_type->value }})" aria-hidden="true"></span>{{ ucfirst($recipe->meal_type->value) }}</span>
                @if ($minutes > 0)
                    <span class="inline-flex items-center gap-1 tabular-nums"><x-tabler-clock class="size-3.5" />{{ $minutes }} min</span>
                @endif
                <span class="inline-flex items-center gap-1 tabular-nums"><x-tabler-users class="size-3.5" />Serves {{ $recipe->servings }}</span>
                @if ($missingCount)
                    <x-ui.badge tone="warning"><x-tabler-tool class="size-3.5" />{{ $missingCount }} {{ \Illuminate\Support\Str::plural('tool', $missingCount) }} missing</x-ui.badge>
                @endif
            </p>

            {{-- Desktop: the details as a strip above the two columns. --}}
            <x-ui.datagrid class="mb-4 hidden rounded-box border border-base-300 bg-base-100 px-4 py-3 lg:grid">
                <x-ui.datagrid.item label="Cuisine">{{ $recipe->cuisine ? ucfirst($recipe->cuisine) : '' }}</x-ui.datagrid.item>
                <x-ui.datagrid.item label="Time">@if ($minutes > 0)<span class="tabular-nums">{{ $recipe->prep_minutes }} prep, {{ $recipe->cook_minutes }} cook</span>@endif</x-ui.datagrid.item>
                <x-ui.datagrid.item label="Approved">@if ($recipe->approved_at){{ $recipe->approved_at->format('Y-m-d') }}@if ($recipe->approvedBy) by {{ $recipe->approvedBy->name }}@endif @endif</x-ui.datagrid.item>
                <x-ui.datagrid.item label="Source">@if ($host)<a class="link link-hover" href="{{ $recipe->source_url }}" target="_blank" rel="noopener">{{ $host }}</a> · @endif{{ ucfirst($recipe->source->value) }}</x-ui.datagrid.item>
            </x-ui.datagrid>

            <div class="space-y-3 lg:grid lg:grid-cols-[22rem_1fr] lg:items-start lg:gap-4 lg:space-y-0">
                {{-- Mise en place: the tools to get out, then the ingredient Lines to tick. overflow-clip (not hidden) keeps the group row sticky. --}}
                <x-ui.card flush class="overflow-clip lg:sticky lg:top-20" data-section="mise-en-place">
                    <button type="button" class="{{ $groupRow }}" x-on:click="open.mise = ! open.mise" x-bind:aria-expanded="open.mise">
                        <x-tabler-chevron-down class="size-5 lg:hidden" x-bind:class="open.mise ? '' : '-rotate-90'" />
                        <h2 class="contents">Mise en place</h2>
                        <span class="ms-auto normal-case tracking-normal tabular-nums" x-text="ready.length + ' of {{ $ingredientCount }} ready'">0 of {{ $ingredientCount }} ready</span>
                    </button>
                    <div class="lg:block lg:max-h-[calc(100vh-10rem)] lg:overflow-y-auto" x-bind:class="open.mise ? '' : 'hidden'">
                        <div class="flex flex-wrap items-center gap-1.5 border-b border-base-300 px-3 py-2.5">
                            <span class="me-1 text-base-content/70">Get out</span>
                            @foreach ($recipe->recipeTools as $tool)
                                <x-ui.badge :tone="$missingToolIds->has($tool->id) ? 'warning' : 'neutral'">
                                    @if ($tool->count > 1){{ $tool->count }} &times; @endif{{ $tool->alternatives->pluck('word')->implode(' or ') }}
                                    @if ($missingToolIds->has($tool->id))<x-tabler-tool class="size-3" /><span data-missing-tool>missing</span>@endif
                                </x-ui.badge>
                            @endforeach
                        </div>
                        @foreach ($recipe->ingredients as $n => $ingredient)
                            <label class="flex min-h-11 cursor-pointer items-start gap-3 border-b border-base-300 px-3 py-2.5 last:border-b-0 hover:bg-base-200"
                                   x-bind:class="ready.includes({{ $n }}) && 'text-base-content/70'">
                                <input type="checkbox" class="checkbox checkbox-sm checkbox-primary mt-0.5"
                                       x-on:change="$event.target.checked ? ready.push({{ $n }}) : ready.splice(ready.indexOf({{ $n }}), 1);
                                                    if (ready.length === {{ $ingredientCount }}) open.mise = false">
                                <span class="w-16 shrink-0 text-right font-medium tabular-nums">{{ $amount($ingredient) }}</span>
                                <span class="min-w-0">
                                    <span x-bind:class="ready.includes({{ $n }}) && 'line-through'">{{ $ingredient->name }}</span>@if ($ingredient->is_pantry_staple)<x-ui.badge class="ms-1">staple</x-ui.badge>@endif
                                    @if ($ingredient->pivot->note)<span class="block text-base-content/70">{{ $ingredient->pivot->note }}</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </x-ui.card>

                {{-- Cooking: tap a step to mark it done; the first open step is the current one. --}}
                <x-ui.card flush class="overflow-clip" data-section="cooking"
                         x-data="{ get current() { for (let n = 0; n < {{ $stepCount }}; n++) if (! done.includes(n)) return n; return -1 } }">
                    <button type="button" class="{{ $groupRow }} lg:px-4" x-on:click="open.cook = ! open.cook" x-bind:aria-expanded="open.cook">
                        <x-tabler-chevron-down class="size-5 lg:hidden" x-bind:class="open.cook ? '' : '-rotate-90'" />
                        <h2 class="contents">Cooking</h2>
                        <span class="ms-auto normal-case tracking-normal tabular-nums" x-text="done.length + ' of {{ $stepCount }} done'">0 of {{ $stepCount }} done</span>
                    </button>
                    <ol class="lg:block" x-bind:class="open.cook ? '' : 'hidden'">
                        @foreach ($recipe->cookingSteps as $n => $step)
                            <li>
                                <button type="button" class="flex w-full gap-3 border-b border-base-300 border-s-4 px-4 py-3.5 text-left text-base leading-relaxed"
                                        x-on:click="done.includes({{ $n }}) ? done.splice(done.indexOf({{ $n }}), 1) : done.push({{ $n }})"
                                        x-bind:aria-pressed="done.includes({{ $n }})"
                                        x-bind:aria-current="current === {{ $n }} ? 'step' : false"
                                        x-bind:class="{ 'bg-primary/5 border-s-primary': current === {{ $n }}, 'border-s-transparent': current !== {{ $n }}, 'text-base-content/70': done.includes({{ $n }}) }">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full border border-base-300 text-sm font-medium tabular-nums"
                                          x-bind:class="done.includes({{ $n }}) && 'border-primary bg-primary text-primary-content'">
                                        <span x-show="! done.includes({{ $n }})">{{ $n + 1 }}</span>
                                        <span x-show="done.includes({{ $n }})" x-cloak><x-tabler-check class="size-4" /></span>
                                    </span>
                                    <span>{{ $step->text }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>

                {{-- Phone: Details, folded by default; the facts and the actions. Desktop shows the strip and the header actions instead. --}}
                <x-ui.card flush class="overflow-clip lg:hidden" data-section="details">
                    <button type="button" class="{{ $groupRow }}" x-on:click="open.details = ! open.details" x-bind:aria-expanded="open.details">
                        <x-tabler-chevron-down class="size-5 lg:hidden" x-bind:class="open.details ? '' : '-rotate-90'" />
                        <h2 class="contents">Details</h2>
                        <span class="ms-auto truncate normal-case tracking-normal">{{ $recipe->cuisine ? ucfirst($recipe->cuisine) : ucfirst($recipe->status->value) }}</span>
                    </button>
                    <div x-show="open.details" x-cloak>
                        @if ($recipe->description)<p class="border-b border-base-300 px-3 py-2.5 leading-relaxed text-base-content/70">{{ $recipe->description }}</p>@endif
                        <x-ui.datagrid class="px-3 py-3">
                            <x-ui.datagrid.item label="Meal">{{ ucfirst($recipe->meal_type->value) }}</x-ui.datagrid.item>
                            <x-ui.datagrid.item label="Cuisine">{{ $recipe->cuisine ? ucfirst($recipe->cuisine) : '' }}</x-ui.datagrid.item>
                            <x-ui.datagrid.item label="Time">@if ($minutes > 0)<span class="tabular-nums">{{ $recipe->prep_minutes }} prep, {{ $recipe->cook_minutes }} cook</span>@endif</x-ui.datagrid.item>
                            <x-ui.datagrid.item label="Serves">{{ $recipe->servings }}</x-ui.datagrid.item>
                            <x-ui.datagrid.item label="Status">{{ ucfirst($recipe->status->value) }}</x-ui.datagrid.item>
                            <x-ui.datagrid.item label="Approved">@if ($recipe->approved_at){{ $recipe->approved_at->format('Y-m-d') }}@if ($recipe->approvedBy) by {{ $recipe->approvedBy->name }}@endif @endif</x-ui.datagrid.item>
                            <x-ui.datagrid.item label="Source" class="col-span-2">@if ($host)<a class="link link-hover" href="{{ $recipe->source_url }}" target="_blank" rel="noopener">{{ $host }}</a> · @endif{{ ucfirst($recipe->source->value) }}</x-ui.datagrid.item>
                            @if ($recipe->tags)
                                <x-ui.datagrid.item label="Tags" class="col-span-2">{{ implode(', ', $recipe->tags) }}</x-ui.datagrid.item>
                            @endif
                        </x-ui.datagrid>
                        <div class="grid gap-2 border-t border-base-300 p-3">
                            <x-ui.button icon="tabler-pencil" wire:click="startEditing">Edit recipe</x-ui.button>
                            @foreach ($statusMoves as $status => [$label, $icon])
                                @if ($recipe->status->value !== $status)
                                    <x-ui.button :icon="$icon" wire:click="setStatus('{{ $status }}')" busy="setStatus">{{ $label }}</x-ui.button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- Phone bottom bar: one primary action. --}}
            <div class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 mt-4 flex gap-2 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg lg:hidden">
                <x-ui.button variant="primary" icon="tabler-calendar-plus" :href="route('plan.builder')" class="flex-1">Add to a week</x-ui.button>
            </div>
        </div>
    @else
        <x-ui.page-header title="Edit recipe" :back="route('recipes.index')" />

        <form wire:submit="save">
            @include('livewire.partials.recipe-form-fields')

            <div class="mt-6 flex gap-2">
                <x-ui.button variant="primary" type="submit" busy="save">Save</x-ui.button>
                <x-ui.button wire:click="cancelEditing">Cancel</x-ui.button>
            </div>
        </form>
    @endif
</div>
