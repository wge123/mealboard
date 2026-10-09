{{-- Editable kitchen tools bound to $tools: alternatives (comma separated) and a count. --}}
<div class="flex flex-col gap-2" data-form-group="tools">
    @foreach ($tools as $index => $tool)
        <div class="grid grid-cols-12 gap-2" wire:key="tool-{{ $index }}">
            <div class="col-span-12 md:col-span-8">
                <input type="text" class="input min-h-11 w-full @error('tools.'.$index.'.alternatives') input-error @enderror"
                       placeholder="Tool, or alternatives separated by commas (e.g. flat-top griddle, large skillet)"
                       aria-label="Tool alternatives" wire:model="tools.{{ $index }}.alternatives">
                @error('tools.'.$index.'.alternatives') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-8 md:col-span-3">
                <input type="number" min="1" class="input min-h-11 w-full @error('tools.'.$index.'.count') input-error @enderror"
                       aria-label="Count" wire:model="tools.{{ $index }}.count">
                @error('tools.'.$index.'.count') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-4 md:col-span-1">
                <button type="button" class="btn btn-ghost min-h-11 w-full text-error" aria-label="Remove tool"
                        wire:click="removeTool({{ $index }})">&times;</button>
            </div>
        </div>
    @endforeach
    @error('tools') <p class="text-sm text-error">{{ $message }}</p> @enderror

    <button type="button" class="btn btn-ghost btn-sm min-h-11 w-fit border border-base-300" wire:click="addTool">
        + Add tool
    </button>
</div>
