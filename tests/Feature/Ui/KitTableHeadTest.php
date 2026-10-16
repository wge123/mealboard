<?php

use Illuminate\Support\Facades\Blade;

it('hides the table head on a phone unless the table asks to keep it', function () {
    $stacked = Blade::render('<x-ui.table><x-slot:head><tr><x-ui.table.th>Ate</x-ui.table.th></tr></x-slot:head></x-ui.table>');
    $kept = Blade::render('<x-ui.table head-on-phone><x-slot:head><tr><x-ui.table.th>Ate</x-ui.table.th></tr></x-slot:head></x-ui.table>');

    expect($stacked)->not->toContain('data-head-on-phone')
        ->and($kept)->toContain('data-head-on-phone')
        ->and($kept)->toContain('Ate');
});
