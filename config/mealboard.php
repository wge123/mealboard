<?php

use App\Discovery\AnthropicDriver;
use App\Discovery\TheMealDbDriver;
use App\Discovery\YouTubeDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Recipe discovery
    |--------------------------------------------------------------------------
    |
    | DECISIONS.md #1 — discovery runs DAILY, 3 candidates per run.
    | One run executes ALL listed drivers; each driver is asked for
    | candidates_per_run candidates and failures are recorded per driver.
    |
    */

    'cadence' => 'daily',

    'candidates_per_run' => 3,

    'drivers' => [
        AnthropicDriver::class, // primary (DECISIONS.md #2 — claude CLI)
        YouTubeDriver::class, // channel RSS -> classify -> transcript pipeline
        TheMealDbDriver::class, // free-API fallback
    ],

    /*
    |--------------------------------------------------------------------------
    | Claude CLI binary
    |--------------------------------------------------------------------------
    |
    | Explicit path to the `claude` binary for the AnthropicDriver. When null,
    | the driver locates it on PATH at runtime and fails fast if missing.
    |
    */

    'claude_bin' => env('MEALBOARD_CLAUDE_BIN'),

    /*
    |--------------------------------------------------------------------------
    | YouTube transcript python
    |--------------------------------------------------------------------------
    |
    | DECISIONS.md #4 — transcripts come from a thin python3 subprocess using
    | youtube_transcript_api out of yt2md's venv. Transcript fetching ONLY;
    | Mealboard's own claude driver does the structuring.
    |
    */

    'yt_python' => env('MEALBOARD_YT_PYTHON', '/Users/willem/Developer/Personal/yt2md/venv/bin/python3'),

    /*
    |--------------------------------------------------------------------------
    | Second-brain vault (GitHub)
    |--------------------------------------------------------------------------
    |
    | DECISIONS.md #7 — Mealboard both pulls preference notes FROM the vault
    | repo (brain:sync, step 25) and pushes meal-plan markdown TO it
    | (PublishMealsMarkdown, step 26), via the GitHub contents API.
    |
    | brain_files lists the vault-relative markdown paths brain:sync pulls
    | (food/recipe preference notes). Deviation from the original step text:
    | DECISIONS.md #5 put the YouTube channel list in the database with its
    | own settings UI, so channel sync is deliberately NOT part of brain_files
    | or brain:sync. A path missing in the repo warns per file, never fails.
    |
    */

    'github_pat' => env('GITHUB_PAT'),

    /*
    |--------------------------------------------------------------------------
    | Pull-endpoint API token
    |--------------------------------------------------------------------------
    |
    | DECISIONS.md #7 (pull half) — static token guarding /api/plan/current
    | and /api/plan/ical. Accepted as `Authorization: Bearer <token>` or
    | `?token=<token>` (calendar subscribers can't set headers).
    |
    */

    'api_token' => env('MEALBOARD_API_TOKEN'),

    'brain_repo' => env('MEALBOARD_BRAIN_REPO', 'wge123/second-brain-vault'),

    'brain_files' => [
        'wiki/concepts/food-preferences.md',
    ],

];
