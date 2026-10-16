@foreach ($missing as $entry)
    <x-ui.badge tone="warning" data-missing-badge>needs: {{ $entry->label() }}</x-ui.badge>
@endforeach
