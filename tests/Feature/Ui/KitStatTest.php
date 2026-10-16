<?php

use Illuminate\Support\Facades\Blade;

it('pairs the label with its value and shows the hint beneath', function () {
    $html = Blade::render('<dl><x-ui.stat label="Meals logged" value="42" hint="6 weeks" /></dl>');

    expect($html)
        ->toMatch('/<dt[^>]*>\s*Meals logged\s*<\/dt>\s*<dd[^>]*>\s*42\s*<\/dd>/')
        ->toContain('6 weeks');
});

it('puts the suffix after the value inside the same value element', function () {
    $html = Blade::render('<dl><x-ui.stat label="Eaten" value="75" suffix="%" /></dl>');

    expect($html)->toMatch('/<dd[^>]*>\s*75\s*<span[^>]*>\s*%\s*<\/span>\s*<\/dd>/');
});

it('shows a dash, and no suffix, when there is no value', function () {
    $html = Blade::render('<dl><x-ui.stat label="Eaten" :value="null" suffix="%" /></dl>');

    expect($html)->toMatch('/<dd[^>]*>\s*—\s*<\/dd>/')->not->toContain('%');
});

it('passes extra attributes through to the stat', function () {
    expect(Blade::render('<dl><x-ui.stat label="Eaten" value="1" data-test="stat" /></dl>'))->toContain('data-test="stat"');
});

it('lists the stat on the kit page', function () {
    $this->app['env'] = 'local';

    $this->get('/kit')->assertOk()->assertSee('Stat');
});
