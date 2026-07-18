<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0">Shopping list</h1>
            <span class="text-muted small">
                Week of {{ $mealPlan->week_start_date->format('j M Y') }} ({{ $mealPlan->status->value }})
            </span>
        </div>
        <a href="{{ route('plan.builder') }}" class="btn btn-outline-secondary btn-sm">Back to plan</a>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="include-staples"
                   wire:model.live="includeStaples">
            <label class="form-check-label" for="include-staples">Include pantry staples</label>
        </div>

        <div class="d-flex gap-2 ms-auto">
            <button type="button" class="btn btn-outline-primary btn-sm" x-data="{ copied: false }"
                    x-text="copied ? 'Copied!' : 'Copy as markdown'"
                    @click="navigator.clipboard.writeText(@js($markdown)); copied = true; setTimeout(() => copied = false, 1500)">
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" x-data="{ copied: false }"
                    x-text="copied ? 'Copied!' : 'Copy as plain list'"
                    @click="navigator.clipboard.writeText(@js($plain)); copied = true; setTimeout(() => copied = false, 1500)">
            </button>
        </div>
    </div>

    @forelse ($items as $category => $lines)
        <h2 class="h5 text-uppercase text-muted mt-4">{{ ucfirst($category) }}</h2>
        <ul class="list-group mb-3">
            @foreach ($lines as $item)
                @php($key = $item['name'].'|'.($item['unit'] ?? ''))
                @php($isChecked = in_array($key, $checked, true))
                <li class="list-group-item">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="item-{{ md5($key) }}"
                               wire:click="toggleItem('{{ $key }}')" @checked($isChecked)>
                        <label class="form-check-label {{ $isChecked ? 'text-decoration-line-through text-muted' : '' }}"
                               for="item-{{ md5($key) }}">
                            @if ($item['qty'] !== null)
                                <strong>{{ rtrim(rtrim(number_format($item['qty'], 2, '.', ''), '0'), '.') }}</strong>
                            @endif
                            @if ($item['unit'] !== null && $item['unit'] !== 'count')
                                {{ $item['unit'] }}
                            @endif
                            {{ $item['name'] }}
                            @if ($item['notes'] !== [])
                                <span class="text-muted small">({{ implode('; ', $item['notes']) }})</span>
                            @endif
                        </label>
                    </div>
                </li>
            @endforeach
        </ul>
    @empty
        <p class="text-muted">Nothing to buy — the week has no planned meals with ingredients.</p>
    @endforelse
</div>
