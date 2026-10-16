<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

it('renders a filter menu button that is collapsed and names its menu', function () {
    $html = Blade::render('<x-ui.filter-menu label="Meal"><x-ui.filter-menu.option>Dinner</x-ui.filter-menu.option></x-ui.filter-menu>');

    expect($html)
        ->toContain('Meal')->toContain('aria-haspopup="true"')->toContain('aria-expanded')
        ->toContain('role="group"')->toContain('Dinner');
});

it('shows how many values are on and announces it', function () {
    $html = Blade::render('<x-ui.filter-menu label="Meal" :count="2"><x-ui.filter-menu.option>Dinner</x-ui.filter-menu.option></x-ui.filter-menu>');

    expect($html)->toContain('aria-label="Meal, 2 on"')->toContain('· 2');
});

it('says nothing about a count when none are on', function () {
    $html = Blade::render('<x-ui.filter-menu label="Meal"><x-ui.filter-menu.option>Dinner</x-ui.filter-menu.option></x-ui.filter-menu>');

    expect($html)->not->toContain('aria-label="Meal,')->not->toContain('· 0');
});

it('shows the chosen value in place of the label for a one-of-N menu', function () {
    $html = Blade::render('<x-ui.filter-menu label="Time" value="≤ 20 min" single><x-ui.filter-menu.option>Any time</x-ui.filter-menu.option></x-ui.filter-menu>');

    expect($html)->toContain('≤ 20 min')->toContain('aria-label="Time, ≤ 20 min"');
});

it('marks a pressed option with aria-pressed true and an unpressed one with false', function () {
    expect(Blade::render('<x-ui.filter-menu.option pressed>Dinner</x-ui.filter-menu.option>'))
        ->toContain('aria-pressed="true"')->toContain('type="button"');
    expect(Blade::render('<x-ui.filter-menu.option>Dinner</x-ui.filter-menu.option>'))
        ->toContain('aria-pressed="false"');
});

it('passes extra attributes through to an option', function () {
    expect(Blade::render('<x-ui.filter-menu.option wire:click="pick" aria-label="Dinner, 3 recipes">Dinner</x-ui.filter-menu.option>'))
        ->toContain('wire:click="pick"')->toContain('aria-label="Dinner, 3 recipes"');
});

it('disables a busy filter menu for its wire target', function () {
    $html = Blade::render('<x-ui.filter-menu label="Meal" busy="toggle"><x-ui.filter-menu.option>Dinner</x-ui.filter-menu.option></x-ui.filter-menu>');

    expect($html)->toContain('wire:loading.attr="disabled"')->toContain('wire:target="toggle"');
});

it('refuses a filter menu with no label', function () {
    Blade::render('<x-ui.filter-menu><x-ui.filter-menu.option>Dinner</x-ui.filter-menu.option></x-ui.filter-menu>');
})->throws(ViewException::class);

it('needs a label: it names the button', function () {
    expect(fn () => Blade::render('<x-ui.filter-menu><x-ui.filter-menu.option>Dinner</x-ui.filter-menu.option></x-ui.filter-menu>'))
        ->toThrow(Exception::class);
});
