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

];
