{{-- Kit: one datagrid fact. An empty value shows a dash so the grid never has a hole. --}}
@props(['label'])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <dt class="text-xs text-base-content/70">{{ $label }}</dt>
    <dd class="mt-0.5 min-w-0 break-words">{{ $slot->isEmpty() ? '—' : $slot }}</dd>
</div>
