{{-- Editable kitchen tools bound to $tools, as a flush table that stacks per row on the phone. Alternatives are comma separated. --}}
<div data-form-group="tools">
    <div class="hidden grid-cols-12 gap-3 border-b border-base-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-base-content/70 md:grid" aria-hidden="true">
        <span class="col-span-8">Kitchen tool, or alternatives</span>
        <span class="col-span-3">Count</span>
    </div>
    @foreach ($tools as $index => $tool)
        <div class="grid grid-cols-12 gap-x-3 gap-y-2 border-b border-base-300 px-4 py-3" wire:key="tool-{{ $index }}">
            <div class="col-span-12 md:col-span-8">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/70 md:hidden">Kitchen tool</span>
                <input type="text" class="input min-h-11 w-full sm:min-h-9 @error('tools.'.$index.'.alternatives') input-error @enderror"
                       placeholder="Tool, or alternatives separated by commas (e.g. flat-top griddle, large skillet)"
                       aria-label="Kitchen tool alternatives" wire:model="tools.{{ $index }}.alternatives">
                @error('tools.'.$index.'.alternatives') <p class="mt-1 font-medium text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-8 md:col-span-3">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/70 md:hidden">Count</span>
                <input type="number" min="1" class="input min-h-11 w-full sm:min-h-9 @error('tools.'.$index.'.count') input-error @enderror"
                       aria-label="Count" wire:model="tools.{{ $index }}.count">
                @error('tools.'.$index.'.count') <p class="mt-1 font-medium text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-4 flex items-end justify-end md:col-span-1">
                <x-ui.button square variant="danger" icon="tabler-x" label="Remove kitchen tool" wire:click="removeTool({{ $index }})" />
            </div>
        </div>
    @endforeach
    @error('tools') <p class="border-b border-base-300 px-4 py-3 font-medium text-error">{{ $message }}</p> @enderror

    <div class="px-4 py-3">
        <x-ui.button wire:click="addTool">+ Add kitchen tool</x-ui.button>
    </div>
</div>
