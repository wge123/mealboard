<?php

use App\Discovery\ClaudeCli;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config()->set('mealboard.claude_bin', '/fake/bin/claude');
});

it('sends the prompt on standard input and never as an argument', function () {
    Process::fake(['*claude*' => Process::result(output: 'ok')]);

    expect(trim(app(ClaudeCli::class)->run('cook the noodles')))->toBe('ok');

    Process::assertRan(fn ($process) => $process->command === ['/fake/bin/claude', '-p', '--model', 'sonnet']
        && $process->input === 'cook the noodles');
});

it('runs a prompt larger than the 122880 byte argument limit', function () {
    $prompt = str_repeat('boil the noodles then sizzle the garlic. ', 4000);
    expect(strlen($prompt))->toBeGreaterThan(122880);

    Process::fake(['*claude*' => Process::result(output: 'ok')]);

    app(ClaudeCli::class)->run($prompt);

    Process::assertRan(fn ($process) => $process->input === $prompt
        && collect($process->command)->every(fn ($arg) => strlen($arg) < 1000));
});
