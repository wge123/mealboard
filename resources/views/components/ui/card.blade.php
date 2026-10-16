{{-- Kit: card. The header row shows only with a title or actions; `flush` drops the padding so a table runs edge to edge. --}}
@props(['title' => null, 'count' => null, 'flush' => false, 'actions' => null, 'footer' => null])

<section {{ $attributes->merge(['class' => 'rounded-box border border-base-300 bg-base-100']) }}>
    @if ($title || $actions)
        <div class="flex min-h-14 items-center justify-between gap-3 px-4 py-2 {{ $flush ? 'border-b border-base-300' : 'pt-4' }}">
            @if ($title)
                <h2 class="text-base font-semibold">
                    {{ $title }}
                    @if ($count !== null)
                        <span class="ms-1 font-normal tabular-nums text-base-content/70">{{ $count }}</span>
                    @endif
                </h2>
            @endif
            @if ($actions)
                <div class="ms-auto flex items-center gap-2">{{ $actions }}</div>
            @endif
        </div>
    @endif
    <div @class(['p-4' => ! $flush, 'pt-2' => ! $flush && ($title || $actions)])>{{ $slot }}</div>
    @if ($footer)
        <div class="border-t border-base-300 px-4 py-3">{{ $footer }}</div>
    @endif
</section>
