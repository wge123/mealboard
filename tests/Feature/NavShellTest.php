<?php

use App\Enums\RecipeStatus;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;

/**
 * The links of one nav landmark, found by its accessible name.
 *
 * @return array{text: string, hrefs: list<string>, links: array<string, string>}
 */
function navLandmark(string $html, string $label): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);
    $nav = $xpath->query("//nav[@aria-label='{$label}']")->item(0);
    expect($nav)->not->toBeNull("no nav labelled {$label}");

    $links = [];
    foreach ($xpath->query('.//a', $nav) as $a) {
        $links[trim(preg_replace('/\s+/', ' ', $a->textContent))] = $a->getAttribute('href');
    }

    return [
        'text' => trim(preg_replace('/\s+/', ' ', $nav->textContent)),
        'hrefs' => array_values($links),
        'links' => $links,
    ];
}

it('lists every destination in the desktop top bar and the phone tabs', function () {
    $html = $this->actingAs(User::factory()->create())->get('/plan')->assertOk()->getContent();

    $top = navLandmark($html, 'Main');
    foreach (['Plan', 'Shop', 'Recipes', 'Approve', 'Log', 'Insights'] as $name) {
        expect($top['links'])->toHaveKey($name);
    }
    expect($top['text'])->toContain('Settings')->toContain('Log out');

    $tabs = navLandmark($html, 'Primary');
    foreach (['Plan', 'Shop', 'Approve', 'Log', 'Recipes', 'Insights', 'Settings'] as $name) {
        expect($tabs['links'])->toHaveKey($name);
    }
    expect($tabs['text'])->toContain('More')->toContain('Log out');
});

it('leaves Request out of both navs', function () {
    $html = $this->actingAs(User::factory()->create())->get('/plan')->getContent();

    expect(navLandmark($html, 'Main')['hrefs'])->not->toContain(route('recipes.request'));
    expect(navLandmark($html, 'Primary')['hrefs'])->not->toContain(route('recipes.request'));
});

it('links Shop to /shop even when no week is locked', function () {
    MealPlan::factory()->create(); // draft only

    $html = $this->actingAs(User::factory()->create())->get('/plan')->getContent();

    expect(navLandmark($html, 'Main')['links']['Shop'])->toBe(route('shop'));
    expect(navLandmark($html, 'Primary')['links']['Shop'])->toBe(route('shop'));
});

it('sends /shop to the latest locked week', function () {
    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);
    $latest = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    MealPlan::factory()->create(['week_start_date' => '2026-07-27']); // draft

    $this->actingAs(User::factory()->create())
        ->get('/shop')
        ->assertRedirect(route('plan.shopping-list', $latest->id));
});

it('shows an empty state linking to Plan on /shop when no week is locked', function () {
    MealPlan::factory()->create(); // draft only

    $this->actingAs(User::factory()->create())
        ->get('/shop')
        ->assertOk()
        ->assertSee('Lock a week to build your list')
        ->assertSee('href="'.route('plan.builder').'"', false);
});

it('requires a login for /shop', function () {
    $this->get('/shop')->assertRedirect(route('login'));
});

it('shows the pending recipe count on Approve in both navs', function () {
    Recipe::factory()->count(3)->create(['status' => RecipeStatus::Pending]);

    $html = $this->actingAs(User::factory()->create())->get('/plan')->getContent();

    expect(navLandmark($html, 'Main')['links'])->toHaveKey('Approve 3');
    expect(navLandmark($html, 'Primary')['links'])->toHaveKey('Approve 3');
});

it('shows no count on Approve when nothing is pending', function () {
    $html = $this->actingAs(User::factory()->create())->get('/plan')->getContent();

    expect(navLandmark($html, 'Main')['links'])->toHaveKey('Approve');
    expect(navLandmark($html, 'Primary')['links'])->toHaveKey('Approve');
});

it('redirects the root to the plan', function () {
    $this->get('/')->assertRedirect('/plan');
});

it('no longer ships the unused root views', function () {
    expect(view()->exists('home'))->toBeFalse()
        ->and(view()->exists('welcome'))->toBeFalse();
});

it('offers the theme switch as Light, Dark and System in the top bar and the phone drawer', function () {
    $html = $this->actingAs(User::factory()->create())->get('/plan')->assertOk()->getContent();

    expect(substr_count($html, 'aria-label="Theme"'))->toBe(2);
    foreach (['Light', 'Dark', 'System'] as $label) {
        expect(substr_count($html, ">{$label}</button>"))->toBe(2);
    }
    expect($html)->not->toContain('>Auto</button>');
});
