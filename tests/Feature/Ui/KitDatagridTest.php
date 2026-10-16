<?php

use Illuminate\Support\Facades\Blade;

it('pairs each label with its value in a description list', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.datagrid>
            <x-ui.datagrid.item label="Cuisine">Thai</x-ui.datagrid.item>
            <x-ui.datagrid.item label="Serves">4</x-ui.datagrid.item>
        </x-ui.datagrid>
    BLADE);

    expect($html)->toContain('<dl')
        ->and($html)->toMatch('/<dt[^>]*>\s*Cuisine\s*<\/dt>\s*<dd[^>]*>\s*Thai\s*<\/dd>/')
        ->and($html)->toMatch('/<dt[^>]*>\s*Serves\s*<\/dt>\s*<dd[^>]*>\s*4\s*<\/dd>/');
});

it('shows a dash for a fact with no value', function () {
    $html = Blade::render('<x-ui.datagrid><x-ui.datagrid.item label="Cuisine" /></x-ui.datagrid>');

    expect($html)->toMatch('/<dd[^>]*>\s*—\s*<\/dd>/');
});

it('passes extra attributes through to the grid', function () {
    expect(Blade::render('<x-ui.datagrid data-test="facts"></x-ui.datagrid>'))->toContain('data-test="facts"');
});

it('lists the datagrid on the kit page', function () {
    $this->app['env'] = 'local';

    $this->get('/kit')->assertOk()->assertSee('Datagrid');
});
