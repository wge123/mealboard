<?php

namespace App\Mcp;

use App\Actions\Planning\BuildShoppingList;
use App\Actions\Planning\SaveProductMatch;
use App\Enums\MealPlanStatus;
use App\Enums\MealSlot;
use App\Models\Ingredient;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\WalmartMatch;
use App\Support\CleanIngredientKeywords;
use Throwable;

/**
 * Minimal MCP server: a JSON-RPC 2.0 loop over line-delimited JSON on
 * STDIN/STDOUT (protocol 2024-11-05, tools capability only). No SDK — the
 * tools call straight into the existing Actions and models.
 */
class McpServer
{
    public const PROTOCOL_VERSION = '2024-11-05';

    private const PARSE_ERROR = -32700;

    private const INVALID_REQUEST = -32600;

    private const METHOD_NOT_FOUND = -32601;

    private const INVALID_PARAMS = -32602;

    private const INTERNAL_ERROR = -32603;

    private const NOT_FOUND = -32002;

    public function __construct(
        private BuildShoppingList $buildShoppingList,
        private SaveProductMatch $saveProductMatch,
        private CleanIngredientKeywords $cleanKeywords,
    ) {}

    /**
     * Run a session over the given streams until the input closes.
     *
     * @param  resource  $input
     * @param  resource  $output
     */
    public function serve($input, $output): void
    {
        while (($line = fgets($input)) !== false) {
            if (trim($line) === '') {
                continue;
            }

            $response = $this->handleLine($line);

            if ($response !== null) {
                fwrite($output, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                fflush($output);
            }
        }
    }

    /**
     * Handle one raw line. Null means no response (notifications).
     *
     * @return array<string, mixed>|null
     */
    public function handleLine(string $line): ?array
    {
        $message = json_decode($line, true);

        if (! is_array($message)) {
            return $this->error(null, self::PARSE_ERROR, 'Parse error: expected one JSON object per line.');
        }

        return $this->handleMessage($message);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    public function handleMessage(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;

        if (! is_string($method)) {
            return $this->error($id, self::INVALID_REQUEST, 'Invalid request: missing method.');
        }

        try {
            return match ($method) {
                'initialize' => $this->result($id, [
                    'protocolVersion' => self::PROTOCOL_VERSION,
                    'capabilities' => ['tools' => (object) []],
                    'serverInfo' => ['name' => 'mealboard', 'version' => '1.0.0'],
                ]),
                'notifications/initialized' => null,
                'tools/list' => $this->result($id, ['tools' => $this->toolDefinitions()]),
                'tools/call' => $this->callTool($id, is_array($message['params'] ?? null) ? $message['params'] : []),
                default => $this->error($id, self::METHOD_NOT_FOUND, "Method not found: {$method}"),
            };
        } catch (McpError $e) {
            return $this->error($id, $e->rpcCode, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->error($id, self::INTERNAL_ERROR, 'Internal error: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(mixed $id, array $params): array
    {
        $arguments = $params['arguments'] ?? [];

        if (! is_array($arguments)) {
            throw new McpError(self::INVALID_PARAMS, 'Invalid params: arguments must be an object.');
        }

        $data = match ($params['name'] ?? null) {
            'get_current_shopping_list' => $this->getCurrentShoppingList(),
            'save_product_match' => $this->saveProductMatchTool($arguments),
            'get_week_plan' => $this->getWeekPlan($arguments),
            'mark_list_purchased' => $this->markListPurchased($arguments),
            default => throw new McpError(
                self::INVALID_PARAMS,
                'Unknown tool: '.(is_string($params['name'] ?? null) ? $params['name'] : '(none)'),
            ),
        };

        return $this->result($id, [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
        ]);
    }

    /**
     * The latest locked week's shopping list: category-grouped items with
     * cleaned search keywords, the remembered Walmart product URL when one
     * exists, and the shared checkbox state.
     *
     * @return array<string, mixed>
     */
    private function getCurrentShoppingList(): array
    {
        $plan = MealPlan::query()
            ->where('status', MealPlanStatus::Locked)
            ->orderByDesc('week_start_date')
            ->first();

        if ($plan === null) {
            throw new McpError(self::NOT_FOUND, 'No locked week.');
        }

        $items = $this->buildShoppingList->handle($plan);
        $checked = $plan->checked_items ?? [];

        $names = collect($items)->collapse()->pluck('name');

        $productUrls = WalmartMatch::query()
            ->whereHas('ingredient', fn ($query) => $query->whereIn('name', $names))
            ->with('ingredient')
            ->get()
            ->mapWithKeys(fn (WalmartMatch $match) => [$match->ingredient->name => $match->product_url]);

        $list = [];

        foreach ($items as $category => $lines) {
            foreach ($lines as $item) {
                $list[$category][] = [
                    ...$item,
                    'keywords' => $this->cleanKeywords->handle($item['name']),
                    'product_url' => $productUrls[$item['name']] ?? null,
                    'checked' => in_array($item['name'].'|'.($item['unit'] ?? ''), $checked, true),
                ];
            }
        }

        return [
            'week_start_date' => $plan->week_start_date->toDateString(),
            'purchased_at' => $plan->purchased_at?->toIso8601String(),
            'items' => $list,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveProductMatchTool(array $arguments): array
    {
        $name = mb_strtolower($this->stringArgument($arguments, 'ingredient'));
        $productUrl = $this->stringArgument($arguments, 'product_url');
        $productName = $this->stringArgument($arguments, 'product_name');

        if (! filter_var($productUrl, FILTER_VALIDATE_URL)) {
            throw new McpError(self::INVALID_PARAMS, 'Invalid params: product_url must be a full URL.');
        }

        $ingredient = Ingredient::query()->where('name', $name)->first();

        if ($ingredient === null) {
            throw new McpError(self::NOT_FOUND, "Ingredient not found: {$name}");
        }

        $match = $this->saveProductMatch->handle($ingredient, $productUrl, $productName);

        return [
            'ingredient' => $ingredient->name,
            'product_url' => $match->product_url,
            'product_name' => $match->product_name,
            'last_confirmed_at' => $match->last_confirmed_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getWeekPlan(array $arguments): array
    {
        $plan = $this->planForWeek($arguments);

        $plan->load('plannedMeals.recipe');

        $days = collect(range(0, 4))->map(function (int $offset) use ($plan) {
            $date = $plan->week_start_date->copy()->addDays($offset);

            $slots = collect(MealSlot::cases())->mapWithKeys(function (MealSlot $slot) use ($plan, $date) {
                $meal = $plan->plannedMeals->first(
                    fn (PlannedMeal $meal) => $meal->date->isSameDay($date) && $meal->slot === $slot,
                );

                return [$slot->value => $meal?->recipe->title];
            });

            return ['date' => $date->toDateString(), 'slots' => $slots];
        });

        return [
            'week_start_date' => $plan->week_start_date->toDateString(),
            'status' => $plan->status->value,
            'days' => $days->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function markListPurchased(array $arguments): array
    {
        $plan = $this->planForWeek($arguments);

        $plan->update(['purchased_at' => now()]);

        return [
            'week_start_date' => $plan->week_start_date->toDateString(),
            'purchased_at' => $plan->purchased_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function planForWeek(array $arguments): MealPlan
    {
        $weekStart = $this->stringArgument($arguments, 'week_start');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
            throw new McpError(self::INVALID_PARAMS, 'Invalid params: week_start must be YYYY-MM-DD.');
        }

        $plan = MealPlan::query()->whereDate('week_start_date', $weekStart)->first();

        if ($plan === null) {
            throw new McpError(self::NOT_FOUND, "No meal plan for week starting {$weekStart}.");
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function stringArgument(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new McpError(self::INVALID_PARAMS, "Invalid params: {$key} is required.");
        }

        return trim($value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        $weekStartSchema = [
            'type' => 'object',
            'properties' => [
                'week_start' => [
                    'type' => 'string',
                    'description' => 'Week start date (a Monday), YYYY-MM-DD.',
                ],
            ],
            'required' => ['week_start'],
        ];

        return [
            [
                'name' => 'get_current_shopping_list',
                'description' => "The latest locked week's shopping list: items grouped by store category, each with cleaned Walmart search keywords, the remembered product URL when one exists, and checked (already in cart) state.",
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'save_product_match',
                'description' => 'Remember the Walmart product for an ingredient (upserts the mapping and refreshes its confirmation stamp).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'ingredient' => ['type' => 'string', 'description' => 'Ingredient name as it appears on the shopping list.'],
                        'product_url' => ['type' => 'string', 'description' => 'Walmart product page URL.'],
                        'product_name' => ['type' => 'string', 'description' => 'Product title as listed on Walmart.'],
                    ],
                    'required' => ['ingredient', 'product_url', 'product_name'],
                ],
            ],
            [
                'name' => 'get_week_plan',
                'description' => "A week's meal schedule: Mon-Fri days, each day's slots mapping to the planned recipe title (or null).",
                'inputSchema' => $weekStartSchema,
            ],
            [
                'name' => 'mark_list_purchased',
                'description' => "Record that the week's shopping list has been purchased (stamps purchased_at on the meal plan).",
                'inputSchema' => $weekStartSchema,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function result(mixed $id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
