<?php

namespace App\Discovery;

use App\Actions\Planning\ComputeTasteProfile;
use App\Enums\RecipeStatus;
use App\Models\BrainNote;
use App\Models\KitchenToolKind;
use App\Models\Recipe;
use App\Models\RecipeRequest;
use App\Support\HouseholdPreferences;
use App\Support\KitchenToolInventory;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Primary driver — shells out to the local `claude` CLI (DECISIONS.md #2:
 * `claude -p <prompt> --model sonnet`, exactly like yt2md; no API key).
 */
class AnthropicDriver implements RecipeDiscoveryDriver
{
    /** A rejection theme needs at least this many rejected recipes. */
    private const int REJECTION_THEME_MIN = 2;

    /** Cap on rejection-theme lines so the prompt section stays compact. */
    private const int REJECTION_THEME_CAP = 8;

    /** "Long recipe" rejection-theme threshold (matches DECISIONS.md #6). */
    private const int LONG_RECIPE_MINUTES = 30;

    /** Cap on synced brain-note text so a long note can't flood the prompt. */
    private const int BRAIN_NOTES_CHAR_CAP = 1500;

    public function __construct(
        private ClaudeCli $claude,
        private CandidateValidator $validator,
        private ComputeTasteProfile $profile,
        private KitchenToolInventory $tools,
        private RetryOnce $retry,
        private HouseholdPreferences $preferences,
    ) {}

    public function discover(int $n): array
    {
        $output = $this->claude->run($this->prompt($n));

        return $this->parseCandidates($output, fn (mixed $item) => $this->checkShaped($item));
    }

    /**
     * Same driver, aimed at one household request instead of at open-ended
     * discovery. The weekday limits are dropped: the household named the dish,
     * and a hibachi spread for four does not fit in a weekday's ingredients.
     *
     * @return array<int, array<string, mixed>>
     */
    public function discoverFor(RecipeRequest $request, int $n): array
    {
        $checked = $this->retry->run(
            $this->prompt($n, $request),
            fn (string $output) => $this->outputErrors($output),
        );

        // After the retry, a candidate that still fails is dropped.
        return $this->parseCandidates($checked->output, fn (mixed $item) => $this->checkShaped($item));
    }

    private function prompt(int $n, ?RecipeRequest $request = null): string
    {
        $tasteProfile = $this->tasteProfile();
        // On a request, the "over 30 minutes" theme is dropped: it is an
        // artifact of the weeknight rubric, and leaving it in would tell the
        // model to avoid long recipes two lines after telling it there is no
        // time limit. Cuisine, tag and ingredient themes still apply, because
        // those are real dislikes rather than a scheduling constraint.
        $rejections = $this->summarizeRejections(includeDurationTheme: $request === null);

        $tasteProfileSection = $tasteProfile !== '' ? $tasteProfile : '(none yet)';
        $rejectionSection = $rejections !== '' ? $rejections : '(none yet)';

        $size = $this->preferences->householdSize();
        $avoided = $this->preferences->avoidedIngredients();
        $avoidedLine = $avoided->isNotEmpty()
            ? "- Never use these ingredients (a household rule, no exceptions): {$avoided->implode(', ')}.\n"
            : '';

        if ($request !== null) {
            $brief = <<<BRIEF
            The household has asked for this specifically:
            "{$request->query}"

            Every candidate must be a genuine answer to that request. Prefer the authentic version of what was asked for over a lighter or faster reinterpretation of it.

            Constraints for EVERY candidate:
            - There is NO time limit and NO ingredient limit. The household's usual weekday limits do not apply to a requested dish; give the recipe the time and the ingredients it actually takes.
            - Unless the request says otherwise, the recipe serves about {$size}.
            {$avoidedLine}- Whole-food-leaning where the dish allows it, without compromising what makes the dish itself.
            BRIEF;
        } else {
            $limits = $this->preferences->weekdayLimits();
            $staples = $this->preferences->staples();
            $staplesLine = $staples->isNotEmpty()
                ? "Pantry staples the household keeps in stock, which do not count toward the ingredient limit: {$staples->implode(', ')}."
                : 'The household has marked no pantry staples yet, so every ingredient counts toward the limit.';

            $brief = <<<BRIEF
            Suggest candidates the household has plausibly never tried.

            Hard constraints for EVERY candidate (healthy + easy):
            - Total time (prep_minutes + cook_minutes) must be {$limits['minutes']} minutes or less.
            - {$limits['ingredients']} ingredients or fewer. {$staplesLine}
            - The recipe serves about {$size}.
            {$avoidedLine}- Whole-food-leaning: minimally processed ingredients over packaged or ultra-processed ones.
            BRIEF;
        }

        // The request lanes ignore which tools the household owns: no owned
        // list and no "prefer" line, only the words the model may use.
        $toolsSection = $request === null
            ? $this->toolsSection()
            : RecipeOutputFormat::kindsSection($this->tools->kinds())."\n\n";
        $outputSpec = RecipeOutputFormat::schema();

        return <<<PROMPT
        You are the recipe discovery engine for a household meal planner. Suggest exactly {$n} recipe candidates.

        {$brief}

        Taste profile:
        {$tasteProfileSection}

        Themes from previously rejected recipes — avoid these:
        {$rejectionSection}

        {$toolsSection}Respond with STRICT JSON only: a top-level array of exactly {$n} objects. No prose, no markdown fences, no trailing commentary. Each object has exactly these keys:
        {$outputSpec}

        Source rule: every candidate MUST cite the real, published recipe page or cooking video it is based on as source_url (an http(s) URL on a recipe site or YouTube). Only cite URLs you are confident actually exist — NEVER invent or guess a URL. If you cannot cite a real source for an idea, replace it with a candidate you can cite. Cited URLs are checked; a dead link gets the candidate discarded.
        PROMPT;
    }

    /**
     * The kinds the model may name tools by, and what the household owns, so
     * the daily lane can ask for recipes that need no missing tools.
     */
    private function toolsSection(): string
    {
        $kindsSection = RecipeOutputFormat::kindsSection($this->tools->kinds());
        $owned = KitchenToolKind::query()->where('owned', true)->orderBy('name')->pluck('name')->implode(', ');
        $owned = $owned !== '' ? $owned : '(none)';

        return <<<SECTION
        {$kindsSection}

        Tools the household owns:
        {$owned}

        Prefer recipes that need no tools the household is missing.


        SECTION;
    }

    /**
     * Every check error across the candidates in a raw output, each naming
     * its candidate, for the retry prompt. Not-a-list output is one error;
     * parseCandidates reports the final failure of that kind.
     *
     * @return list<string>
     */
    private function outputErrors(string $output): array
    {
        $decoded = json_decode($this->claude->extractJson($output), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return ['output: must be a JSON array of candidates'];
        }

        $errors = [];

        foreach ($decoded as $i => $item) {
            foreach ($this->validator->check($item)->errors as $error) {
                $errors[] = "candidates[{$i}] \"".CandidateValidator::titleOf($item).'" '.$error;
            }
        }

        return $errors;
    }

    /**
     * Shaped check; a failing candidate is logged and dropped (the scheduled lane never retries; the request lane retries before this).
     *
     * @return ?array<string, mixed>
     */
    private function checkShaped(mixed $item): ?array
    {
        return $this->validator->check($item)->candidateOrDrop(CandidateValidator::titleOf($item));
    }

    /**
     * Serialized ComputeTasteProfile output. Empty string on a fresh
     * install, so the prompt keeps its "(none yet)" placeholder.
     */
    protected function tasteProfile(): string
    {
        $profile = $this->profile->handle();

        $describe = fn (array $rated) => collect($rated)
            ->map(fn (float $average, string $name) => "{$name} (avg {$average})")
            ->implode(', ');

        $lines = [];

        if ($profile['cuisines'] !== []) {
            $lines[] = 'Favored cuisines: '.$describe($profile['cuisines']);
        }

        if ($profile['tags'] !== []) {
            $lines[] = 'Favored tags: '.$describe($profile['tags']);
        }

        if ($profile['favoredIngredients'] !== []) {
            $lines[] = 'Favored ingredients: '.implode(', ', $profile['favoredIngredients']);
        }

        if ($profile['avoidedIngredients'] !== []) {
            $lines[] = 'Avoid these ingredients: '.implode(', ', $profile['avoidedIngredients']);
        }

        foreach ($profile['slotPatterns'] as $slot => $rate) {
            $lines[] = sprintf('Habit: %s is skipped %d%% of logged opportunities — favor very fast %s recipes.', $slot, (int) round($rate * 100), $slot);
        }

        // Synced second-brain preference notes (brain:sync, step 25) surface
        // as plain preference lines alongside the computed profile.
        $notes = trim(BrainNote::query()->orderBy('path')->pluck('content')->implode("\n"));

        if ($notes !== '') {
            $lines[] = rtrim(mb_substr($notes, 0, self::BRAIN_NOTES_CHAR_CAP));
        }

        return implode("\n", $lines);
    }

    /**
     * DECISIONS.md #3 — rejected titles SUMMARIZED into themes, never listed
     * verbatim. Plain PHP aggregation (no extra AI call): rejected recipes
     * grouped by cuisine, tag, key ingredient, and over-30-minutes, each
     * theme needing at least REJECTION_THEME_MIN members, capped at
     * REJECTION_THEME_CAP lines.
     */
    protected function summarizeRejections(bool $includeDurationTheme = true): string
    {
        $rejected = Recipe::query()
            ->where('status', RecipeStatus::Rejected)
            ->with('ingredients')
            ->get();

        if ($rejected->isEmpty()) {
            return '';
        }

        $normalize = fn (?string $value) => mb_strtolower(trim((string) $value));

        // Theme label => rejected-recipe count.
        $themes = collect()
            ->merge($rejected
                ->countBy(fn (Recipe $recipe) => $normalize($recipe->cuisine))
                ->forget('')
                ->mapWithKeys(fn (int $count, string $cuisine) => ["{$cuisine} dishes" => $count]))
            ->merge($rejected
                ->flatMap(fn (Recipe $recipe) => collect($recipe->tags ?? [])->map($normalize)->unique())
                ->countBy()
                ->forget('')
                ->mapWithKeys(fn (int $count, string $tag) => ["recipes tagged \"{$tag}\"" => $count]))
            ->merge($rejected
                ->flatMap(fn (Recipe $recipe) => $recipe->ingredients->pluck('name')->unique())
                ->countBy()
                ->mapWithKeys(fn (int $count, string $ingredient) => ["recipes featuring {$ingredient}" => $count]))
            ->when($includeDurationTheme, fn ($themes) => $themes->put(
                'recipes over '.self::LONG_RECIPE_MINUTES.' minutes total',
                $rejected->filter(fn (Recipe $recipe) => $recipe->prep_minutes + $recipe->cook_minutes > self::LONG_RECIPE_MINUTES)->count(),
            ));

        // Theme = repetition: drop singleton groups, biggest first, capped.
        return $themes
            ->filter(fn (int $count) => $count >= self::REJECTION_THEME_MIN)
            ->sortDesc()
            ->take(self::REJECTION_THEME_CAP)
            ->map(fn (int $count, string $label) => "- {$label} ({$count} rejected)")
            ->implode("\n");
    }

    /**
     * @param  Closure(mixed): ?array<string, mixed>  $validate
     * @return array<int, array<string, mixed>>
     */
    private function parseCandidates(string $output, Closure $validate): array
    {
        $decoded = json_decode($this->claude->extractJson($output), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException(
                'claude output was not a JSON array of candidates: '.mb_substr(trim($output), 0, 300),
            );
        }

        $candidates = [];

        foreach ($decoded as $item) {
            $candidate = $validate($item);

            if ($candidate === null) {
                continue;
            }

            // The model is told to cite real sources, but it can still
            // hallucinate one — a dead link disqualifies the candidate.
            if (! $this->sourceResolves($candidate['source_url'])) {
                Log::warning("discovery: discarded candidate \"{$candidate['title']}\" — cited source_url does not resolve: {$candidate['source_url']}");

                continue;
            }

            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /**
     * A hallucinated citation shows up as a hard not-found or a dead host.
     * Bot-blocking responses (403/429) still prove the URL resolves, so only
     * 404/410/connection failure disqualify.
     */
    private function sourceResolves(string $url): bool
    {
        try {
            $status = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Mealboard/1.0'])
                ->head($url)
                ->status();
        } catch (ConnectionException) {
            return false;
        }

        return ! in_array($status, [404, 410], true);
    }
}
