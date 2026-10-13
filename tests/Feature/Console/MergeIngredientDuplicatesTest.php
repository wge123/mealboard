<?php

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\WalmartMatch;
use Illuminate\Support\Facades\DB;

it('merges rows that clean to the same name into the clean one and re-points recipes', function () {
    $tomato = Ingredient::factory()->create(['name' => 'tomato']);
    $line = Ingredient::factory()->create(['name' => '200g tomato', 'is_pantry_staple' => true]);
    $pinch = Ingredient::factory()->create(['name' => 'pinch of salt']);

    $curry = Recipe::factory()->unshaped()->create();
    $curry->ingredients()->attach($line->id, ['qty' => null, 'unit' => null, 'note' => 'ripe']);
    $curry->ingredients()->attach($pinch->id);

    $both = Recipe::factory()->unshaped()->create();
    $both->ingredients()->attach($tomato->id, ['qty' => 2, 'unit' => 'count']);
    $both->ingredients()->attach($line->id);

    WalmartMatch::factory()->create(['ingredient_id' => $line->id, 'product_name' => 'Roma tomatoes']);

    $this->artisan('ingredients:merge-duplicates')
        ->expectsOutputToContain('"tomato" <- "tomato", "200g tomato"')
        ->expectsOutputToContain('"salt" <- "pinch of salt"')
        ->assertSuccessful();

    expect(Ingredient::query()->whereIn('name', ['tomato', '200g tomato', 'pinch of salt', 'salt'])->orderBy('name')->pluck('name')->all())->toBe(['salt', 'tomato']);

    $tomato->refresh();
    expect($tomato->is_pantry_staple)->toBeTrue()
        ->and($tomato->walmartMatch->product_name)->toBe('Roma tomatoes');

    $curryTomato = $curry->ingredients()->where('name', 'tomato')->first();
    expect((float) $curryTomato->pivot->qty)->toBe(200.0)
        ->and($curryTomato->pivot->unit)->toBe('g')
        ->and($curryTomato->pivot->note)->toBe('ripe');

    $bothTomato = $both->ingredients()->where('name', 'tomato')->get();
    expect($bothTomato)->toHaveCount(1)
        ->and((float) $bothTomato->first()->pivot->qty)->toBe(2.0);

    expect($curry->ingredients()->where('name', 'salt')->first()->pivot->note)->toBe('pinch of')
        ->and(Ingredient::query()->where('name', 'salt')->first()->id)->toBe($pinch->id);

    expect(DB::table('ingredient_recipe')->whereIn('recipe_id', [$curry->id, $both->id])->count())->toBe(3);
});

it('writes nothing on a dry run', function () {
    Ingredient::factory()->create(['name' => '200g tomato']);

    $this->artisan('ingredients:merge-duplicates --dry-run')
        ->expectsOutputToContain('"tomato" <- "200g tomato"')
        ->expectsOutputToContain('1 name(s) would change')
        ->assertSuccessful();

    expect(Ingredient::query()->pluck('name')->all())->toBe(['200g tomato']);
});

it('reports clean names as nothing to do', function () {
    Ingredient::factory()->create(['name' => 'tomato']);

    $this->artisan('ingredients:merge-duplicates')
        ->expectsOutputToContain('Every ingredient name is already clean.')
        ->assertSuccessful();
});
