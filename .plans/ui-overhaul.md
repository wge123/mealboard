# Mealboard UI Overhaul — Tailwind 4 + daisyUI

User verdict on the current Bootstrap-CDN UI: "it looks terrible." Approved direction (2026-07-19):
replace the UI framework entirely and do a real design pass. **This supersedes the original
master-plan convention "no Tailwind / no build step"** — record that override in DECISIONS.md as #8.

## Framework decision (made)

- **Tailwind CSS v4** via Vite (`@import "tailwindcss"` — v4 needs no config file for basics).
- **daisyUI 5** as the component layer (CSS-only, themed, dark mode built in). No Flux, no MaryUI
  unless a step hits a wall daisyUI can't cover — then note it, don't add the dependency unilaterally.
- All interactivity stays Livewire 3 + Alpine — this is a restyle, not a rewrite. Keep component
  PHP classes untouched wherever possible; the work is in Blade templates + a new CSS entry.
- Bootstrap CDN `<link>`/`<script>` and `public/css/app.css` are removed at the end of the
  foundation step. `wire:confirm` dialogs and Alpine behaviors must keep working (Bootstrap JS is
  currently used only for the navbar collapse — the new shell replaces it).

## Design direction

- Phone-first food app. Warm neutral base (stone/cream), one saturated accent (herb green
  `oklch`-tuned), meal-type accent colors used consistently everywhere: breakfast=amber,
  lunch=sky, dinner=violet, any=neutral.
- Custom daisyUI theme (light + dark) named `mealboard` / `mealboard-dark`; fonts: Inter for UI,
  a display serif (e.g. Fraunces) for page titles and recipe names, loaded locally via
  `@fontsource` packages (no Google Fonts CDN).
- Big tap targets (min 44px), generous whitespace, rounded-2xl cards, subtle borders over shadows.
- **Navigation shell**: bottom tab bar on <lg screens — Plan, Shop, Approve, Log, More (More opens
  a sheet with Recipes, Insights, Channels, Logout). Slim top navbar on lg+. Active-state tinting.
  The Approve tab shows a pending-count badge.

## Conventions for every step

- App root: /Users/willem/Developer/Personal/mealboard. Laravel 13 + Livewire 3, tests with Pest
  (`./vendor/bin/pest`), format with `./vendor/bin/pint --dirty` before every commit.
- One commit per step, message "UI <step-number> — <what>". Never add Claude attribution.
- Every step ends with: full suite green + the step's pages screenshotted at 390px AND 1280px via
  `playwright-cli` (auto-login is active in local env — no login step needed; screenshots to
  .plans/ui-shots/<step>/, gitignored).
- Existing Pest tests that assert Bootstrap class names or markup structure: update the assertion
  to the new markup in the same step that restyles the page — never delete a test to make it pass.
- `vite build` must succeed in every step after the foundation lands; `npm run dev` is not
  assumed running (use built assets via `@vite` in the layout).

## Steps

### 1. Baseline audit
Screenshot all pages (/, /plan, /recipes, /recipes/create, /recipes/{id}, /approve, /log,
/plan/{locked}/shopping-list, /insights, /settings/channels, /login logged-out state) at 390px and
1280px into .plans/ui-shots/00-baseline/. Write .plans/ui-audit.md: per page, what exists, what's
ugly/missing (states, spacing, hierarchy). Add .plans/ui-shots/ to .gitignore. No product code changes.

### 2. Foundation: Vite + Tailwind 4 + daisyUI + theme
npm init if needed; install tailwindcss@4, @tailwindcss/vite, daisyui@5, @fontsource-variable/inter,
@fontsource/fraunces. resources/css/app.css: `@import "tailwindcss"; @plugin "daisyui" { themes: ... }`
with the two custom `mealboard` themes (tokens above, incl. per-meal-type CSS custom properties);
set Inter as the UI/sans base font and Fraunces as the display font in the theme.
Wire @vite into the layout; remove Bootstrap CDN link+script and public/css/app.css. Rebuild the
layout shell minimally (temporary plain top nav) so every page still renders unstyled-but-functional.
Theme toggle (light/dark/system, Alpine + localStorage, `data-theme`). Verify: vite build passes,
suite green, every page still 200s; screenshot / and /plan at 390+1280 → .plans/ui-shots/02/.

### 3. App shell: bottom tab bar + top nav
Bottom tab bar (<lg): Plan, Shop (→ latest locked week's shopping list, hidden if none), Approve
(with pending-count badge), Log, More (daisyUI dropdown/sheet: Recipes, Insights, Channels, theme
toggle, Logout). Top navbar (lg+) with the same items inline. Active route tinting. Safe-area
padding (env(safe-area-inset-bottom)). Update any nav-related tests.

### 4. Plan builder redesign
/plan is the flagship: sticky week header (week picker as daisyUI select, status badge, actions);
day cards Mon–Fri; slot rows with meal-type accent border + icon, recipe name in display font,
total minutes chip; empty slot = ghost "add" affordance opening the picker; picker overlay becomes
a daisyUI modal with search input styled; lock/complete buttons restyled (btn-primary/btn-success,
wire:confirm kept). Breakfast-skip note becomes a subtle callout. Quick-log controls on past slots
restyled inline (Yes/No pills + star row). No new npm deps — daisyUI modal + Alpine only; if a wall
is hit, note it and stop. Update PlanBuilder/MealLogControls tests' markup assertions.

### 5. Shopping list + tap-through redesign
Category sections with sticky headers; each line a large tappable row: checkbox (daisyUI, satisfying
checked state with strikethrough), qty+unit muted, product/search link chevron with ↗ product / ↗
search chip; found-it paste field collapses behind a small "+ save product" toggle per row; staples
toggle as daisyUI switch; copy-markdown/plain buttons in a sticky bottom action bar above the tab bar.
Update ShoppingListTest markup assertions (export strings unchanged).

### 6. Approval cards redesign
Full-bleed card: 16:9 thumbnail (YouTube) or meal-type gradient placeholder; title in display font;
chips for time/meal-type/cuisine; key ingredients as wrapping badge row; description clamped with
expand. Approve (btn-primary, full-width) / Reject (btn-ghost) + kbd hints "A"/"R"; Alpine
swipe-out transition between cards (Alpine x-transition only — no gesture/animation libs, no new
npm deps; note walls instead); progress "3 of 6 pending". Empty state with illustration-ish
emoji + link to channels. Update RecipeApprovalTest assertions.

### 7. Recipe library, detail, create/edit redesign
Library: responsive card grid (image_url placeholder gradient by meal type), title, cuisine chip,
time chip, rating stars when rated; search + filters in a collapsible filter bar (daisyUI join +
selects); "Add recipe" FAB-style button on mobile. Detail: hero header (title display font, chips),
two-column lg layout (ingredients card + instructions prose), status controls as a button group;
edit mode form fields daisyUI-styled; ingredient rows as compact grid with add/remove ghost buttons.
Create: same field styling; paste textarea prominent with "Parse" / "AI parse" buttons + parser
badge (badge-info/warning). Update the three test files' markup assertions.

### 8. Log + insights redesign
/log: yesterday's meals as cards with the same quick-log controls (Yes/No pills, tappable star row
— stars ≥44px hit area), empty state "all caught up". /insights: stat cards row (totals), ate/skip
by slot+weekday as labeled progress bars (daisyUI progress, meal-type colors), top-10 as a clean
table with rank + stars, rejection-rate radial-progress. Update InsightsTest/DailyCatchUpTest
assertions.

### 9. Auth + polish pass
/login styled (card, centered, app name in display font) for the non-auto-login case. Global
polish: wire:loading indicators on every action button (loading-spinner), consistent empty states,
error/success flash → daisyUI toast (Alpine auto-dismiss), focus-visible rings, prefers-reduced-motion
respected on transitions. 404/500 pages minimal branding.

### 10. Final verification + DECISIONS/README update
Full suite green; vite build clean; screenshot ALL pages at 390/1280 into .plans/ui-shots/10-final/;
side-by-side check against 00-baseline; no horizontal scroll anywhere at 390px; Lighthouse-style
sanity (tap targets, contrast) by eye. Append DECISIONS.md #8 (Tailwind override rationale).
Update README (remove "Bootstrap via CDN, no build step" claims; add `npm run build` to setup,
note Vite). Update the master todo's convention note via a body append.

## Done means

Steps 1–10 all Done; suite green; every page restyled with the new shell; baseline vs final
screenshots captured; DECISIONS.md #8 + README updated.
