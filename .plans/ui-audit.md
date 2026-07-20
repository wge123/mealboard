# Mealboard UI baseline audit — 2026-07-19

Shots: `.plans/ui-shots/00-baseline/<page>-390.png` and `<page>-1280.png` (gitignored).
Stack at time of audit: Bootstrap 5.3 CDN + one hand-rolled `public/css/app.css`. No build step, no theme, no dark mode.

## Global (every page)

**Exists:** Bootstrap navbar (brand in green, 6 links, user name + logout), `container` main, off-white body.

**Problems:**
- Zero identity. Default Bootstrap link-blue (#0d6efd) is the dominant interactive color everywhere — the one color that screams "template". The green brand word is the only intentional choice on screen and it fights the blue instead of leading.
- Mobile nav is a hamburger that collapses to a plain stacked list; on a phone-first food app the primary nav should be reachable by thumb (bottom bar), not hidden behind a top-right toggle.
- Typography is stock system stack at Bootstrap defaults: no display face, headings differ from body only by size/weight, no personality, no rhythm. Everything is left-aligned gray-on-white text blocks.
- No dark mode at all.
- Cards are Bootstrap defaults: 1px gray border, tiny radius, cramped padding. Tap targets in lists (meal slots, table rows) are links, not big touch surfaces.
- No favicon/app-feel; no page transitions or micro-interactions anywhere; nothing animates, confirms, or delights.

## / (redirect → /plan)
Pure redirect — fine. Same audit as /plan.

## /plan — Weekly plan
**Exists:** week selector + "Create next week", Locked badge + date range, Shopping list button, 5 day columns (Mon–Fri) each with Breakfast/Lunch/Dinner slot cards containing a recipe link.

**Problems:**
- The heart of the app looks like a admin CRUD grid. Slot cards are gray-bordered boxes with an ALL-CAPS gray label and a blue underlined link — no meal-type color coding (breakfast/lunch/dinner all identical), no imagery, no time/servings metadata on the card.
- Desktop: 5 equal columns floating in a sea of empty white below; no "today" emphasis — Monday looks identical to Friday, and nothing tells you what today is.
- Mobile: an endless vertical scroll of identical gray boxes; day headers (gray strips) barely separate days; no snap/anchor to today.
- "Locked" is a yellow warning badge — reads as an error, but locking is the *success* state of a week. Wrong affordance.
- Mixed button styles: white dropdown + solid blue button + solid blue Shopping list, no hierarchy between "Create next week" (rare) and "Shopping list" (frequent).
- Empty slots (unplanned weeks) have no visible empty/add state in the shot; no drag/swap affordance visible.

## /recipes — Recipe library
**Exists:** search input, 4 filter selects (meal, cuisine, tag, rating), results table (Title/Meal/Cuisine/Time/Tags…).

**Problems:**
- **A data table for a recipe collection is the wrong shape entirely.** On 390px the table overflows and clips mid-column ("Tag…", "he…/ve…/ma…" truncated tag pills) — literally unreadable. Should be cards/list rows with the whole row tappable.
- Filter row is 4 stacked full-width selects + search = a wall of form controls above the fold before you see a single recipe. Should be chips/segmented controls or a single filter sheet.
- No rating shown as stars, no cook-time iconography, no cuisine flavor — everything is plain text cells.
- Blue multi-line title links wrap awkwardly ("Salmon Teriyaki with Broccoli" over 3 lines beside a 60px column).
- No empty state or result count visible; no pagination visible.

## /recipes/create — Add recipe
**Exists:** back link, long single-column form: title, meal, cuisine, description, prep/cook/servings, source URL, tags (comma separated), instructions (markdown), ingredients.

**Problems:**
- Undifferentiated field soup — no grouping into sections (Basics / Timing / Ingredients / Instructions), no visual anchors; on mobile it's ~4 screens of bare inputs.
- "Tags (comma separated)" and "Instructions (markdown)" push data-format burden onto the user with no tag chips input or markdown preview.
- No sticky/save affordance visible while scrolling; submit is somewhere at the bottom.
- Labels are plain body text with default spacing; required vs optional not indicated.

## /recipes/{id} — Recipe detail
**Exists:** back link, title, Edit button, status + meta badges, prep/cook/total/serves line, source, approval line, description, Reject/Archive buttons, ingredients table, numbered instructions.

**Problems:**
- Action soup: Edit floats alone under the title, Reject/Archive sit mid-page between description and ingredients — destructive actions in the reading flow instead of a menu/footer.
- Badge pile: 6 near-identical gray pills mix status ("Approved"), meal type, cuisine, and tags — different kinds of information, one visual weight.
- Ingredients are a zebra table with a weird narrow qty/unit column ("60 g | baby spinach | roughly chopped") — fine data, but no scaling control, no checkable rows for cook mode.
- Instructions are a default `<ol>` — no step cards, no cook-mode/large-type option for a propped-up phone in the kitchen.
- Meta line "Prep 5 min · Cook 8 min · 13 min total · Serves 2" is plain small text — this is card-worthy at-a-glance info.

## /approve — Recipe approval
**Exists:** one candidate card: title, meta pills, description, key-ingredient pills, Reject (R) / Approve (A) full-width buttons with kbd hints.

**Problems:**
- Closest page to a real design (clear single task, big buttons, kbd hints) but it's centered in a void — no queue context (how many left?), no progress, no "skip/later".
- Reject above Approve is inverted emphasis order for a swipe-era approval flow; card doesn't swipe.
- Heading "Approve recipes" is centered while every other page is left-aligned — inconsistent.
- No image/preview of the source video for a YouTube-discovered recipe; you approve blind from text.

## /log — Daily catch-up
**Exists:** heading, subtitle, "All caught up" line.

**Problems:**
- The empty state is a bare sentence — no illustration, no celebration, no next action ("view this week's plan"). This is the page's *success* state and it looks like a 404.
- Nothing shows what logging looks like when there ARE meals (ate/skipped/rating controls untested here) — but the empty shell suggests zero layout beyond stacked text.

## /plan/{id}/shopping-list
**Exists:** heading + week context, Back to plan, pantry-staples toggle, Copy as markdown / plain list, category headings (PRODUCE…), per-item: checkbox, qty+name+prep note, "search" link, Paste product URL input + "Found it" button.

**Problems:**
- Every single item drags a full-width "Paste product URL" input + "Found it" button with it — triples row height, makes a 30-item list feel infinite on mobile, and buries the actual checklist. That workflow belongs behind a per-item expand.
- Checkboxes are default-size (~16px) — far below 44px tap targets for the ONE page that gets used one-handed in a store.
- No progress indicator (n of m checked), no checked-item styling visible, no "hide checked".
- Two side-by-side blue outline Copy buttons + gray Back button + toggle = four different control styles in the header.

## /insights
**Exists:** four sections: ate-vs-skipped by slot and by weekday (tables with green progress bars), top recipes by rating (empty), rejection rate (empty).

**Problems:**
- Tables again where simple viz belongs; single sparse rows ("Breakfast 1 0 100%") look broken rather than early.
- Two of four sections are empty strings of body text — empty states with no guidance on what will populate them or when.
- Desktop puts two half-empty tables side by side and leaves the bottom 60% of the viewport blank.

## /settings/channels
**Exists:** heading, explainer sentence, Channel ID + Channel name inputs, full-width blue Add button, empty-state line.

**Problems:**
- "Channel ID (UC...)" demands a raw YouTube channel ID — maximal user burden; should accept a channel URL/@handle.
- Placeholder-only labels (inputs have no visible labels) — accessibility and recall failure.
- Full-width bright blue Add for a rare admin action; empty state is again one bare sentence.

## /login
Unreachable locally — `AutoLoginLocal` middleware logs every request in as Willem in the local env, so the logged-out login page cannot be captured here. To be shot/styled in step 10.

## Three harshest findings (summary)
1. **/recipes is a clipped, overflowing data table on mobile** — tag pills and columns truncate mid-word at 390px; the core browsing surface of a phone-first food app is effectively broken on phones.
2. **The app has no visual identity whatsoever** — default Bootstrap blue links/buttons, system font, gray-bordered boxes; meal types, days, and food itself get zero color, imagery, or typographic voice. It reads as a scaffold, not a product.
3. **The shopping list — the most in-store, one-handed page — has ~16px checkboxes and a per-item URL-paste form inline on every row**, burying the checklist under provisioning chrome and missing tap-target minimums by ~3x.
