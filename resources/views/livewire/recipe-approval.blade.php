<div class="mx-auto w-full max-w-sm">
    <x-ui.page-header title="Approve recipes" class="mb-3">
        <x-slot:actions>
            @if ($recipe !== null)
                <span class="tabular-nums text-base-content/70">{{ $pending }} to go</span>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($recipe === null)
        <div x-data
             x-on:keydown.window="if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || $event.target.isContentEditable) return;
                if ($event.key === 'u' || $event.key === 'U') $wire.undo()">
            <x-ui.card>
                <x-ui.empty-state title="All caught up" icon="tabler-circle-check">
                    Nothing pending to review.
                    <x-slot:action>
                        @if ($history !== [])
                            <x-ui.button icon="tabler-arrow-back-up" wire:click="undo">Undo the last one</x-ui.button>
                        @endif
                        <x-ui.button variant="ghost" :href="route('settings').'#channels'">Add YouTube channels to discover more</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        </div>
    @else
        @php
            $cover = $recipe->youtubeVideoId() !== null ? "https://i.ytimg.com/vi/{$recipe->youtubeVideoId()}/hqdefault.jpg" : $recipe->image_url;
            $minutes = (int) $recipe->prep_minutes + (int) $recipe->cook_minutes;
            $cuisine = $recipe->cuisine ? \Illuminate\Support\Str::ucfirst($recipe->cuisine) : null;
            $amount = function ($ingredient) {
                $unit = $ingredient->pivot->unit !== 'count' ? (string) $ingredient->pivot->unit : '';

                return trim(($ingredient->pivot->qty !== null ? (string) (float) $ingredient->pivot->qty : '').' '.$unit);
            };
        @endphp

        {{--
            One deck per top card: wire:key swaps it when the top card changes, so the drag
            state starts fresh. Dragging past 100px flies the card off and decides it; a tap
            without a drag slides the ingredients sheet up or down. Drags never start on a
            link, button or field.
        --}}
        <div wire:key="deck-{{ $recipe->id }}"
             x-data="{
                 dx: 0, dragging: false, moved: false, startX: 0, flipped: false, leaving: false,
                 down(e) {
                     if (this.leaving || e.target.closest('a, button, select, input, label')) return;
                     this.dragging = true; this.moved = false; this.startX = e.clientX;
                     e.currentTarget.setPointerCapture(e.pointerId);
                 },
                 move(e) {
                     if (! this.dragging) return;
                     this.dx = e.clientX - this.startX;
                     if (Math.abs(this.dx) > 5) this.moved = true;
                 },
                 up() {
                     if (! this.dragging) return;
                     this.dragging = false;
                     if (Math.abs(this.dx) > 100) return this.fly(this.dx > 0 ? 'approve' : 'reject');
                     if (! this.moved) this.flipped = ! this.flipped;
                     this.dx = 0;
                 },
                 fly(action) {
                     if (this.leaving) return;
                     this.leaving = true;
                     this.dx = action === 'approve' ? 600 : -600;
                     setTimeout(() => $wire[action]({{ $recipe->id }}), 200);
                 },
                 skip() {
                     if (this.leaving) return;
                     this.leaving = true;
                     $wire.skip({{ $recipe->id }});
                 },
             }"
             x-on:keydown.window="if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || $event.target.isContentEditable) return;
                const key = $event.key.toLowerCase();
                if (key === 'a') fly('approve');
                if (key === 'r') fly('reject');
                if (key === 's') skip();
                if (key === 'u') $wire.undo();">
            <div class="relative aspect-[4/5] select-none">
                {{-- What a swipe in flight leaves behind: the empty card outline, with a spinner once the wait is long. --}}
                <div class="absolute inset-0 grid place-items-center rounded-box border border-dashed border-base-300" aria-hidden="true">
                    <span class="loading loading-spinner loading-sm hidden text-base-content/70" wire:loading.delay.longer.class.remove="hidden" wire:target="approve,reject,skip"></span>
                </div>

                <article class="absolute inset-0 flex touch-pan-y flex-col overflow-hidden rounded-box border border-base-300 bg-base-100"
                         x-init="$el.animate([{ transform: 'translateY(10px) scale(.96)' }, { transform: 'none' }], { duration: 180, easing: 'ease-out' })"
                         :class="dragging || 'transition-[transform,opacity] duration-200'"
                         :style="`transform: translateX(${dx}px) rotate(${dx / 18}deg)` + (leaving && dx === 0 ? '; opacity: .4' : '')"
                         x-on:pointerdown="down($event)" x-on:pointermove="move($event)" x-on:pointerup="up()" x-on:pointercancel="dragging = false; dx = 0"
                         aria-label="{{ $recipe->title }}">
                    <div class="relative min-h-0 flex-1 border-b border-base-300" style="{{ \App\Support\RecipeCover::background($recipe) }}">
                        @if ($cover)
                            <img src="{{ $cover }}" class="size-full object-cover" alt="{{ $recipe->youtubeVideoId() !== null ? 'Video thumbnail for '.$recipe->title : '' }}" draggable="false">
                        @else
                            <span aria-hidden="true" class="absolute inset-0 grid place-items-center font-display text-6xl font-semibold text-black/15">{{ \App\Support\RecipeCover::initials($recipe) }}</span>
                        @endif
                        @if ($recipe->youtubeVideoId() !== null)
                            <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener" class="absolute top-2 left-2 inline-flex min-h-8 items-center gap-1 rounded-field bg-base-100 px-2 text-xs font-medium"><x-tabler-brand-youtube class="size-4" /> YouTube</a>
                        @endif
                    </div>
                    <div class="p-4">
                        @if ($recipe->recipeRequest !== null)
                            <p class="text-xs text-base-content/70">You asked for &ldquo;{{ $recipe->recipeRequest->query }}&rdquo;</p>
                        @endif
                        <h2 class="text-base leading-snug font-semibold">{{ $recipe->title }}</h2>
                        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-base-content/70">
                            @if ($minutes > 0)<span class="inline-flex items-center gap-1"><x-tabler-clock class="size-4" /> {{ $minutes }} min</span>@endif
                            <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: var(--meal-{{ $recipe->meal_type->value }})"></span>{{ ucfirst($recipe->meal_type->value) }}</span>
                            @if ($cuisine)<span>{{ $cuisine }}</span>@endif
                        </p>
                        @if ($missing !== [])
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($missing as $entry)
                                    <x-ui.badge data-missing-badge><x-tabler-tool class="size-3.5" /> needs: {{ $entry->label() }}</x-ui.badge>
                                @endforeach
                            </div>
                        @endif
                        <p class="mt-2 text-xs text-base-content/70">Tap for ingredients{{ $missing !== [] ? ' and tools' : '' }}</p>
                    </div>

                    {{-- The sheet: ingredient Lines and the missing-tool fixes, sliding up over the card. --}}
                    <div class="absolute inset-0 overflow-y-auto overscroll-contain border-t border-base-300 bg-base-100 p-4" x-show="flipped" x-cloak
                         x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
                        <h2 class="text-base leading-snug font-semibold">{{ $recipe->title }}</h2>
                        @if ($recipe->description)
                            <p class="mt-2 leading-relaxed">{{ $recipe->description }}</p>
                        @endif

                        @if ($recipe->ingredients->isNotEmpty())
                            <h3 class="mt-4 mb-1.5 text-xs font-medium tracking-wide text-base-content/70 uppercase">Ingredients · {{ $recipe->ingredients->count() }}</h3>
                            <ul class="divide-y divide-base-300 rounded-field border border-base-300" data-ingredient-lines>
                                @foreach ($recipe->ingredients as $ingredient)
                                    <li class="flex justify-between gap-3 px-3 py-1.5"><span>{{ $ingredient->name }}</span><span class="tabular-nums text-base-content/70">{{ $amount($ingredient) }}</span></li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($missing !== [])
                            <h3 class="mt-4 mb-1.5 text-xs font-medium tracking-wide text-base-content/70 uppercase">Kitchen tools you don't have</h3>
                            <div class="space-y-2" data-missing-tools>
                                @foreach ($missing as $entry)
                                    <div class="rounded-field border border-base-300 bg-base-200 p-3" wire:key="missing-{{ $entry->tool->id }}">
                                        <p class="flex items-center gap-1.5 font-medium"><x-tabler-tool class="size-4 text-base-content/70" /> {{ $entry->label() }}</p>
                                        @foreach ($entry->alternatives as $alt)
                                            <div class="mt-2" wire:key="missing-alt-{{ $entry->tool->id }}-{{ $loop->index }}">
                                                @if ($alt['kind'] === null)
                                                    <p class="text-base-content/70">&ldquo;{{ $alt['word'] }}&rdquo; is not a kitchen tool you have listed.</p>
                                                    <div class="mt-1.5 flex flex-wrap items-center gap-2" x-data="{ kindId: '', word: @js($alt['word']) }">
                                                        <x-ui.button wire:click="addAsNewTool(word)">Add as a new kitchen tool</x-ui.button>
                                                        <select class="select min-h-11 w-auto sm:min-h-9" x-model="kindId"
                                                                aria-label="Which kitchen tool is {{ $alt['word'] }}?">
                                                            <option value="">It's my&hellip;</option>
                                                            @foreach ($kinds as $kind)
                                                                <option value="{{ $kind->id }}">{{ $kind->display_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <x-ui.button variant="primary" x-show="kindId !== ''" x-on:click="$wire.nameAsMyTool(word, Number(kindId))">Save</x-ui.button>
                                                    </div>
                                                @else
                                                    <x-ui.button :wire:click="'markToolOwned('.$alt['kind']->id.')'">Mark {{ $alt['kind']->display_name }} as owned</x-ui.button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                                @error('tool')
                                    <p class="text-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if ($recipe->source_url)
                            <a class="mt-4 inline-flex min-h-11 items-center gap-1 font-medium text-primary hover:underline" href="{{ $recipe->source_url }}" target="_blank" rel="noopener">Open the source <x-tabler-external-link class="size-4" /></a>
                        @endif
                        <p class="mt-1 text-xs text-base-content/70">Tap again to slide it back down.</p>
                    </div>

                    {{-- Labels that fade in as the card is dragged. --}}
                    <span aria-hidden="true" class="pointer-events-none absolute top-3 left-3 inline-flex items-center gap-1 rounded-field bg-primary px-2 py-1 font-semibold text-primary-content" :style="`opacity: ${Math.max(0, dx / 120)}`" style="opacity: 0"><x-tabler-check class="size-4" /> Keep</span>
                    <span aria-hidden="true" class="pointer-events-none absolute top-3 right-3 inline-flex items-center gap-1 rounded-field bg-neutral px-2 py-1 font-semibold text-neutral-content" :style="`opacity: ${Math.max(0, -dx / 120)}`" style="opacity: 0"><x-tabler-x class="size-4" /> Pass</span>
                </article>
            </div>

            <div class="mt-4 grid grid-cols-[1fr_auto_auto_1fr] gap-2">
                <x-ui.button icon="tabler-x" x-on:click="fly('reject')" aria-label="Reject" title="Pass (R)">Pass</x-ui.button>
                <x-ui.button variant="ghost" icon="tabler-arrow-back-up" wire:click="undo" :disabled="$history === []" aria-label="Undo" title="Undo (U)"><span class="max-sm:sr-only">Undo</span></x-ui.button>
                <x-ui.button variant="ghost" icon="tabler-chevrons-right" x-on:click="skip()" aria-label="Skip for now" title="Skip for now (S)"><span class="max-sm:sr-only">Skip</span></x-ui.button>
                <x-ui.button variant="primary" icon="tabler-check" x-on:click="fly('approve')" aria-label="Approve" title="Keep (A)">Keep</x-ui.button>
            </div>
            <p class="mt-3 text-center text-xs text-base-content/70">Swipe right to keep, left to pass. Skip sends it to the back.<span class="hidden whitespace-nowrap lg:inline"> Keys: <kbd class="kbd kbd-xs">A</kbd> <kbd class="kbd kbd-xs">R</kbd> <kbd class="kbd kbd-xs">S</kbd> <kbd class="kbd kbd-xs">U</kbd></span></p>
        </div>
    @endif
</div>
