{{-- Editable cooking steps bound to $steps, one action per step. --}}
<div class="flex flex-col gap-2" data-form-group="steps">
    @foreach ($steps as $index => $step)
        <div class="grid grid-cols-12 gap-2" wire:key="step-{{ $index }}">
            <div class="col-span-9 md:col-span-11">
                <textarea rows="2" class="textarea w-full @error('steps.'.$index) textarea-error @enderror"
                          placeholder="Step {{ $index + 1 }}" aria-label="Cooking step {{ $index + 1 }}"
                          wire:model="steps.{{ $index }}"></textarea>
                @error('steps.'.$index) <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-3 md:col-span-1">
                <button type="button" class="btn btn-ghost min-h-11 w-full text-error" aria-label="Remove step"
                        wire:click="removeStep({{ $index }})">&times;</button>
            </div>
        </div>
    @endforeach
    @error('steps') <p class="text-sm text-error">{{ $message }}</p> @enderror

    <button type="button" class="btn btn-ghost btn-sm min-h-11 w-fit border border-base-300" wire:click="addStep">
        + Add step
    </button>
</div>
