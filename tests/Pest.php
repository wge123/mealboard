<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The items of a settings list as [server order, browser order]: the browser
 * re-sorts a tapped item the way resources/js/app.js does, ticked items first,
 * each ordered by its data-key-on or data-key-off. An item is named by its
 * control's aria-label. The control is a pressed button or a checkbox; a
 * heading <li data-state="on|off"> has none and is named by its text.
 *
 * @return array{0: list<string>, 1: list<string>}
 */
function tileOrders(string $html): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8">'.$html);
    $xpath = new DOMXPath($dom);

    $tiles = [];
    foreach ($xpath->query('//li[@data-key-on]') as $li) {
        $control = $xpath->query('.//*[@aria-pressed] | .//input[@type="checkbox"]', $li)->item(0);
        $on = match (true) {
            $control === null => $li->getAttribute('data-state') === 'on',
            $control->nodeName === 'input' => $control->hasAttribute('checked'),
            default => $control->getAttribute('aria-pressed') === 'true',
        };
        $tiles[] = [
            'list' => $li->parentNode->getNodePath(),
            'name' => $control?->getAttribute('aria-label') ?? trim(preg_replace('/\s+/', ' ', $li->textContent)),
            'sort' => [$on ? 0 : 1, $li->getAttribute($on ? 'data-key-on' : 'data-key-off')],
        ];
    }

    $server = array_column($tiles, 'name');
    // The browser moves an item only within its own list: an aisle, or the whole grid.
    $browser = [];
    foreach (array_unique(array_column($tiles, 'list')) as $list) {
        $inList = array_filter($tiles, fn ($tile) => $tile['list'] === $list);
        usort($inList, fn ($a, $b) => strcmp($a['sort'][0].' '.$a['sort'][1], $b['sort'][0].' '.$b['sort'][1]));
        array_push($browser, ...array_column($inList, 'name'));
    }

    return [$server, $browser];
}
