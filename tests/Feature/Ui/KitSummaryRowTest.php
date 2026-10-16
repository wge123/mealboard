<?php

use Illuminate\Support\Facades\Blade;

it('shows the answer in the closed row and keeps the panel closed', function () {
    $html = Blade::render('<x-ui.summary-row label="Weekday time limit" value="30" suffix="min" help="Prep and cook, in total.">Presets</x-ui.summary-row>');

    expect($html)->toContain('Weekday time limit')->toContain('30')->toContain('min')
        ->toContain('aria-expanded="false"')->toContain('type="button"');
});

it('points the toggle at the panel it opens', function () {
    $html = Blade::render('<x-ui.summary-row id="limit" label="Limit" value="30">Presets</x-ui.summary-row>');

    expect($html)->toContain('aria-controls="limit-panel"')->toContain('id="limit-panel"');
});

it('can start open', function () {
    expect(Blade::render('<x-ui.summary-row label="Limit" value="30" open>Presets</x-ui.summary-row>'))
        ->toContain('aria-expanded="true"');
});

it('renders the help line and slot in the panel', function () {
    expect(Blade::render('<x-ui.summary-row label="Limit" value="30" help="Prep and cook, in total.">Presets here</x-ui.summary-row>'))
        ->toContain('Prep and cook, in total.')->toContain('Presets here');
});

it('announces an ink chip as pressed or not', function () {
    expect(Blade::render('<x-ui.chip tone="ink" pressed>Cilantro</x-ui.chip>'))->toContain('aria-pressed="true"')->toContain('Cilantro');
    expect(Blade::render('<x-ui.chip tone="ink">Cilantro</x-ui.chip>'))->toContain('aria-pressed="false"');
});

it('keeps a passed data-test attribute ahead of aria-pressed', function () {
    expect(Blade::render('<x-ui.chip data-test="chip" pressed>Dinner</x-ui.chip>'))
        ->toContain('data-test="chip" aria-pressed="true"');
});
