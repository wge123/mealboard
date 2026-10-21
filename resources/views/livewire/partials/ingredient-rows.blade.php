{{-- Editable ingredient Lines bound to $rows, as a flush table that stacks per row on the phone. Shared by the recipe edit and create forms. --}}
<div data-form-group="rows">
    <div class="hidden grid-cols-12 gap-3 border-b border-base-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-base-content/70 md:grid" aria-hidden="true">
        <span class="col-span-2">Qty</span>
        <span class="col-span-2">Unit</span>
        <span class="col-span-3">Ingredient</span>
        <span class="col-span-2">Prep note</span>
        <span class="col-span-2">Prep bowl</span>
    </div>
    @foreach ($rows as $index => $row)
        <div class="grid grid-cols-12 gap-x-3 gap-y-2 border-b border-base-300 px-4 py-3" wire:key="row-{{ $index }}">
            <div class="col-span-5 md:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/70 md:hidden">Qty</span>
                <input type="text" class="input min-h-11 w-full sm:min-h-9 @error('rows.'.$index.'.qty') input-error @enderror"
                       placeholder="Qty" aria-label="Quantity" wire:model="rows.{{ $index }}.qty">
                @error('rows.'.$index.'.qty') <p class="mt-1 font-medium text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-7 md:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/70 md:hidden">Unit</span>
                <select class="select min-h-11 w-full sm:min-h-9 @error('rows.'.$index.'.unit') select-error @enderror"
                        aria-label="Unit" wire:model="rows.{{ $index }}.unit">
                    <option value="">no unit</option>
                    @foreach (\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}">{{ $unit->value }}</option>
                    @endforeach
                </select>
                @error('rows.'.$index.'.unit') <p class="mt-1 font-medium text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-12 md:col-span-3">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/70 md:hidden">Ingredient</span>
                <input type="text" class="input min-h-11 w-full sm:min-h-9 @error('rows.'.$index.'.name') input-error @enderror"
                       placeholder="Ingredient" aria-label="Ingredient name" wire:model="rows.{{ $index }}.name">
                @error('rows.'.$index.'.name') <p class="mt-1 font-medium text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-12 md:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/70 md:hidden">Prep note</span>
                <input type="text" class="input min-h-11 w-full sm:min-h-9 @error('rows.'.$index.'.prep_note') input-error @enderror"
                       placeholder="Prep note (e.g. diced)" aria-label="Prep note" wire:model="rows.{{ $index }}.prep_note">
                @error('rows.'.$index.'.prep_note') <p class="mt-1 font-medium text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-9 md:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/70 md:hidden">Prep bowl</span>
                <input type="text" class="input min-h-11 w-full sm:min-h-9 @error('rows.'.$index.'.prep_bowl') input-error @enderror"
                       placeholder="Bowl (e.g. spice bowl)" aria-label="Prep bowl" wire:model="rows.{{ $index }}.prep_bowl">
                @error('rows.'.$index.'.prep_bowl') <p class="mt-1 font-medium text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-3 flex items-end justify-end md:col-span-1">
                <x-ui.button square variant="danger" icon="tabler-x" label="Remove ingredient" wire:click="removeRow({{ $index }})" />
            </div>
        </div>
    @endforeach
    @error('rows') <p class="border-b border-base-300 px-4 py-3 font-medium text-error">{{ $message }}</p> @enderror

    <div class="px-4 py-3">
        <x-ui.button wire:click="addRow">+ Add ingredient</x-ui.button>
    </div>
</div>
