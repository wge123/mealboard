{{-- Editable ingredient rows bound to $rows. Shared by the recipe edit and create forms. --}}
<div class="flex flex-col gap-2">
    @foreach ($rows as $index => $row)
        <div class="grid grid-cols-12 gap-2" wire:key="row-{{ $index }}">
            <div class="col-span-3 md:col-span-2">
                <input type="text" class="input min-h-11 w-full @error('rows.'.$index.'.qty') input-error @enderror"
                       placeholder="Qty" aria-label="Quantity" wire:model="rows.{{ $index }}.qty">
                @error('rows.'.$index.'.qty') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-3 md:col-span-2">
                <select class="select min-h-11 w-full @error('rows.'.$index.'.unit') select-error @enderror"
                        aria-label="Unit" wire:model="rows.{{ $index }}.unit">
                    <option value="">no unit</option>
                    @foreach (\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}">{{ $unit->value }}</option>
                    @endforeach
                </select>
                @error('rows.'.$index.'.unit') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-6 md:col-span-3">
                <input type="text" class="input min-h-11 w-full @error('rows.'.$index.'.name') input-error @enderror"
                       placeholder="Ingredient" aria-label="Ingredient name" wire:model="rows.{{ $index }}.name">
                @error('rows.'.$index.'.name') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-9 md:col-span-4">
                <input type="text" class="input min-h-11 w-full @error('rows.'.$index.'.note') input-error @enderror"
                       placeholder="Note (e.g. diced)" aria-label="Note" wire:model="rows.{{ $index }}.note">
                @error('rows.'.$index.'.note') <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-3 md:col-span-1">
                <button type="button" class="btn btn-ghost min-h-11 w-full text-error" aria-label="Remove ingredient"
                        wire:click="removeRow({{ $index }})">&times;</button>
            </div>
        </div>
    @endforeach

    <button type="button" class="btn btn-ghost btn-sm min-h-11 w-fit border border-base-300" wire:click="addRow">
        + Add ingredient
    </button>
</div>
