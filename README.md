# Mealboard

Self-hosted Laravel household meal planner: recipe library with AI/YouTube discovery and approve/reject cards, Mon–Fri auto-filled meal plans weighted by a taste profile learned from eat/rating logs, weekly shopping lists, second-brain vault sync, and a three-tier Walmart pickup handoff.

Stack: Laravel + Livewire 3 + Alpine, Tailwind CSS v4 + daisyUI 5 via Vite (custom light/dark themes, phone-first with a bottom tab bar), MySQL, database queue. AI features shell out to the local `claude` CLI (`claude -p --model sonnet`) — no API key. All architectural decisions are recorded in [DECISIONS.md](DECISIONS.md).

## Setup

```bash
composer install
npm install && npm run build      # Vite bundle (Tailwind 4 + daisyUI); rerun npm run build after CSS/blade changes
cp .env.example .env && php artisan key:generate
mysql -u root -e "CREATE DATABASE IF NOT EXISTS mealboard"
php artisan migrate --seed        # seeds willem@ + partner@example.com (password: password) and ~10 recipes
herd link mealboard               # → http://mealboard.test
```

Local dev auto-logs you in as Willem (`AutoLoginLocal` middleware, `local` env only).

### .env keys

| Key | Required for | Notes |
| --- | --- | --- |
| `DB_*` | everything | MySQL database `mealboard` |
| `QUEUE_CONNECTION=database` | discovery runs | set by default |
| `MEALBOARD_API_TOKEN` | pull endpoints | generated; Bearer or `?token=` on `/api/plan/*` |
| `GITHUB_PAT` | `brain:sync`, meals publishing | fine-grained, Contents R/W on `wge123/second-brain-vault` |
| `MEALBOARD_BRAIN_REPO` | optional override | defaults to `wge123/second-brain-vault` |
| `MEALBOARD_CLAUDE_BIN` | optional override | defaults to `claude` on PATH |
| `MEALBOARD_YT_PYTHON` | optional override | defaults to the yt2md venv python |
| `MEALBOARD_CHROME_PROFILE` | `walmart:push-list` (Tier 3) | persistent logged-in Chrome user-data dir |
| `MEALBOARD_WALMART_LIST_URL` | `walmart:push-list` (Tier 3) | your Walmart list page URL |

## Running

Two long-lived processes:

```bash
# scheduler (cron): * * * * * cd /Users/willem/Developer/Personal/mealboard && php artisan schedule:run >> /dev/null 2>&1
# queue worker:
php artisan queue:work --tries=1
```

Scheduled commands (`php artisan schedule:list`):

- `brain:sync` — daily 05:15, pulls preference notes from the vault repo into discovery prompts
- `recipes:discover` — daily 05:30, all drivers (claude CLI → YouTube → TheMealDB), 3 candidates/run, fuzzy-deduped, idempotent per day (`--force` to re-run)
- `meals:publish-today` — daily 00:10, pushes `meals/today.md` to the vault repo when a locked week covers today

## Daily use (phone-first)

- `/recipes` — library (search/filters), `/recipes/create` — manual add with paste parser + AI parse button
- `/approve` — discovery approve/reject cards (keyboard: **A** / **R**)
- `/plan` — week builder: create next week, auto-fill, swap, then **Lock week** (locking publishes to the vault and opens the shopping list)
- `/plan/{id}/shopping-list` — shared checklist; rows tap through to Walmart product or search; paste-back "found it" saves the match
- `/log` — yesterday's catch-up logging; past slots on the locked week log inline
- `/insights` — ate/skip rates, top-rated, discovery rejection rate
- `/settings/channels` — YouTube channels feeding discovery

## Pull endpoints

`GET /api/plan/current` (JSON) and `GET /api/plan/ical` (iCal, one VEVENT per meal at 08/12/18h) — both take `Authorization: Bearer $MEALBOARD_API_TOKEN` or `?token=`.

## Walmart handoff tiers

1. **Tier 1 — tap-through list** (always on): shopping-list rows link to remembered products or keyword search; every confirmed "found it" teaches `walmart_matches`.
2. **Tier 2 — supervised agent cart**: runbook in [docs/tier2-cart.md](docs/tier2-cart.md). Claude drives a logged-in browser via the MCP server below; human approves ambiguity and places the order. Nothing is ever purchased automatically.
3. **Tier 3 — `walmart:push-list`**: headed Chrome pushes cleaned keywords onto your Walmart list (never the cart); selectors are provisional until first supervised run — see [docs/tier3-list.md](docs/tier3-list.md). Fails loudly on any selector miss.

## MCP server

Mealboard ships a local stdio MCP server (JSON-RPC 2.0 over STDIN/STDOUT, protocol `2024-11-05`, no SDK dependency) so Claude Code can drive the Walmart handoff directly.

Register it with Claude Code:

```bash
claude mcp add mealboard -- php /Users/willem/Developer/Personal/mealboard/artisan mcp:serve
```

### Tools

| Tool | Arguments | Does |
| --- | --- | --- |
| `get_current_shopping_list` | — | The latest **locked** week's shopping list: items grouped by store category, each with cleaned Walmart search keywords, the remembered `product_url` when a match exists, and `checked` (already in cart) state. |
| `save_product_match` | `ingredient`, `product_url`, `product_name` | Remembers the Walmart product for an ingredient (one match per ingredient, upserted; `last_confirmed_at` refreshed on every save). |
| `get_week_plan` | `week_start` (`YYYY-MM-DD`, a Monday) | That week's schedule: Mon–Fri days, each day's breakfast/lunch/dinner slot mapping to the planned recipe title or `null`. |
| `mark_list_purchased` | `week_start` (`YYYY-MM-DD`) | Stamps `purchased_at` on that week's meal plan once the order is placed. |

Failures come back as JSON-RPC error responses — `-32602` for unknown tools/bad params, `-32002` for not-found (no locked week, unknown ingredient, no plan for that week) — the loop never crashes.

## Tests

```bash
./vendor/bin/pest    # runs on sqlite :memory:
```

Gotchas pinned by the suite: sqlite+PDO makes bound-numeric aggregate comparisons silently false (inline/PHP-side instead); `Process::fake` patterns must wildcard around the binary (`'*claude*'`).
