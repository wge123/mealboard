<div class="mx-auto w-full max-w-sm">
    <div class="mb-3 flex items-baseline justify-between">
        <h1 class="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Approve recipes</h1>
        @if ($recipe !== null)
            <span class="text-sm text-base-content/70">{{ $pending }} to go</span>
        @endif
    </div>

    @if ($recipe === null)
        <div class="rounded-3xl border border-base-300 bg-base-200 px-6 py-14 text-center"
             x-data
             x-on:keydown.window="if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || $event.target.isContentEditable) return;
                if ($event.key === 'u' || $event.key === 'U') $wire.undo()">
            <p class="text-6xl" aria-hidden="true">🎉</p>
            <p class="mt-4 font-[family-name:var(--font-display)] text-2xl font-semibold">All caught up</p>
            <p class="mt-1 text-sm text-base-content/70">Nothing pending to review.</p>
            <div class="mt-5 flex flex-col items-center gap-2">
                @if ($history !== [])
                    <button type="button" class="btn btn-ghost btn-sm min-h-11 border border-base-300" wire:click="undo">↶ Undo the last one</button>
                @endif
                <a class="btn btn-ghost btn-sm min-h-11 border border-base-300" href="{{ route('settings').'#channels' }}">
                    Add YouTube channels to discover more
                </a>
            </div>
        </div>
    @else
        @php
            $cover = fn ($card) => $card->youtubeVideoId() !== null ? "https://i.ytimg.com/vi/{$card->youtubeVideoId()}/hqdefault.jpg" : $card->image_url;
            $minutes = (int) $recipe->prep_minutes + (int) $recipe->cook_minutes;
            $facts = collect([$minutes > 0 ? "{$minutes} min" : null, ucfirst($recipe->meal_type->value), $recipe->cuisine ? \Illuminate\Support\Str::ucfirst($recipe->cuisine) : null])->filter()->implode(' · ');
        @endphp

        {{--
            One deck per top card: wire:key swaps it when the top card changes, so the drag
            state starts fresh. Dragging past 100px flies the card off and decides it; a tap
            without a drag turns it over. Drags never start on a link, button or field.
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
            <div class="relative aspect-[3/4] select-none">
                {{-- The next two cards, peeking out underneath. --}}
                @foreach ($under->reverse() as $card)
                    @php($depth = $loop->remaining + 1)
                    <div class="absolute inset-0 overflow-hidden rounded-3xl border border-base-300 shadow-md" aria-hidden="true" wire:key="under-{{ $card->id }}"
                         style="{{ \App\Support\RecipeCover::background($card) }}; transform: translateY({{ $depth * 14 }}px) scale({{ 1 - $depth * 0.03 }})">
                        @if ($cover($card))
                            <img src="{{ $cover($card) }}" class="size-full object-cover" alt="" loading="lazy">
                        @endif
                    </div>
                @endforeach

                <article class="absolute inset-0 touch-pan-y overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-xl"
                         x-init="$el.animate([{ transform: 'translateY(10px) scale(.96)' }, { transform: 'none' }], { duration: 180, easing: 'ease-out' })"
                         :class="dragging || 'transition-[transform,opacity] duration-200'"
                         :style="`transform: translateX(${dx}px) rotate(${dx / 18}deg)` + (leaving && dx === 0 ? '; opacity: .4' : '')"
                         x-on:pointerdown="down($event)" x-on:pointermove="move($event)" x-on:pointerup="up()" x-on:pointercancel="dragging = false; dx = 0"
                         aria-label="{{ $recipe->title }}">
                    {{-- Front: the cover. --}}
                    <div class="absolute inset-0" style="{{ \App\Support\RecipeCover::background($recipe) }}" x-show="! flipped">
                        @if ($cover($recipe))
                            <img src="{{ $cover($recipe) }}" class="size-full object-cover" alt="{{ $recipe->youtubeVideoId() !== null ? 'Video thumbnail for '.$recipe->title : '' }}" draggable="false">
                        @else
                            <span aria-hidden="true" class="absolute inset-0 grid place-items-center pb-24 font-[family-name:var(--font-display)] text-7xl font-semibold text-black/15">{{ \App\Support\RecipeCover::initials($recipe) }}</span>
                        @endif
                        @if ($recipe->youtubeVideoId() !== null)
                            <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener" class="badge badge-neutral absolute top-3 left-3 min-h-8 border-0">▶ YouTube ↗</a>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/50 to-transparent p-5 pt-16 text-white">
                            @if ($recipe->recipeRequest !== null)
                                <p class="text-xs text-white/75">You asked for &ldquo;{{ $recipe->recipeRequest->query }}&rdquo;</p>
                            @endif
                            <h2 class="font-[family-name:var(--font-display)] text-2xl leading-tight font-semibold">{{ $recipe->title }}</h2>
                            <p class="mt-1 text-sm text-white/80">{{ $facts }}</p>
                            @if ($missing !== [])
                                <div class="mt-2 flex flex-wrap gap-1">@include('livewire.partials.missing-tools-badge', ['missing' => $missing])</div>
                            @endif
                            <p class="mt-2 text-xs text-white/60">Tap for ingredients{{ $missing !== [] ? ' and tools' : '' }}</p>
                        </div>
                    </div>

                    {{-- Back: the details and the missing-tool fixes. --}}
                    <div class="absolute inset-0 overflow-y-auto overscroll-contain p-5" x-show="flipped" x-cloak>
                        <h2 class="font-[family-name:var(--font-display)] text-xl leading-tight font-semibold">{{ $recipe->title }}</h2>
                        <p class="mt-1 text-sm text-base-content/70">{{ $facts }}</p>
                        @if ($recipe->description)
                            <p class="mt-3 text-sm leading-relaxed">{{ $recipe->description }}</p>
                        @endif

                        @if ($recipe->ingredients->isNotEmpty())
                            <h3 class="mt-4 mb-1.5 text-xs font-semibold tracking-widest text-base-content/60 uppercase">Ingredients</h3>
                            <ul class="flex flex-wrap gap-1.5">
                                @foreach ($recipe->ingredients as $ingredient)
                                    <li class="badge badge-ghost h-auto py-1">{{ $ingredient->name }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($missing !== [])
                            <div class="mt-4" data-missing-tools>
                                @foreach ($missing as $entry)
                                    <div class="mb-2 rounded-xl border border-warning/40 bg-warning/10 p-3" wire:key="missing-{{ $entry->tool->id }}">
                                        @include('livewire.partials.missing-tools-badge', ['missing' => [$entry]])
                                        @foreach ($entry->alternatives as $alt)
                                            <div class="mt-2 text-sm" wire:key="missing-alt-{{ $entry->tool->id }}-{{ $loop->index }}">
                                                @if ($alt['kind'] === null)
                                                    <p class="font-medium">&ldquo;{{ $alt['word'] }}&rdquo; is not a kitchen tool you have listed.</p>
                                                    <div class="mt-1 flex flex-wrap items-center gap-2" x-data="{ kindId: '' }">
                                                        <button type="button" class="btn btn-outline btn-sm min-h-11"
                                                                wire:click='addAsNewTool(@js($alt['word']))'>Add as a new kitchen tool</button>
                                                        <select class="select select-bordered select-sm min-h-11" x-model="kindId"
                                                                aria-label="Which kitchen tool is {{ $alt['word'] }}?">
                                                            <option value="">It's my&hellip;</option>
                                                            @foreach ($kinds as $kind)
                                                                <option value="{{ $kind->id }}">{{ $kind->display_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="button" class="btn btn-outline btn-sm min-h-11" x-show="kindId !== ''"
                                                                @click='$wire.nameAsMyTool(@js($alt['word']), Number(kindId))'>Save</button>
                                                    </div>
                                                @else
                                                    <button type="button" class="btn btn-outline btn-sm min-h-11"
                                                            wire:click="markToolOwned({{ $alt['kind']->id }})">Mark {{ $alt['kind']->display_name }} as owned</button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                                @error('tool')
                                    <p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if ($recipe->source_url)
                            <a class="link mt-4 inline-block min-h-11 text-sm" href="{{ $recipe->source_url }}" target="_blank" rel="noopener">Open the source ↗</a>
                        @endif
                        <p class="mt-2 text-xs text-base-content/60">Tap again to turn the card back.</p>
                    </div>

                    {{-- Stamps that fade in as the card is dragged. --}}
                    <span aria-hidden="true" class="pointer-events-none absolute top-6 left-6 -rotate-12 rounded-lg border-4 border-success bg-base-100/70 px-3 py-1 text-2xl font-black text-success" :style="`opacity: ${Math.max(0, dx / 120)}`" style="opacity: 0">KEEP</span>
                    <span aria-hidden="true" class="pointer-events-none absolute top-6 right-6 rotate-12 rounded-lg border-4 border-error bg-base-100/70 px-3 py-1 text-2xl font-black text-error" :style="`opacity: ${Math.max(0, -dx / 120)}`" style="opacity: 0">NOPE</span>
                </article>
            </div>

            <div class="mt-7 flex items-center justify-center gap-4">
                <button type="button" class="btn btn-circle size-16 border-2 border-error bg-base-100 text-2xl text-error" x-on:click="fly('reject')" aria-label="Reject" title="Reject (R)">✕</button>
                <button type="button" class="btn btn-circle btn-ghost size-12 border border-base-300 text-lg" wire:click="undo" @disabled($history === []) aria-label="Undo" title="Undo (U)">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
                </button>
                <button type="button" class="btn btn-circle btn-ghost size-12 border border-base-300 text-lg" x-on:click="skip()" aria-label="Skip for now" title="Skip for now (S)">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/></svg>
                </button>
                <button type="button" class="btn btn-circle size-16 border-2 border-success bg-base-100 text-2xl text-success" x-on:click="fly('approve')" aria-label="Approve" title="Approve (A)">✓</button>
            </div>
            <p class="mt-3 text-center text-xs text-base-content/60">Swipe right to keep, left to pass. Skip sends it to the back.<span class="hidden lg:inline"> Keys: A, R, S, U.</span></p>
        </div>
    @endif
</div>
