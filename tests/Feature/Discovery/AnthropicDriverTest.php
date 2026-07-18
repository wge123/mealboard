<?php

use App\Discovery\AnthropicDriver;
use Illuminate\Support\Facades\Process;

function validClaudeCandidate(array $overrides = []): array
{
    return array_merge([
        'title' => 'Lemon Garlic Salmon Bowls',
        'description' => 'Quick pan-seared salmon over rice with a lemon-garlic dressing.',
        'meal_type' => 'dinner',
        'prep_minutes' => 10,
        'cook_minutes' => 15,
        'servings' => 2,
        'instructions' => "1. Sear the salmon.\n2. Whisk the dressing.\n3. Assemble the bowls.",
        'cuisine' => 'Mediterranean',
        'tags' => ['fish', 'quick'],
        'source_url' => null,
        'ingredients' => [
            ['qty' => 2, 'unit' => null, 'name' => 'salmon fillets', 'note' => null],
            ['qty' => 1, 'unit' => 'tbsp', 'name' => 'olive oil', 'note' => null],
            ['qty' => 1, 'unit' => null, 'name' => 'lemon', 'note' => 'juiced'],
        ],
    ], $overrides);
}

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
});

it('parses strict JSON output into schema-checked candidates', function () {
    $json = json_encode([
        validClaudeCandidate(),
        validClaudeCandidate(['title' => 'Chickpea Spinach Skillet', 'meal_type' => 'any']),
    ]);

    Process::fake(['*' => Process::result(output: $json)]);

    $candidates = app(AnthropicDriver::class)->discover(2);

    expect($candidates)->toHaveCount(2)
        ->and($candidates[0]['title'])->toBe('Lemon Garlic Salmon Bowls')
        ->and($candidates[0]['prep_minutes'])->toBe(10)
        ->and($candidates[0]['ingredients'][0])->toBe([
            'qty' => 2.0,
            'unit' => null,
            'name' => 'salmon fillets',
            'note' => null,
        ])
        ->and($candidates[1]['title'])->toBe('Chickpea Spinach Skillet');
});

it('parses output wrapped in fences and preamble', function () {
    $json = json_encode([validClaudeCandidate()]);
    $output = "Here are your recipes:\n```json\n{$json}\n```\nEnjoy!";

    Process::fake(['*' => Process::result(output: $output)]);

    $candidates = app(AnthropicDriver::class)->discover(1);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['title'])->toBe('Lemon Garlic Salmon Bowls');
});

it('discards a malformed candidate while keeping its valid sibling', function () {
    $json = json_encode([
        validClaudeCandidate(['title' => '']), // blank title -> discarded
        validClaudeCandidate(['meal_type' => 'brunch']), // invalid enum -> discarded
        validClaudeCandidate(['ingredients' => [['qty' => 1, 'unit' => null, 'note' => null]]]), // no name -> discarded
        validClaudeCandidate(['title' => 'Survivor Stir-Fry']),
    ]);

    Process::fake(['*' => Process::result(output: $json)]);

    $candidates = app(AnthropicDriver::class)->discover(4);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['title'])->toBe('Survivor Stir-Fry');
});

it('throws when the whole output is unparseable', function () {
    Process::fake(['*' => Process::result(output: 'Sorry, I cannot produce recipes right now.')]);

    app(AnthropicDriver::class)->discover(3);
})->throws(RuntimeException::class, 'not a JSON array');

it('throws when the claude CLI exits non-zero', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

    app(AnthropicDriver::class)->discover(1);
})->throws(RuntimeException::class, 'claude CLI failed');

it('invokes claude -p with the candidate count and prompt section slots', function () {
    Process::fake(['*' => Process::result(output: json_encode([validClaudeCandidate()]))]);

    app(AnthropicDriver::class)->discover(3);

    Process::assertRan(function ($process) {
        $command = $process->command;
        $prompt = $command[2];

        return $command[0] === '/fake/bin/claude'
            && $command[1] === '-p'
            && $command[3] === '--model'
            && $command[4] === 'sonnet'
            && str_contains($prompt, 'exactly 3 recipe candidates')
            && str_contains($prompt, '30 minutes or less')
            && str_contains($prompt, '10 ingredients or fewer')
            && str_contains($prompt, "Taste profile:\n(none yet)")
            && str_contains($prompt, "previously rejected recipes — avoid these:\n(none yet)");
    });
});
