# Tabler Icons for Mealboard: which route, and which icon replaces which glyph

Researched 2026-10-14. Mealboard: Laravel `^13.8`, PHP `^8.3`, Livewire 3, Tailwind v4 + daisyUI 5 (`composer.json`).
Raw captures the claims rest on are in `docs/research/tabler-icons/` (cited below). Nothing was installed and no app code changed; the recommended package has not been run inside Mealboard.

## Recommendation

Use **`secondnetwork/blade-tabler-icons`** (`composer require secondnetwork/blade-tabler-icons`). Components: `<x-tabler-calendar-week />`, filled as `<x-tabler-star-filled />` (or `-f`), or `@svg('tabler-star', 'size-5')`.

It is the only candidate that is current, allows Laravel 13, tracks Tabler's latest, and carries both outline and filled. It is the package the abandoned original points to.

## 1. Package comparison

Sources: Packagist p2 JSON, GitHub API / READMEs, npm registry (all saved under `docs/research/tabler-icons/`).

| | secondnetwork/blade-tabler-icons | ryangjchandler/blade-tabler-icons | jbsommeling/blade-tabler-icons | neazk/blade-tabler-icons | faustbrian/blade-icons-tabler-icons | blade-ui-kit/blade-icons + vendored SVG / `@tabler/icons` |
|---|---|---|---|---|---|---|
| Latest release | v3.49.0, 2026-10-06 (122 releases; weekly auto-PR per README) | v2.3.0, 2024-03-12; repo archived 2024-12-16 | v1.0.0, 2026-01-20 | 3.41, 2026-03-18 (2 releases) | does not exist (Packagist 404, GitHub 404) | blade-icons v1.10.1, 2026-06-30; `@tabler/icons` 3.49.0 |
| Laravel support | `illuminate/support` `^9 ... ^13` (v3.49.0) | `^10 \|\| ^11` | `^10 \|\| ^11 \|\| ^12`, no 13 | `^9 ... ^13` (3.41) | n/a | blade-icons `^8 ... ^13` |
| Tabler version tracked | 3.49.0, same as npm latest (clone in scratchpad is also 3.49.0) | 2.x era | fork of ryangjchandler (per its README); version not stated | 3.41, a fork of secondnetwork (README near-identical) | n/a | you pick |
| Outline + filled | yes: `star` and `star-filled` verified as raw files in `resources/svg/` (`icons-tabler-filled` class, `fill="currentColor"`) | not verified (v2 era, not checked) | README documents outline only | yes (same README as secondnetwork) | n/a | both, in the Tabler repo (`icons/outline`, `icons/filled`) |
| Licence | MIT | MIT | MIT | MIT | n/a | MIT (blade-icons, Tabler) |
| Naming | `tabler-<name>`, `tabler-<name>-filled` / `-f` | `tabler-<name>` | `tabler-<name>` | as secondnetwork | n/a | your own set prefix |
| Adoption | 964,848 installs, 40 stars | 535,974 installs, flagged `abandoned: secondnetwork/blade-tabler-icons` | 13,734 installs, 0 stars | 784 installs, 1 star | none | n/a |

Findings:
- `ryangjchandler` is abandoned on Packagist and names `secondnetwork` as its replacement. Stops at Laravel 11, so it cannot be installed on Mealboard.
- `jbsommeling` is a fork of ryangjchandler with a single release and no Laravel 13 constraint.
- `neazk` is a small fork of secondnetwork, two releases, trailing by 8 minor versions. No reason to prefer it.
- `faustbrian/blade-icons-tabler-icons` was not found on Packagist or GitHub. Treat as non-existent.
- Plain `blade-ui-kit/blade-icons` plus vendored SVGs works (it is the engine under all of these) but means copying files and refreshing them by hand. Only worth it if the package set (5,184 outline + 1,054 filled SVGs in Tabler 3.49.0) is unwanted; blade-icons only inlines what a view uses, and its caching (`php artisan icons:cache`) covers the lookup cost.
- `@tabler/icons` via npm gives raw SVGs but no Blade component; it suits a JS-bundled icon pipeline, which Mealboard does not have (server-rendered Blade/Livewire).

Practical notes for the swap:
- Tabler SVGs hardcode `stroke-width="2"`. The nav currently uses Heroicons at `1.5`; pass `style="stroke-width:1.5"` or a Tailwind `stroke-[1.5]` class (CSS beats the attribute) to keep that weight.
- Sizing already works with `class="size-6"`; `aria-hidden="true"` must be added per use (the components do not add it).

## 2. KitchenToolIcon

`app/Support/KitchenToolIcon.php` maps a word in a tool's name to a file in `resources/svg/kitchen-tools/` and returns the raw SVG as `HtmlString`; `tests/Feature/KitchenToolIconTest.php` asserts `<svg` and `stroke="currentColor"`.

Of the 41 files, 33 exist in Tabler 3.49.0 outline by the same name; 8 do not (`beef`, `cake-slice`, `cooking-pot`, `ham`, `pocket-knife`, `slow-cooker`, `toaster-oven`, `wok`) and come from Lucide (the folder carries `LICENSE-lucide.txt` and `LICENSE-tabler.txt`). `scale-outline` is also a renamed copy. So the package cannot serve the whole set.

Recommendation: **keep it separate.** It works, is tested, and mixes two libraries by design. Do not rewrite it onto the package. If one registry is ever wanted, blade-icons can register `resources/svg/kitchen-tools` as its own set (custom set in `config/blade-icons.php`) and `KitchenToolIcon::for()` could return `svg('kitchen-<name>')`. That is optional polish, not needed for this work.

## 3. Glyphs and inline SVGs to replace

Names verified against `icons/outline` and `icons/filled` of a Tabler 3.49.0 clone. "Use" is `<x-tabler-NAME />`. Line numbers are from `main` at commit f26a370.

### Inline Heroicons, `resources/views/layouts/app.blade.php` (all `stroke-width="1.5"`)

| Line | Current (Heroicons outline) | Tabler name | Notes |
|---|---|---|---|
| 98 | calendar-days (Plan) | `calendar-week` | `calendar` also exists |
| 109 | shopping-cart (Shop) | `shopping-cart` | filled exists |
| 124 | check-circle (Approve) | `circle-check` | filled exists |
| 135 | pencil-square (Log) | `edit` | `pencil` also exists |
| 147 | ellipsis-horizontal (More) | `dots` | |
| 156 | book-open (Recipes) | `book-2` | `book` also exists (has filled) |
| 164 | chart-bar (Insights) | `chart-bar` | outline only |
| 172 | computer-desktop (Settings) | `device-desktop` | `settings` exists too, arguably better for a Settings link |
| 192 | arrow-right-on-rectangle (Log out) | `logout` | `logout-2` also exists |

### Other inline SVGs

| File:line | Current | Tabler name |
|---|---|---|
| `resources/views/livewire/recipe-library.blade.php:9` | magnifier (search) | `search` |
| `resources/views/livewire/pantry-staples.blade.php:8` | magnifier (search) | `search` |
| `resources/views/livewire/recipe-library.blade.php:108` | plus path (Add recipe FAB) | `plus` |
| `resources/views/livewire/recipe-approval.blade.php:185` | Lucide undo arrow (Undo) | `arrow-back-up` |
| `resources/views/livewire/recipe-approval.blade.php:188` | Lucide double chevron (Skip) | `chevrons-right` |
| `resources/views/welcome.blade.php:66,93,123,142,153` | Laravel starter-page art/logos | leave alone (unused starter page, not icons) |

### Text glyphs used as icons

| File:line | Current glyph | Tabler name | Notes |
|---|---|---|---|
| `livewire/recipe-approval.blade.php:14` | 🎉 | `confetti` | `party-popper` does not exist in Tabler |
| `livewire/daily-catch-up.blade.php:8` | 🎉 | `confetti` | same empty-state treatment |
| `livewire/recipe-approval.blade.php:183` | ✕ (Reject button) | `x` | |
| `livewire/recipe-approval.blade.php:190` | ✓ (Approve button) | `check` | |
| `livewire/recipe-approval.blade.php:19` | ↶ (Undo the last one) | `arrow-back-up` | match the button on line 185 |
| `livewire/recipe-approval.blade.php:102` | ▶ YouTube ↗ | `brand-youtube` (or `player-play`) | drop `↗` or pair with `external-link` |
| `livewire/recipe-approval.blade.php:171` | ↗ (Open the source) | `external-link` | `arrow-up-right` exists, outline only |
| `livewire/shopping-list.blade.php:60` | ↗ (Walmart link chip) | `external-link` | |
| `components/check-tile.blade.php:15` | ✓ in small box | `check` | `text-[10px]` box; use `size-3` |
| `livewire/kitchen-tools.blade.php:25` | ✓ owned badge | `check` | |
| `livewire/kitchen-tools.blade.php:36` | ⋯ (details) | `dots` | `aria-label` stays |
| `livewire/kitchen-tools.blade.php:4` | "⋯" in help sentence | keep as text, or reword | inline in prose; an icon is optional |
| `components/recipe-tile.blade.php:24` | ★ rating badge | `star-filled` | |
| `livewire/recipe-library.blade.php:40` | ★ "4 and up" filter | `star-filled` | |
| `livewire/insights.blade.php:20` | ★ avg rating | `star-filled` | |
| `livewire/insights.blade.php:87` | `str_repeat('★'/'☆')` | `star-filled` x n, `star` x (5-n) | loop of components instead of `str_repeat` |
| `livewire/meal-log-controls.blade.php:32` | ★ / ☆ rating buttons | `star-filled` / `star` | `star-off` is not the empty star; use `star` |
| `livewire/plan-builder.blade.php:143` | `+` empty plan cell | `plus` | |
| `livewire/plan-builder.blade.php:189` | `&times;` close picker | `x` | |
| `livewire/kitchen-tools.blade.php:65` | `&times;` close details | `x` | |
| `livewire/kitchen-tools.blade.php:100` | `&times;` remove other name | `x` | |
| `livewire/partials/tile-grid-bar.blade.php:9` | `&times;` stop selecting | `x` | |
| `livewire/channel-settings.blade.php:54` | `&times;` remove channel | `x` | consider `trash` |
| `livewire/partials/cooking-step-rows.blade.php:13` | `&times;` remove step | `x` | consider `trash` |
| `livewire/partials/ingredient-rows.blade.php:32` | `&times;` remove row | `x` | consider `trash` |
| `livewire/partials/recipe-tool-rows.blade.php:18` | `&times;` remove tool | `x` | consider `trash` |
| `livewire/recipe-create.blade.php:3` | `&larr;` Recipes | `arrow-left` | outline only; `chevron-left` also exists |
| `livewire/recipe-detail.blade.php:3` | `&larr;` Recipes | `arrow-left` | |
| `livewire/shopping-list.blade.php:11` | `&larr;` Back to plan | `arrow-left` | |

### Judged not icons (leave as text)

- `&times;` / `×` as a multiplication sign: `livewire/recipe-detail.blade.php:79` (`{{ $tool->count }} &times;`) and `livewire/plan-builder.blade.php:223` (`×{{ $uses }} this week`).
- `—` empty-value dashes (`plan-builder.blade.php:145,157`, `insights.blade.php:13,22,29`), `·` separators, `≤` in `recipe-library.blade.php:37`, `&ldquo;`/`&rdquo;`, `&hellip;`, `…`, and the `—` in page titles.

### Name existence

All names above were checked against `icons/outline` in the clone. Filled variants exist for: `calendar-week`, `shopping-cart`, `circle-check`, `edit`, `dots`, `settings`, `device-desktop`, `search`, `plus`, `check`, `x`, `star`, `external-link`, `brand-youtube`, `confetti`, `trash`. Outline only (no filled): `chart-bar`, `book-2`, `logout`, `arrow-back-up`, `chevrons-right`, `arrow-left`, `arrow-up-right`, `chevron-left`. Does not exist: `party-popper`.

## Sources

- Packagist p2 responses: `docs/research/tabler-icons/packagist-*.json`; installs and abandonment: `packagist-search-tabler-blade.json`.
- GitHub repo metadata: `repo-*.json` (secondnetwork pushed 2026-10-06, ryangjchandler archived).
- READMEs: `secondnetwork-README.md`, `neazk-README.md`, `jbsommeling-README.md`.
- npm: `npm-tabler-icons-latest.json` (`@tabler/icons` 3.49.0).
- Tabler icon names: clone of `tabler/tabler-icons` 3.49.0 (`icons/outline`, `icons/filled`), scratchpad only; not committed.
