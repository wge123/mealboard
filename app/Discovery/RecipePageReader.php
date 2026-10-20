<?php

namespace App\Discovery;

use App\Actions\Recipes\RestructureIngredientLine;
use App\Enums\RecipePageRefusal;
use App\Support\Text;

/**
 * Reads one recipe page: page HTML and its address in, either a candidate in
 * the recipe shape (see RecipeOutputFormat) or a named refusal out. It reads
 * the page's own schema.org Recipe data and restructures it by code (ADR 0004).
 * No network and no model.
 */
class RecipePageReader
{
    public function __construct(
        private RestructureIngredientLine $restructure,
        private KitchenToolsInSteps $toolsInSteps,
    ) {}

    public function read(string $html, string $url): RecipePageReading
    {
        $recipe = $this->findRecipe($html);

        if ($recipe === null) {
            return $this->refuse(RecipePageRefusal::NoRecipeData);
        }

        $lines = $this->textList($recipe['recipeIngredient'] ?? null);

        if ($lines === []) {
            return $this->refuse(RecipePageRefusal::NoIngredients);
        }

        $steps = $this->steps($recipe['recipeInstructions'] ?? null);

        if ($steps === []) {
            return $this->refuse(RecipePageRefusal::NoSteps);
        }

        [$prep, $cook] = $this->times($recipe);

        $ingredients = array_map($this->restructure->handle(...), $lines);

        if (! $this->isLineForLine($lines, $ingredients)) {
            return $this->refuse(RecipePageRefusal::NotLineForLine);
        }

        return RecipePageReading::of([
            'title' => $this->text($recipe['name'] ?? ''),
            'description' => $this->text($recipe['description'] ?? ''),
            'meal_type' => $this->mealType($this->textList($recipe['recipeCategory'] ?? null)),
            'prep_minutes' => $prep ?? 0,
            'cook_minutes' => $cook ?? 0,
            'servings' => $this->servings($recipe['recipeYield'] ?? null),
            'cuisine' => ($cuisine = $this->textList($recipe['recipeCuisine'] ?? null)[0] ?? null) === null ? null : mb_strtolower($cuisine),
            'tags' => [],
            'source_url' => $url,
            'tools' => $this->toolsInSteps->find($steps),
            'ingredients' => $ingredients,
            'steps' => $steps,
        ]);
    }

    /**
     * The cheap facts of a page's recipe data, before anything is restructured:
     * the raw ingredient lines and the stated minutes (0 when stated nowhere).
     * Null when the page has no recipe data. Lets a caller refuse a page on
     * time or ingredients without paying for restructuring.
     *
     * @return ?array{ingredients: list<string>, prep_minutes: int, cook_minutes: int}
     */
    public function preview(string $html): ?array
    {
        $recipe = $this->findRecipe($html);

        if ($recipe === null) {
            return null;
        }

        [$prep, $cook] = $this->times($recipe);

        return [
            'ingredients' => $this->textList($recipe['recipeIngredient'] ?? null),
            'prep_minutes' => $prep ?? 0,
            'cook_minutes' => $cook ?? 0,
        ];
    }

    /**
     * Prep and cook minutes; a page that gives only a total time has it all as cook time.
     *
     * @param  array<string, mixed>  $recipe
     * @return array{0: ?int, 1: ?int}
     */
    private function times(array $recipe): array
    {
        $total = $this->minutes($recipe['totalTime'] ?? null);
        $prep = $this->minutes($recipe['prepTime'] ?? null);
        $cook = $this->minutes($recipe['cookTime'] ?? null);

        if ($prep === null && $cook === null && $total !== null) {
            $cook = $total;
        }

        return [$prep, $cook];
    }

    /**
     * One restructured ingredient per source line, and no word in a name or
     * prep note that the source line does not carry (nothing added).
     *
     * @param  list<string>  $lines
     * @param  array<int, array{name: string, prep_note: ?string}>  $ingredients
     */
    private function isLineForLine(array $lines, array $ingredients): bool
    {
        if (count($lines) !== count($ingredients)) {
            return false;
        }

        foreach ($lines as $i => $line) {
            $source = $this->words($line);
            $made = $this->words($ingredients[$i]['name'].' '.($ingredients[$i]['prep_note'] ?? ''));

            if ($ingredients[$i]['name'] === '' || array_diff($made, $source) !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        preg_match_all('/\p{L}+|\p{N}+/u', mb_strtolower($text), $m);

        return $m[0];
    }

    private function refuse(RecipePageRefusal $refusal): RecipePageReading
    {
        return RecipePageReading::refused($refusal);
    }

    /**
     * The first node whose @type is or contains Recipe, in any JSON-LD block
     * (top-level object, list of objects, or @graph). Block attributes are
     * matched loosely: the type attribute may be unquoted.
     *
     * @return ?array<string, mixed>
     */
    private function findRecipe(string $html): ?array
    {
        preg_match_all('/<script\b[^>]*\btype\s*=\s*["\']?application\/ld\+json["\']?[^>]*>(.*?)<\/script>/is', $html, $blocks);

        foreach ($blocks[1] as $json) {
            $decoded = json_decode(trim($json), true);

            if (is_array($decoded) && ($recipe = $this->recipeIn($decoded)) !== null) {
                return $recipe;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $node
     * @return ?array<string, mixed>
     */
    private function recipeIn(array $node): ?array
    {
        if (isset($node['@type']) && in_array('Recipe', (array) $node['@type'], true)) {
            return $node;
        }

        foreach ($node['@graph'] ?? [] as $child) {
            if (is_array($child) && ($recipe = $this->recipeIn($child)) !== null) {
                return $recipe;
            }
        }

        if (array_is_list($node)) {
            foreach ($node as $child) {
                if (is_array($child) && ($recipe = $this->recipeIn($child)) !== null) {
                    return $recipe;
                }
            }
        }

        return null;
    }

    /**
     * Instruction steps in order: strings, HowToStep text, HowToSection items flattened.
     *
     * @return list<string>
     */
    private function steps(mixed $raw): array
    {
        if (is_string($raw)) {
            return $this->textList(preg_split('/\R/', $raw) ?: []);
        }

        $steps = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            if (is_string($item)) {
                $steps[] = $item;
            } elseif (is_array($item) && isset($item['itemListElement'])) {
                array_push($steps, ...$this->steps($item['itemListElement']));
            } elseif (is_array($item)) {
                $steps[] = $item['text'] ?? $item['name'] ?? '';
            }
        }

        return $this->textList($steps);
    }

    /**
     * @return list<string>
     */
    private function textList(mixed $raw): array
    {
        $items = is_string($raw) ? [$raw] : (is_array($raw) ? $raw : []);

        return array_values(array_filter(
            array_map(fn ($item) => is_string($item) ? $this->text($item) : '', $items),
            fn (string $text) => $text !== '',
        ));
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? Text::collapseWhitespace(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }

    /** ISO 8601 duration ("PT1H20M") or plain "50 minutes", in whole minutes. */
    private function minutes(mixed $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }

        if (preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:\d+S)?$/i', trim($value), $m) && $value !== 'P') {
            return (int) ($m[1] ?? 0) * 1440 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
        }

        return preg_match('/^(\d+)\s*(?:min|minutes?)$/i', trim($value), $m) ? (int) $m[1] : null;
    }

    private function servings(mixed $yield): int
    {
        foreach ((array) $yield as $item) {
            if (preg_match('/\d+/', (string) $item, $m)) {
                return (int) $m[0];
            }
        }

        return 0;
    }

    /**
     * @param  list<string>  $categories
     */
    private function mealType(array $categories): string
    {
        $text = mb_strtolower(implode(' ', $categories));

        return match (true) {
            str_contains($text, 'breakfast') => 'breakfast',
            str_contains($text, 'lunch') => 'lunch',
            str_contains($text, 'main'), str_contains($text, 'dinner'), str_contains($text, 'soup') => 'dinner',
            default => 'any',
        };
    }
}
