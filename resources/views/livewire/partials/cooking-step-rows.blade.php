{{-- Editable cooking steps bound to $steps, one action per step, as a flush numbered table. --}}
<div data-form-group="steps">
    @foreach ($steps as $index => $step)
        <div class="grid grid-cols-[2rem_1fr_auto] items-start gap-x-3 border-b border-base-300 px-4 py-3" wire:key="step-{{ $index }}">
            <span class="pt-2 text-base-content/70 tabular-nums" aria-hidden="true">{{ $index + 1 }}</span>
            <div>
                <textarea rows="2" class="textarea w-full @error('steps.'.$index) textarea-error @enderror"
                          placeholder="Step {{ $index + 1 }}" aria-label="Cooking step {{ $index + 1 }}"
                          wire:model="steps.{{ $index }}"></textarea>
                @error('steps.'.$index) <p class="mt-1 font-medium text-error">{{ $message }}</p> @enderror
            </div>
            <x-ui.button square variant="danger" icon="tabler-x" label="Remove step" wire:click="removeStep({{ $index }})" />
        </div>
    @endforeach
    @error('steps') <p class="border-b border-base-300 px-4 py-3 font-medium text-error">{{ $message }}</p> @enderror

    <div class="px-4 py-3">
        <x-ui.button wire:click="addStep">+ Add step</x-ui.button>
    </div>
</div>
