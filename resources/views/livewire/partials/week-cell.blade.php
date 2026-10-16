{{-- One meal on the week ledger: the button or link around it is the behaviour (tray to pick on a draft, tray to log a
     past meal on a locked week, the recipe otherwise). Takes $day, $slot and $class for the wrapper; the rest comes from the screen. --}}
@php
    $meal = $meals->get($cellKey($day, $slot));
    $label = $slot->value.' '.$day->format('l');
    $wrap = $class.' '.($openCell === $cellKey($day, $slot) ? 'ring-2 ring-primary ring-inset' : '');
    $log = $meal ? $logState($meal, $day) : null;
    $actionable = $meal && ($editable || ($loggable && $day->lte(today())));
@endphp
@if ($meal)
    @php $minutes = $minutesOf($meal->recipe); @endphp
    @if ($actionable)
        <button type="button" class="{{ $wrap }}" aria-label="{{ $label }}: {{ $meal->recipe->title }}"
                wire:click="{{ $editable ? 'openPicker' : 'openMeal' }}('{{ $day->toDateString() }}', '{{ $slot->value }}')">
    @else
        <a href="{{ route('recipes.show', $meal->recipe) }}" class="{{ $wrap }}">
    @endif
        <span class="flex items-start gap-1">
            <span class="line-clamp-2 flex-1 font-medium leading-snug {{ $log === 'skipped' ? 'text-base-content/70 line-through' : '' }}">{{ $meal->recipe->title }}</span>
            @if ($log === 'ate')
                <span class="text-success"><x-tabler-check /><span class="sr-only">Eaten</span></span>
            @elseif ($log === 'skipped')
                <span class="text-base-content/70"><x-tabler-x /><span class="sr-only">Skipped</span></span>
            @elseif ($log === 'open')
                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-warning"></span><span class="sr-only">Not logged yet</span>
            @endif
        </span>
        <span class="num text-xs text-base-content/70">{{ $minutes > 0 ? $minutes.' min' : '' }}</span>
    @if ($actionable)
        </button>
    @else
        </a>
    @endif
@elseif ($editable)
    <button type="button" class="{{ $wrap }} items-center justify-center text-base-content/70 hover:bg-base-200"
            wire:click="openPicker('{{ $day->toDateString() }}', '{{ $slot->value }}')" aria-label="Add {{ $label }}">
        <x-tabler-plus />
    </button>
@else
    <div class="{{ $wrap }} items-center justify-center text-base-content/70">—</div>
@endif
