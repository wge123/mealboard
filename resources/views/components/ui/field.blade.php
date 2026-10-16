{{-- Kit: form field. The label is bound to the control by id (default: the name). With no slot it renders an <input> that takes the
     attributes (wire:model, placeholder) and `type`; put a <select> or <textarea> in the slot, with the same id, for those.
     The error line is the validation error for `name`, or `error` when a screen passes one. Text sizes come from the base input rule. --}}
@props(['label', 'name' => null, 'hint' => null, 'error' => null, 'type' => 'text', 'id' => null])

@php
    if (blank($name)) {
        throw new InvalidArgumentException('A field needs a name: it binds the label and finds the validation error.');
    }
    $id ??= $name;
    $error ??= isset($errors) ? $errors->first($name) : null;
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-xs font-semibold uppercase tracking-wide text-base-content/70">{{ $label }}</label>
    @if ($slot->isEmpty())
        <input
            id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->merge(['class' => 'input min-h-11 w-full sm:min-h-9 '.($error ? 'input-error' : '')]) }}
        >
    @else
        {{ $slot }}
    @endif
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-base-content/70">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="font-medium text-error">{{ $error }}</p>
    @endif
</div>
