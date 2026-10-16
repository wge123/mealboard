<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

it('renders a button with an href as an anchor and without one as a plain button', function () {
    $link = Blade::render('<x-ui.button href="/plan">Plan</x-ui.button>');
    expect($link)->toContain('<a')->toContain('href="/plan"')->not->toContain('<button');

    $button = Blade::render('<x-ui.button>Save</x-ui.button>');
    expect($button)->toContain('<button')->toContain('type="button"')->not->toContain('<a ');
});

it('lets a form button override the type through attributes', function () {
    expect(Blade::render('<x-ui.button type="submit">Save</x-ui.button>'))
        ->toContain('type="submit"')
        ->not->toContain('type="button"');
});

it('passes extra attributes through to the button', function () {
    expect(Blade::render('<x-ui.button data-test="go" wire:click="go">Go</x-ui.button>'))
        ->toContain('data-test="go"')
        ->toContain('wire:click="go"');
});

it('refuses a square button that has no label', function () {
    Blade::render('<x-ui.button square icon="tabler-plus" />');
})->throws(ViewException::class);

it('uses the label as the accessible name of a square button', function () {
    expect(Blade::render('<x-ui.button square icon="tabler-plus" label="Add recipe" />'))
        ->toContain('aria-label="Add recipe"');
});

it('disables a busy button for its wire target and shows the spinner', function () {
    $html = Blade::render('<x-ui.button busy="lock" icon="tabler-lock">Lock week</x-ui.button>');

    expect($html)
        ->toContain('wire:loading.attr="disabled"')
        ->toContain('wire:target="lock"')
        ->toContain('data-spinner');
});

it('shows no spinner and no wire hooks on a button that is not busy', function () {
    expect(Blade::render('<x-ui.button>Lock week</x-ui.button>'))
        ->not->toContain('wire:loading')
        ->not->toContain('data-spinner');
});

it('swaps the text for the busy label while the target runs', function () {
    $html = Blade::render('<x-ui.button busy="parse" busy-label="Parsing…">Parse</x-ui.button>');

    expect($html)->toContain('Parse')->toContain('Parsing…');
});

it('gives an error or warning alert the alert role and an info alert the status role', function (string $tone, string $role) {
    $html = Blade::render('<x-ui.alert tone="'.$tone.'">Heads up</x-ui.alert>');

    expect($html)->toContain('role="'.$role.'"')->toContain('Heads up');
})->with([
    ['error', 'alert'],
    ['warning', 'alert'],
    ['info', 'status'],
]);

it('rejects an alert tone the kit does not have', function () {
    Blade::render('<x-ui.alert tone="success">Nope</x-ui.alert>');
})->throws(ViewException::class);

it('renders the page header title as an h1 with pretitle, subtitle, back link and actions', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.page-header title="Shopping list" pretitle="Week of 13 Jul" subtitle="Tick as you shop" back="/plan">
            <x-slot:actions><a href="/x">Copy</a></x-slot:actions>
        </x-ui.page-header>
    BLADE);

    expect($html)
        ->toContain('<h1')->toContain('Shopping list')
        ->toContain('Week of 13 Jul')
        ->toContain('Tick as you shop')
        ->toContain('href="/plan"')->toContain('Back')
        ->toContain('Copy');
});

it('leaves out the page header back link when there is no back url', function () {
    expect(Blade::render('<x-ui.page-header title="Plan" />'))->not->toContain('Back');
});

it('renders the card header only when there is a title or actions', function () {
    $titled = Blade::render('<x-ui.card title="Aisles" :count="12">Body</x-ui.card>');
    expect($titled)->toContain('<h2')->toContain('Aisles')->toContain('12')->toContain('Body');

    $plain = Blade::render('<x-ui.card>Body</x-ui.card>');
    expect($plain)->not->toContain('<h2')->toContain('Body');

    $actions = Blade::render('<x-ui.card><x-slot:actions><a href="/e">Edit</a></x-slot:actions>Body</x-ui.card>');
    expect($actions)->toContain('Edit')->toContain('Body');
});

it('renders the card footer slot', function () {
    expect(Blade::render('<x-ui.card>Body<x-slot:footer>Foot note</x-slot:footer></x-ui.card>'))
        ->toContain('Foot note');
});

it('renders an empty state with title, body and action', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.empty-state title="Nothing yet" icon="tabler-calendar">
            Plan a week first.
            <x-slot:action><a href="/plan">Go to plan</a></x-slot:action>
        </x-ui.empty-state>
    BLADE);

    expect($html)->toContain('Nothing yet')->toContain('Plan a week first.')->toContain('Go to plan');
});

it('renders a badge with its text and marks the dot decorative', function () {
    $html = Blade::render('<x-ui.badge tone="warning" dot>Missing tool</x-ui.badge>');

    expect($html)->toContain('Missing tool')->toContain('aria-hidden="true"');
});

it('rejects a badge tone the kit does not have', function () {
    Blade::render('<x-ui.badge tone="purple">x</x-ui.badge>');
})->throws(ViewException::class);
