# Light Plan: Add an on-demand recipe requester

**Date:** 2026-08-18
**Branch:** main (work needs a `feat/recipe-requester` branch cut first)
**Scope:** single-session

## Objective

Let the household ask for a specific dish ("hibachi for 4 on a flat-top griddle") instead of
waiting for scheduled discovery to happen upon one. A typed request fans out to a YouTube keyword
search and to Claude, and the resulting candidates land in the existing `/approve` queue tagged
with the request that produced them. Requested recipes are exempt from the weeknight hard
constraints (30-minute total, 10 ingredients) that shape scheduled discovery.

## Acceptance Criteria

- [ ] Submitting "hibachi blackstone" on `/request` creates a `recipe_requests` row and, when the run finishes, at least one pending recipe whose `recipe_request_id` is that row.
- [ ] A recipe produced by a request may exceed 30 total minutes and 10 ingredients; a recipe produced by the scheduled `recipes:discover` run still may not.
- [ ] `/approve` shows requested candidates alongside scheduled ones, each labeled with its originating request text.
- [ ] `php artisan recipes:request "hibachi blackstone"` performs the same run from the CLI and exits non-zero when every lane fails.
- [ ] A request that yields zero candidates reports that on the request page rather than silently showing an empty queue.

## Scope

### Files to create

- `app/Models/RecipeRequest.php` plus migration: request text, status, timestamps
- migration adding nullable `recipe_request_id` to `recipes` and `discovered_videos`
- `app/Actions/Discovery/SearchYouTube.php`: runs `yt-dlp ytsearch<N>:<query>` into `discovered_videos` rows
- `app/Actions/Discovery/RunRequest.php`: fans out both lanes for one request
- `app/Console/Commands/RequestRecipes.php`: `recipes:request {query}`
- `app/Livewire/RecipeRequester.php` plus view: the request box and its result state

### Files to modify

- `app/Discovery/AnthropicDriver.php`: accept an optional request, drop the two caps and inject the request text when one is present
- `app/Actions/Discovery/ClassifyDiscoveredVideos.php`: score request-sourced videos against the request rather than the weeknight rubric
- `app/Livewire/RecipeApproval.php` plus view: surface the originating request
- `routes/web.php`: `GET /request`

### Out of scope

- The cookbook lane. Its source is still undecided (own PDFs vs. recipe sites vs. physical books), so it gets its own plan once chosen.
- Household size as a setting. `servings` stays whatever the lane returns.
- Any change to the scheduled daily run's cadence, drivers, or constraints.

## Key Decisions

- **`yt-dlp` for search, not the YouTube Data API**, because it is already installed at `/opt/homebrew/bin/yt-dlp`, needs no API key, and has no quota.
- **Reuse `discovered_videos`, `ClassifyDiscoveredVideos` and `ExtractRecipeFromVideo` in their current shape**, since the classifier selects on `whereNull('classification')` and is not scoped to subscribed channels, so search hits flow through the existing chain.
- **No new review UI.** Candidates land as `RecipeStatus::Pending`, which `/approve` already queues.
- **The caps are prompt text, not validation.** `CandidateValidator` never enforced them, so the exemption is a prompt change and needs no validator work.

## Risk Areas

- **The caps live in TWO prompts, not one.** `AnthropicDriver` generates against them and `ClassifyDiscoveredVideos` scores against them. Exempting only the generator leaves the YouTube lane broken: `YouTubeDriver` takes survivors `orderByDesc('score')`, so a correctly found hibachi video still loses to a generic 20-minute dinner. Both must change.
- `discovered_videos.channel_id` is non-nullable in the current schema, and search hits carry a channel that is not a subscribed one. Confirm nothing joins it back to `youtube_channels` before inserting.
- The daily idempotency guard lives in `DiscoverRecipes`, not in the actions. A request run must not trip or consume it, or one request would suppress that day's scheduled discovery.
- `claude` on this machine is a shell function wrapping `_claude_run`, not a bare binary. `ClaudeCli` resolves a real path, so set `MEALBOARD_CLAUDE_BIN` if the request lane fails to spawn.

## Manual Test Hints

- Submit "hibachi blackstone" on `/request`, then wait a couple of minutes: `/approve` holds at least one hibachi-ish candidate labeled with that request.
- Open that candidate's detail: total time may exceed 30 minutes and the ingredient list may exceed 10 entries without the recipe being rejected.
- Run `php artisan recipes:discover --force` straight afterward: the candidates it produces are still all within 30 minutes and 10 ingredients.
- Submit a request that cannot match anything ("xyzzy stew"): the page reports zero candidates found rather than appearing to hang or succeed.
- Cut network access and submit a request: the run fails loudly with a non-zero CLI exit, and no half-written pending recipe is left behind.
