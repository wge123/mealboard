<div class="mx-auto w-full max-w-md">
    <h1 class="mb-4 font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight">Approve recipes</h1>

    @if ($recipe === null)
        <div class="rounded-2xl border border-base-300 bg-base-200 px-6 py-14 text-center">
            <p class="text-6xl" aria-hidden="true">🎉</p>
            <p class="mt-4 font-[family-name:var(--font-display)] text-2xl font-semibold">All caught up</p>
            <p class="mt-1 text-sm opacity-60">Nothing pending to review.</p>
            <a class="btn btn-ghost btn-sm mt-5 min-h-11 border border-base-300" href="{{ route('settings.channels') }}">
                Add YouTube channels to discover more
            </a>
        </div>
    @else
        @php($videoId = $recipe->youtubeVideoId())
        @php($minutes = (int) $recipe->prep_minutes + (int) $recipe->cook_minutes)
        @php($emoji = ['breakfast' => '🍳', 'lunch' => '🥪', 'dinner' => '🍽️', 'any' => '🍲'][$recipe->meal_type->value])

        <p class="mb-3 text-sm opacity-60">{{ $reviewed + 1 }} of {{ $reviewed + $pending }} pending</p>

        {{-- wire:key swaps the element per recipe so Alpine re-initializes and the slide/fade-in runs between cards. --}}
        <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100" wire:key="card-{{ $recipe->id }}"
             x-data="{ shown: false }"
             x-init="$nextTick(() => shown = true)"
             x-show="shown"
             x-transition:enter="transition duration-300 ease-out"
             x-transition:enter-start="translate-x-8 opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             @keydown.window="
                 if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || $event.target.isContentEditable) return;
                 if ($event.key === 'a' || $event.key === 'A') $wire.approve({{ $recipe->id }});
                 if ($event.key === 'r' || $event.key === 'R') $wire.reject({{ $recipe->id }});
             ">
            {{-- Media header: video thumbnail, or a meal-type gradient placeholder. --}}
            <figure class="relative aspect-video">
                @if ($videoId !== null)
                    <img src="https://i.ytimg.com/vi/{{ $videoId }}/hqdefault.jpg"
                         class="h-full w-full object-cover"
                         alt="Video thumbnail for {{ $recipe->title }}">
                    <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener"
                       class="badge badge-neutral absolute bottom-2 left-2 border-0">
                        ▶ YouTube ↗
                    </a>
                @else
                    <div class="flex h-full w-full items-center justify-center bg-base-200"
                         style="background-image: linear-gradient(135deg, color-mix(in oklab, var(--meal-{{ $recipe->meal_type->value }}) 45%, transparent), transparent)">
                        <span class="text-6xl" aria-hidden="true">{{ $emoji }}</span>
                    </div>
                @endif
            </figure>

            <div class="p-5">
                @if ($recipe->recipeRequest !== null)
                    <p class="mb-1 text-xs opacity-60">You asked for &ldquo;{{ $recipe->recipeRequest->query }}&rdquo;</p>
                @endif

                <h2 class="font-[family-name:var(--font-display)] text-2xl leading-tight font-semibold">{{ $recipe->title }}</h2>

                <div class="mt-2.5 flex flex-wrap gap-1.5">
                    @if ($minutes > 0)
                        <span class="badge badge-ghost">{{ $minutes }} min</span>
                    @endif
                    <span class="badge badge-ghost slot-label font-medium" style="--slot-accent: var(--meal-{{ $recipe->meal_type->value }})">
                        {{ ucfirst($recipe->meal_type->value) }}
                    </span>
                    @if ($recipe->cuisine)
                        <span class="badge badge-ghost">{{ ucfirst($recipe->cuisine) }}</span>
                    @endif
                </div>

                @if ($recipe->description)
                    <div class="mt-3" x-data="{ expanded: false }">
                        <p class="text-sm leading-relaxed opacity-80" :class="expanded || 'line-clamp-3'">{{ $recipe->description }}</p>
                        @if (mb_strlen($recipe->description) > 180)
                            <button type="button" class="btn btn-ghost btn-xs min-h-11 px-1 font-normal text-primary"
                                    @click="expanded = !expanded" x-text="expanded ? 'less' : 'more'"></button>
                        @endif
                    </div>
                @endif

                @if ($recipe->ingredients->isNotEmpty())
                    <p class="mt-4 text-xs font-semibold tracking-wide uppercase opacity-60">Key ingredients</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($recipe->ingredients->take(8) as $ingredient)
                            <span class="badge badge-ghost">{{ $ingredient->name }}</span>
                        @endforeach
                        @if ($recipe->ingredients->count() > 8)
                            <span class="badge badge-ghost opacity-60">+{{ $recipe->ingredients->count() - 8 }} more</span>
                        @endif
                    </div>
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

                <div class="mt-6 flex flex-col gap-2">
                    <button type="button" class="btn btn-primary btn-lg w-full"
                            wire:click="approve({{ $recipe->id }})"
                            wire:loading.attr="disabled" wire:target="approve, reject">
                        <span wire:loading wire:target="approve" class="loading loading-spinner loading-xs"></span>
                        Approve <kbd class="kbd kbd-sm hidden lg:inline-flex">A</kbd>
                    </button>
                    <button type="button" class="btn btn-ghost min-h-11 w-full"
                            wire:click="reject({{ $recipe->id }})"
                            wire:loading.attr="disabled" wire:target="approve, reject">
                        <span wire:loading wire:target="reject" class="loading loading-spinner loading-xs"></span>
                        Reject <kbd class="kbd kbd-sm hidden lg:inline-flex">R</kbd>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
