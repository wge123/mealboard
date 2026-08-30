<?php

use App\Enums\IngredientCategory;
use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Enums\MealType;
use App\Mcp\McpServer;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\WalmartMatch;
use Illuminate\Support\Carbon;

/**
 * Scripted stdio session: pipe line-delimited JSON-RPC requests through the
 * server's stream loop (the same code path `php artisan mcp:serve` runs on
 * STDIN/STDOUT) and decode each response line.
 *
 * @param  list<array<string, mixed>>  $messages
 * @return list<array<string, mixed>>
 */
function mcpSession(array $messages): array
{
    $input = fopen('php://memory', 'r+');
    $output = fopen('php://memory', 'r+');

    foreach ($messages as $message) {
        fwrite($input, json_encode($message)."\n");
    }

    rewind($input);

    app(McpServer::class)->serve($input, $output);

    rewind($output);
    $lines = array_filter(array_map('trim', explode("\n", stream_get_contents($output))));

    return array_values(array_map(fn (string $line) => json_decode($line, true), $lines));
}

/**
 * Decode the JSON payload of a tools/call text response.
 *
 * @param  array<string, mixed>  $response
 * @return array<string, mixed>
 */
function mcpToolData(array $response): array
{
    return json_decode($response['result']['content'][0]['text'], true);
}

/** A locked week (Mon 2026-07-20) with a Monday dinner of two ingredients. */
function mcpSeededPlan(): MealPlan
{
    $plan = MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-20']);
    $recipe = Recipe::factory()->approved()->create([
        'title' => 'Sheet-pan chicken',
        'meal_type' => MealType::Any,
    ]);

    $onion = Ingredient::factory()->create(['name' => 'yellow onion', 'category' => IngredientCategory::Produce]);
    $chicken = Ingredient::factory()->create(['name' => 'chicken thighs', 'category' => IngredientCategory::Meat]);

    $recipe->ingredients()->attach($onion->id, ['qty' => 2, 'unit' => 'count']);
    $recipe->ingredients()->attach($chicken->id, ['qty' => 1, 'unit' => 'lb']);

    WalmartMatch::factory()->create([
        'ingredient_id' => $onion->id,
        'product_url' => 'https://www.walmart.com/ip/yellow-onion/44390949',
    ]);

    $plan->plannedMeals()->create([
        'recipe_id' => $recipe->id,
        'date' => '2026-07-20',
        'slot' => MealSlot::Dinner,
    ]);

    $plan->update(['checked_items' => ['chicken thighs|lb']]);

    return $plan;
}

it('completes the initialize handshake and swallows the initialized notification', function () {
    $responses = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0'],
        ]],
        ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
    ]);

    // The notification produces no output line — only initialize answers.
    expect($responses)->toHaveCount(1)
        ->and($responses[0]['id'])->toBe(1)
        ->and($responses[0]['result']['protocolVersion'])->toBe('2024-11-05')
        ->and($responses[0]['result']['serverInfo']['name'])->toBe('mealboard')
        ->and($responses[0]['result']['capabilities'])->toHaveKey('tools');
});

it('lists all four tools', function () {
    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
    ]);

    expect(array_column($response['result']['tools'], 'name'))->toBe([
        'get_current_shopping_list',
        'save_product_match',
        'get_week_plan',
        'mark_list_purchased',
    ]);

    foreach ($response['result']['tools'] as $tool) {
        expect($tool)->toHaveKeys(['description', 'inputSchema']);
    }
});

it('returns the latest locked week shopping list with keywords, product urls, and checked state', function () {
    mcpSeededPlan();

    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
            'name' => 'get_current_shopping_list',
            'arguments' => [],
        ]],
    ]);

    $data = mcpToolData($response);

    expect($data['week_start_date'])->toBe('2026-07-20')
        ->and($data['purchased_at'])->toBeNull();

    [$onion] = $data['items']['produce'];
    [$chicken] = $data['items']['meat'];

    expect($onion['name'])->toBe('yellow onion')
        ->and($onion['keywords'])->toBe('yellow onion')
        ->and($onion['product_url'])->toBe('https://www.walmart.com/ip/yellow-onion/44390949')
        ->and($onion['checked'])->toBeFalse()
        ->and($chicken['name'])->toBe('chicken thighs')
        ->and($chicken['product_url'])->toBeNull()
        ->and($chicken['checked'])->toBeTrue();
});

it('picks the most recent locked week when several exist', function () {
    mcpSeededPlan();
    MealPlan::factory()->locked()->create(['week_start_date' => '2026-07-13']);

    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
            'name' => 'get_current_shopping_list',
            'arguments' => [],
        ]],
    ]);

    expect(mcpToolData($response)['week_start_date'])->toBe('2026-07-20');
});

it('reports how many weeks stale the served list is', function () {
    mcpSeededPlan();
    $this->travelTo(Carbon::parse('2026-08-30 09:00'));

    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
            'name' => 'get_current_shopping_list',
            'arguments' => [],
        ]],
    ]);

    $data = mcpToolData($response);

    expect($data['week_start_date'])->toBe('2026-07-20')
        ->and($data['weeks_stale'])->toBe(5);
});

it('reports zero weeks stale while the served list is the current week', function () {
    mcpSeededPlan();
    $this->travelTo(Carbon::parse('2026-07-23 09:00'));

    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
            'name' => 'get_current_shopping_list',
            'arguments' => [],
        ]],
    ]);

    expect(mcpToolData($response)['weeks_stale'])->toBe(0);
});

it('errors with not-found when no locked week exists', function () {
    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
            'name' => 'get_current_shopping_list',
            'arguments' => [],
        ]],
    ]);

    expect($response['error']['code'])->toBe(-32002)
        ->and($response['error']['message'])->toBe('No locked week.');
});

it('saves a product match through the tool and upserts on re-confirmation', function () {
    mcpSeededPlan();
    $this->travelTo(Carbon::parse('2026-07-20 09:00'));

    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => [
            'name' => 'save_product_match',
            'arguments' => [
                'ingredient' => 'Chicken Thighs',
                'product_url' => 'https://www.walmart.com/ip/chicken-thighs/27648047',
                'product_name' => 'Freshness Guaranteed Chicken Thighs, 4.7-5.6 lb',
            ],
        ]],
    ]);

    $data = mcpToolData($response);

    expect($data['ingredient'])->toBe('chicken thighs')
        ->and($data['product_url'])->toBe('https://www.walmart.com/ip/chicken-thighs/27648047');

    $match = Ingredient::query()->where('name', 'chicken thighs')->sole()->walmartMatch;

    expect($match->product_name)->toBe('Freshness Guaranteed Chicken Thighs, 4.7-5.6 lb')
        ->and($match->last_confirmed_at->toDateTimeString())->toBe('2026-07-20 09:00:00');

    // Re-confirming the onion (already matched) updates instead of duplicating.
    mcpSession([
        ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => [
            'name' => 'save_product_match',
            'arguments' => [
                'ingredient' => 'yellow onion',
                'product_url' => 'https://www.walmart.com/ip/yellow-onion/999',
                'product_name' => 'Yellow Onion, each',
            ],
        ]],
    ]);

    expect(WalmartMatch::count())->toBe(2)
        ->and(Ingredient::query()->where('name', 'yellow onion')->sole()->walmartMatch->product_url)
        ->toBe('https://www.walmart.com/ip/yellow-onion/999');
});

it('errors with not-found for an unknown ingredient', function () {
    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => [
            'name' => 'save_product_match',
            'arguments' => [
                'ingredient' => 'dragon fruit',
                'product_url' => 'https://www.walmart.com/ip/dragon-fruit/1',
                'product_name' => 'Dragon Fruit',
            ],
        ]],
    ]);

    expect($response['error']['code'])->toBe(-32002)
        ->and($response['error']['message'])->toBe('Ingredient not found: dragon fruit');
});

it('rejects save_product_match with missing or invalid params', function () {
    [$missing, $badUrl] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => [
            'name' => 'save_product_match',
            'arguments' => ['ingredient' => 'yellow onion'],
        ]],
        ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => [
            'name' => 'save_product_match',
            'arguments' => [
                'ingredient' => 'yellow onion',
                'product_url' => 'not a url',
                'product_name' => 'Onion',
            ],
        ]],
    ]);

    expect($missing['error']['code'])->toBe(-32602)
        ->and($badUrl['error']['code'])->toBe(-32602);
});

it('returns the week schedule with recipe titles per slot', function () {
    mcpSeededPlan();

    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => [
            'name' => 'get_week_plan',
            'arguments' => ['week_start' => '2026-07-20'],
        ]],
    ]);

    $data = mcpToolData($response);

    expect($data['week_start_date'])->toBe('2026-07-20')
        ->and($data['status'])->toBe(MealPlanStatus::Locked->value)
        ->and($data['days'])->toHaveCount(5)
        ->and($data['days'][0]['date'])->toBe('2026-07-20')
        ->and($data['days'][0]['slots']['dinner'])->toBe('Sheet-pan chicken')
        ->and($data['days'][0]['slots']['breakfast'])->toBeNull()
        ->and($data['days'][1]['slots']['dinner'])->toBeNull();
});

it('rejects a malformed week_start and reports a missing week', function () {
    [$badFormat, $missingWeek] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => [
            'name' => 'get_week_plan',
            'arguments' => ['week_start' => '20 July 2026'],
        ]],
        ['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => [
            'name' => 'get_week_plan',
            'arguments' => ['week_start' => '2026-07-20'],
        ]],
    ]);

    expect($badFormat['error']['code'])->toBe(-32602)
        ->and($missingWeek['error']['code'])->toBe(-32002);
});

it('marks a week purchased', function () {
    $plan = mcpSeededPlan();
    $this->travelTo(Carbon::parse('2026-07-20 18:30'));

    [$response] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 8, 'method' => 'tools/call', 'params' => [
            'name' => 'mark_list_purchased',
            'arguments' => ['week_start' => '2026-07-20'],
        ]],
    ]);

    expect(mcpToolData($response)['week_start_date'])->toBe('2026-07-20')
        ->and($plan->refresh()->purchased_at->toDateTimeString())->toBe('2026-07-20 18:30:00');
});

it('returns JSON-RPC errors for unknown tools, unknown methods, and unparseable lines', function () {
    [$unknownTool, $unknownMethod] = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/call', 'params' => [
            'name' => 'order_groceries',
            'arguments' => [],
        ]],
        ['jsonrpc' => '2.0', 'id' => 10, 'method' => 'resources/list'],
    ]);

    expect($unknownTool['error']['code'])->toBe(-32602)
        ->and($unknownTool['error']['message'])->toBe('Unknown tool: order_groceries')
        ->and($unknownMethod['error']['code'])->toBe(-32601);

    // A line that isn't JSON gets a parse error instead of crashing the loop.
    $parseError = app(McpServer::class)->handleLine("not json\n");

    expect($parseError['error']['code'])->toBe(-32700);
});

it('runs a full end-to-end stdio session in one pipe', function () {
    mcpSeededPlan();

    $responses = mcpSession([
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []],
        ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
            'name' => 'get_current_shopping_list', 'arguments' => [],
        ]],
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => [
            'name' => 'mark_list_purchased', 'arguments' => ['week_start' => '2026-07-20'],
        ]],
    ]);

    expect($responses)->toHaveCount(4)
        ->and(array_column($responses, 'id'))->toBe([1, 2, 3, 4])
        ->and($responses[1]['result']['tools'])->toHaveCount(4)
        ->and(mcpToolData($responses[2])['items'])->toHaveKeys(['produce', 'meat'])
        ->and(mcpToolData($responses[3])['purchased_at'])->not->toBeNull();

    expect(MealPlan::sole()->purchased_at)->not->toBeNull();
});

it('registers the mcp:serve artisan command', function () {
    expect(collect(Artisan::all())->keys()->contains('mcp:serve'))->toBeTrue();
});
