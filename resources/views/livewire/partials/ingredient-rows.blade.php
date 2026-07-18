{{-- Editable ingredient rows bound to $rows. Shared by the recipe edit and create forms. --}}
<div>
    @foreach ($rows as $index => $row)
        <div class="row g-2 mb-2" wire:key="row-{{ $index }}">
            <div class="col-3 col-md-2">
                <input type="text" class="form-control @error('rows.'.$index.'.qty') is-invalid @enderror"
                       placeholder="Qty" aria-label="Quantity" wire:model="rows.{{ $index }}.qty">
                @error('rows.'.$index.'.qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-3 col-md-2">
                <select class="form-select @error('rows.'.$index.'.unit') is-invalid @enderror"
                        aria-label="Unit" wire:model="rows.{{ $index }}.unit">
                    <option value="">no unit</option>
                    @foreach (\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}">{{ $unit->value }}</option>
                    @endforeach
                </select>
                @error('rows.'.$index.'.unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-6 col-md-3">
                <input type="text" class="form-control @error('rows.'.$index.'.name') is-invalid @enderror"
                       placeholder="Ingredient" aria-label="Ingredient name" wire:model="rows.{{ $index }}.name">
                @error('rows.'.$index.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-9 col-md-4">
                <input type="text" class="form-control @error('rows.'.$index.'.note') is-invalid @enderror"
                       placeholder="Note (e.g. diced)" aria-label="Note" wire:model="rows.{{ $index }}.note">
                @error('rows.'.$index.'.note') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-3 col-md-1 d-grid">
                <button type="button" class="btn btn-outline-danger" aria-label="Remove ingredient"
                        wire:click="removeRow({{ $index }})">&times;</button>
            </div>
        </div>
    @endforeach

    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="addRow">Add ingredient</button>
</div>
