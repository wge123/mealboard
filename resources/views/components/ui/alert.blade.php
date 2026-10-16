{{-- Kit: alert. A white box with a tone border and dot. error and warning interrupt (role alert); info is polite (role status). --}}
@props(['tone' => 'info'])

@php
    $tones = [
        'info' => ['border-info/50', 'bg-info', 'status'],
        'warning' => ['border-warning/50', 'bg-warning', 'alert'],
        'error' => ['border-error/50', 'bg-error', 'alert'],
    ];

    if (! isset($tones[$tone])) {
        throw new InvalidArgumentException("Unknown alert tone [{$tone}].");
    }
    [$border, $dot, $role] = $tones[$tone];
@endphp

<div role="{{ $role }}" {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-box border bg-base-100 p-4 text-left {$border}"]) }}>
    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
