<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

it('binds the checkbox to its label so a tap on the name ticks it', function () {
    $html = Blade::render('<x-ui.checklist-line id="staple-1" label="Soy sauce" />');

    expect($html)
        ->toContain('type="checkbox"')->toContain('id="staple-1"')
        ->toContain('<label')->toContain('for="staple-1"')->toContain('Soy sauce');
});

it('ticks the checkbox when the line is checked and leaves it clear when not', function () {
    expect(Blade::render('<x-ui.checklist-line id="a" label="Salt" checked />'))->toMatch('/ checked[ >]/');
    expect(Blade::render('<x-ui.checklist-line id="a" label="Salt" />'))->not->toMatch('/ checked[ >]/');
});

it('passes attributes to the checkbox, so a click handler and an accessible name land on the control', function () {
    $html = Blade::render('<x-ui.checklist-line id="a" label="Salt" aria-label="Staple: salt" wire:click="toggle(1)" />');

    expect($html)->toContain('aria-label="Staple: salt"')->toContain('wire:click="toggle(1)"');
});

it('shows a muted count and says what it counts to assistive tech', function () {
    $html = Blade::render('<x-ui.checklist-line id="a" label="Salt" :count="12" count-label="used in 12 recipes" />');

    expect($html)->toContain('12')->toContain('used in 12 recipes');
});

it('renders a trailing action beside the label, outside the label that ticks the box', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.checklist-line id="a" label="Wok">
            <x-slot:action><button type="button" aria-label="Details for Wok">...</button></x-slot:action>
        </x-ui.checklist-line>
    BLADE);

    expect($html)->toContain('aria-label="Details for Wok"')
        ->and(strpos($html, '</label>'))->toBeLessThan(strpos($html, 'aria-label="Details for Wok"'));
});

it('shows an icon instead of a checkbox when it has one', function () {
    $html = Blade::render('<x-ui.checklist-line label="Wok" icon="tabler-flame" />');

    expect($html)->not->toContain('type="checkbox"')->toContain('<svg')->toContain('Wok');
});

it('needs an id to bind a checkbox to its label', function () {
    Blade::render('<x-ui.checklist-line label="Salt" />');
})->throws(ViewException::class);

it('puts a leading glyph between the checkbox and the name, inside the label that ticks the box', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.checklist-line id="a" label="Wok">
            <x-slot:leading><svg data-test="glyph"></svg></x-slot:leading>
        </x-ui.checklist-line>
    BLADE);

    expect(strpos($html, 'type="checkbox"'))->toBeLessThan(strpos($html, 'data-test="glyph"'))
        ->and(strpos($html, 'data-test="glyph"'))->toBeLessThan(strpos($html, 'Wok'))
        ->and(strpos($html, 'data-test="glyph"'))->toBeLessThan(strpos($html, '</label>'));
});

it('flags a warning count so the screen can mark a need that is not met', function () {
    $warn = Blade::render('<x-ui.checklist-line id="a" label="Wok" :count="3" count-label="used in 3 recipes" warn />');
    $calm = Blade::render('<x-ui.checklist-line id="a" label="Wok" :count="3" count-label="used in 3 recipes" />');

    expect($warn)->toContain('data-warning')
        ->and($calm)->not->toContain('data-warning');
});
