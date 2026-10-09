@foreach ($missing as $entry)
    <span class="badge badge-warning badge-sm" data-missing-badge>needs: {{ $entry->label() }}</span>
@endforeach
