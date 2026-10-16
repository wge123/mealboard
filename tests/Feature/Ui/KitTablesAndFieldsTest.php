<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ViewException;

it('marks a pressed chip with aria-pressed true and an unpressed one with false', function () {
    expect(Blade::render('<x-ui.chip pressed>Dinner</x-ui.chip>'))
        ->toContain('aria-pressed="true"')->toContain('Dinner')->toContain('type="button"');

    expect(Blade::render('<x-ui.chip>Dinner</x-ui.chip>'))->toContain('aria-pressed="false"');
});

it('disables a busy chip for its wire target', function () {
    $html = Blade::render('<x-ui.chip busy="toggle">Dinner</x-ui.chip>');

    expect($html)->toContain('wire:loading.attr="disabled"')->toContain('wire:target="toggle"');
});

it('passes extra attributes through to the chip', function () {
    expect(Blade::render('<x-ui.chip wire:click="pick" data-test="chip">Dinner</x-ui.chip>'))
        ->toContain('wire:click="pick"')->toContain('data-test="chip"');
});

it('renders a segmented toggle as a labelled group of options with aria-pressed', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.segmented label="View">
            <x-ui.segmented.option pressed>Group by</x-ui.segmented.option>
            <x-ui.segmented.option>Shelves</x-ui.segmented.option>
        </x-ui.segmented>
    BLADE);

    expect($html)
        ->toContain('role="group"')->toContain('aria-label="View"')
        ->toContain('aria-pressed="true"')->toContain('aria-pressed="false"')
        ->toContain('Group by')->toContain('Shelves');
});

it('disables every option of a busy segmented toggle for its wire target', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.segmented label="Outcome" busy="save">
            <x-ui.segmented.option>Ate it</x-ui.segmented.option>
            <x-ui.segmented.option>Skipped</x-ui.segmented.option>
        </x-ui.segmented>
    BLADE);

    expect(substr_count($html, 'wire:loading.attr="disabled"'))->toBe(2);
    expect(substr_count($html, 'wire:target="save"'))->toBe(2);
});

it('sets aria-sort on a sorted table header', function (string $sort, string $aria) {
    $html = Blade::render('<x-ui.table.th sort="'.$sort.'">Time</x-ui.table.th>');

    expect($html)->toContain('aria-sort="'.$aria.'"')->toContain('Time');
})->with([
    ['asc', 'ascending'],
    ['desc', 'descending'],
]);

it('renders no aria-sort on a header that is not the sorted column', function () {
    expect(Blade::render('<x-ui.table.th>Recipe</x-ui.table.th>'))->not->toContain('aria-sort');
});

it('makes a sortable header a button that takes the click attributes', function () {
    $html = Blade::render('<x-ui.table.th sortable wire:click="sortBy">Time</x-ui.table.th>');

    expect($html)->toContain('<button')->toContain('wire:click="sortBy"');
});

it('leaves a plain header without a button', function () {
    expect(Blade::render('<x-ui.table.th>Recipe</x-ui.table.th>'))->not->toContain('<button');
});

it('renders the table head slot and the body rows', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.table>
            <x-slot:head><tr><x-ui.table.th>Recipe</x-ui.table.th><x-ui.table.th numeric>Time</x-ui.table.th></tr></x-slot:head>
            <tbody><tr><td>Pad thai</td><td>25</td></tr></tbody>
        </x-ui.table>
    BLADE);

    expect($html)->toContain('<table')->toContain('<thead')->toContain('Recipe')->toContain('Pad thai');
});

it('renders a group row as a button that shows the label and done over total', function () {
    $html = Blade::render('<x-ui.table.group label="Produce" :done="2" :total="5" />');

    expect($html)->toContain('<button')->toContain('Produce')->toContain('2/5')->toContain('aria-expanded');
});

it('marks a complete group as done and an incomplete one as not', function () {
    expect(Blade::render('<x-ui.table.group label="Produce" :done="5" :total="5" />'))->toContain('Done');
    expect(Blade::render('<x-ui.table.group label="Produce" :done="4" :total="5" />'))->not->toContain('Done');
});

it('lets a group row opt out of folding itself when complete', function () {
    $folds = Blade::render('<x-ui.table.group label="Produce" :done="5" :total="5" />');
    $stays = Blade::render('<x-ui.table.group label="Produce" :done="5" :total="5" :fold-when-complete="false" />');

    expect($folds)->not->toBe($stays);
});

it('hides a row under a group through the table fold state', function () {
    expect(Blade::render('<x-ui.table.row group="Produce"><td>Basil</td></x-ui.table.row>'))
        ->toContain('x-show')->toContain('Produce')->toContain('Basil');
});

it('binds the field label to the input and shows the hint', function () {
    $html = Blade::render('<x-ui.field label="Email" name="email" hint="We never share it" />');

    expect($html)
        ->toContain('<label')->toContain('for="email"')->toContain('Email')
        ->toContain('id="email"')->toContain('name="email"')
        ->toContain('We never share it');
});

it('shows the validation error for the field name and marks the input invalid', function () {
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['email' => ['The email is not valid.']])));

    $html = Blade::render('<x-ui.field label="Email" name="email" />');

    expect($html)->toContain('The email is not valid.')->toContain('aria-invalid="true"')->toContain('aria-describedby');
});

it('shows no error line for a field whose name has no error', function () {
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['other' => ['Nope']])));

    $html = Blade::render('<x-ui.field label="Email" name="email" />');

    expect($html)->not->toContain('Nope')->not->toContain('aria-invalid');
});

it('passes input attributes through to the field input', function () {
    $html = Blade::render('<x-ui.field label="Search" name="q" type="search" wire:model.live="q" placeholder="Basil" />');

    expect($html)->toContain('type="search"')->toContain('wire:model.live="q"')->toContain('placeholder="Basil"');
});

it('renders a slotted control in a field under the same label', function () {
    $html = Blade::render('<x-ui.field label="Meal" name="meal"><select id="meal" name="meal"><option>Dinner</option></select></x-ui.field>');

    expect($html)->toContain('for="meal"')->toContain('<select')->not->toContain('<input');
});

it('renders a group row without done as a plain count that never folds itself', function () {
    $html = Blade::render('<x-ui.table.group label="Dinner" :total="4" />');

    expect($html)->toContain('Dinner')->toContain('>4<')->not->toContain('0/4')->not->toContain('Done')->toContain("auto['Dinner'] = false");
});

it('refuses a field with no name', function () {
    Blade::render('<x-ui.field label="Email" />');
})->throws(ViewException::class);
