<?php

/**
 * The shipped project-scoped .mcp.json is what lets a Claude Code session in
 * this repo discover the server without anyone editing ~/.claude.json. Nothing
 * else exercises it, so these guard the one way it silently breaks: a bare
 * "php" command. Claude Code launched from the GUI does NOT inherit Herd's
 * PATH, so a relative command fails at spawn with only a dead server to show.
 */
function mcpProjectConfig(): array
{
    expect(base_path('.mcp.json'))->toBeFile();

    return json_decode(file_get_contents(base_path('.mcp.json')), true, flags: JSON_THROW_ON_ERROR);
}

it('ships a project-scoped mcp entry for the mealboard server', function () {
    $server = mcpProjectConfig()['mcpServers']['mealboard'];

    expect($server['args'])->toContain('mcp:serve');
});

it('invokes php by absolute path so a PATH-less spawn still works', function () {
    $server = mcpProjectConfig()['mcpServers']['mealboard'];

    expect($server['command'])->toStartWith('/')
        ->and($server['command'])->toBeFile()
        ->and(is_executable($server['command']))->toBeTrue();
});

it('points at an artisan file that exists', function () {
    $server = mcpProjectConfig()['mcpServers']['mealboard'];

    $artisan = collect($server['args'])->first(fn (string $a) => str_ends_with($a, 'artisan'));

    expect($artisan)->not->toBeNull()
        ->and($artisan)->toStartWith('/')
        ->and($artisan)->toBeFile();
});
