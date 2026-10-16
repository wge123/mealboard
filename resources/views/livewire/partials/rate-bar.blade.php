{{-- Insights: a thin herb-green rate bar for a table cell, with the percent beside it. $rate is 0 to 100, $label names it. --}}
<div class="flex items-center gap-2">
    <div class="h-1.5 min-w-12 flex-1 overflow-hidden rounded-full bg-base-300" role="meter" aria-label="{{ $label }}"
         aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full rounded-full bg-primary" style="width: {{ $rate }}%"></div>
    </div>
    <span class="w-10 shrink-0 text-end tabular-nums">{{ $rate }}%</span>
</div>
