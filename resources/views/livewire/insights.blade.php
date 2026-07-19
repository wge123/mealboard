<div>
    <h1 class="h3 mb-4">Insights</h1>

    <div class="row g-4">
        <div class="col-lg-6">
            <h2 class="h5">Ate vs skipped by slot</h2>
            <table class="table table-sm align-middle">
                <thead>
                    <tr><th>Slot</th><th>Ate</th><th>Skipped</th><th style="width: 45%;">Ate rate</th></tr>
                </thead>
                <tbody>
                    @forelse ($slotRates as $label => $row)
                        <tr>
                            <td>{{ ucfirst($label) }}</td>
                            <td>{{ $row['ate'] }}</td>
                            <td>{{ $row['skipped'] }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="flex-grow-1 bg-body-secondary rounded" style="height: 10px;">
                                        <div class="bg-success rounded" style="height: 10px; width: {{ $row['rate'] }}%;"></div>
                                    </div>
                                    <span class="small text-muted">{{ $row['rate'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No meal logs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="col-lg-6">
            <h2 class="h5">Ate vs skipped by weekday</h2>
            <table class="table table-sm align-middle">
                <thead>
                    <tr><th>Weekday</th><th>Ate</th><th>Skipped</th><th style="width: 45%;">Ate rate</th></tr>
                </thead>
                <tbody>
                    @forelse ($weekdayRates as $label => $row)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $row['ate'] }}</td>
                            <td>{{ $row['skipped'] }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="flex-grow-1 bg-body-secondary rounded" style="height: 10px;">
                                        <div class="bg-success rounded" style="height: 10px; width: {{ $row['rate'] }}%;"></div>
                                    </div>
                                    <span class="small text-muted">{{ $row['rate'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No meal logs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="col-lg-6">
            <h2 class="h5">Top recipes by average rating</h2>
            <table class="table table-sm align-middle">
                <thead>
                    <tr><th>#</th><th>Recipe</th><th>Avg rating</th><th>Logs</th><th style="width: 35%;"></th></tr>
                </thead>
                <tbody>
                    @forelse ($topRecipes as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $row['title'] }}</td>
                            <td>{{ $row['avg'] }}</td>
                            <td>{{ $row['count'] }}</td>
                            <td>
                                <div class="bg-body-secondary rounded" style="height: 10px;">
                                    <div class="bg-primary rounded" style="height: 10px; width: {{ $row['avg'] / 5 * 100 }}%;"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No rated meals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="col-lg-6">
            <h2 class="h5">Discovered-recipe rejection rate</h2>
            @if ($discovered['rate'] === null)
                <p class="text-muted">No discovery verdicts yet.</p>
            @else
                <p class="mb-2">
                    {{ $discovered['rejected'] }} rejected vs {{ $discovered['approved'] }} approved —
                    <strong>{{ $discovered['rate'] }}% rejected</strong>
                </p>
                <div class="bg-body-secondary rounded" style="height: 10px; max-width: 24rem;">
                    <div class="bg-danger rounded" style="height: 10px; width: {{ $discovered['rate'] }}%;"></div>
                </div>
            @endif
        </div>
    </div>
</div>
