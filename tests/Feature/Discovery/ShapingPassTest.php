<?php

use App\Discovery\ClaudeCliFailed;
use App\Discovery\ShapingPass;
use App\Models\KitchenToolKind;
use Illuminate\Support\Facades\Process;

function rawRecipe(array $overrides = []): array
{
    return array_merge([
        'title' => 'Lemon Garlic Salmon Bowls',
        'description' => 'Salmon over rice.',
        'cuisine' => 'Mediterranean',
        'tags' => ['fish'],
        'source_url' => 'https://example.com/salmon',
        'ingredients' => [
            ['qty' => 2.0, 'unit' => null, 'name' => 'salmon fillets', 'note' => null],
            ['qty' => 1.0, 'unit' => null, 'name' => 'lemon', 'note' => 'juiced'],
        ],
        'method' => "Juice the lemon. Sear the salmon in a skillet 4 minutes a side.\nServe.",
    ], $overrides);
}

function shapedModelOutput(array $overrides = []): array
{
    return array_merge([
        'title' => 'ignored by the pass',
        'description' => 'Salmon over rice.',
        'meal_type' => 'dinner',
        'prep_minutes' => 10,
        'cook_minutes' => 8,
        'servings' => 2,
        'cuisine' => 'Mediterranean',
        'tags' => ['fish'],
        'source_url' => 'https://example.com/salmon',
        'tools' => [['alternatives' => ['skillet'], 'count' => 1]],
        'ingredients' => [
            ['qty' => 2, 'unit' => null, 'name' => 'salmon fillets', 'prep_note' => null],
            ['qty' => 1, 'unit' => null, 'name' => 'lemon', 'prep_note' => 'juiced'],
        ],
        'steps' => ['Sear the salmon.', 'Serve.'],
    ], $overrides);
}

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
});

it('returns a shaped candidate with estimated minutes, servings and meal type when the source has none', function () {
    Process::fake(['*' => Process::result(output: json_encode(shapedModelOutput()))]);

    $check = app(ShapingPass::class)->once(rawRecipe());

    expect($check->passes())->toBeTrue()
        ->and($check->candidate['title'])->toBe('Lemon Garlic Salmon Bowls')
        ->and($check->candidate['prep_minutes'])->toBe(10)
        ->and($check->candidate['cook_minutes'])->toBe(8)
        ->and($check->candidate['servings'])->toBe(2)
        ->and($check->candidate['meal_type'])->toBe('dinner')
        ->and($check->candidate['tools'])->toBe([['alternatives' => ['skillet'], 'count' => 1]])
        ->and($check->candidate['steps'])->toBe(['Sear the salmon.', 'Serve.'])
        ->and($check->candidate['source_url'])->toBe('https://example.com/salmon');
});

it('keeps the values the source gives over the model estimate', function () {
    Process::fake(['*' => Process::result(output: json_encode(shapedModelOutput()))]);

    $check = app(ShapingPass::class)->once(rawRecipe([
        'prep_minutes' => 5, 'cook_minutes' => 20, 'servings' => 6, 'meal_type' => 'lunch',
    ]));

    expect($check->candidate)->toMatchArray([
        'prep_minutes' => 5, 'cook_minutes' => 20, 'servings' => 6, 'meal_type' => 'lunch',
    ]);
});

it('puts the raw text, the shared format and the tool kinds in the prompt', function () {
    KitchenToolKind::query()->firstOrCreate(['name' => 'wok']);
    Process::fake(['*' => Process::result(output: json_encode(shapedModelOutput()))]);

    app(ShapingPass::class)->once(rawRecipe(['prep_minutes' => 5]));

    Process::assertRan(function ($process) {
        $prompt = $process->command[2] ?? '';

        return str_contains($prompt, 'Lemon Garlic Salmon Bowls')
            && str_contains($prompt, 'Sear the salmon in a skillet')
            && str_contains($prompt, 'salmon fillets')
            && str_contains($prompt, '"prep_note"')
            && str_contains($prompt, 'wok');
    });
});

it('returns errors for output that fails the check and does not call the model again', function () {
    Process::fake(['*' => Process::result(output: json_encode(shapedModelOutput(['steps' => []])))]);

    $check = app(ShapingPass::class)->once(rawRecipe());

    expect($check->passes())->toBeFalse()
        ->and($check->errors)->not->toBeEmpty();
    Process::assertRanTimes(fn () => true, 1);
});

it('returns errors when the output is not JSON', function () {
    Process::fake(['*' => Process::result(output: 'Sorry, I cannot do that.')]);

    $check = app(ShapingPass::class)->once(rawRecipe());

    expect($check->passes())->toBeFalse()->and($check->errors)->not->toBeEmpty();
});

it('lets a CLI failure through to the caller', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'overloaded', exitCode: 1)]);

    app(ShapingPass::class)->once(rawRecipe());
})->throws(ClaudeCliFailed::class, 'overloaded');

it('extracts ingredients, tools and steps from pasted text without a title or source url', function () {
    Process::fake(['*' => Process::result(output: json_encode([
        'tools' => [['alternatives' => ['skillet'], 'count' => 1]],
        'ingredients' => [['qty' => 2, 'unit' => null, 'name' => 'eggs', 'prep_note' => null]],
        'steps' => ['Fry the eggs.'],
    ]))]);

    $check = app(ShapingPass::class)->fromPastedText("Fried eggs\n2 eggs\nFry the eggs in a skillet.");

    expect($check->passes())->toBeTrue()
        ->and($check->candidate['ingredients'][0]['name'])->toBe('eggs')
        ->and($check->candidate['steps'])->toBe(['Fry the eggs.']);
    Process::assertRan(function ($process) {
        $prompt = $process->command[2] ?? '';

        return str_contains($prompt, '2 eggs') && ! str_contains($prompt, 'Do not invent ingredients');
    });
});

it('reports which part of pasted text could not be extracted', function () {
    Process::fake(['*' => Process::result(output: json_encode(['tools' => [], 'ingredients' => [], 'steps' => []]))]);

    $check = app(ShapingPass::class)->fromPastedText('hello');

    expect($check->passes())->toBeFalse()
        ->and($check->errors)->toContain('tools: at least one tool is required', 'ingredients: at least one ingredient is required', 'steps: at least one step is required');
});
