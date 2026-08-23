# Tier 3 — `walmart:push-list` (Walmart list page)

Tier 3 pushes the locked week's unchecked shopping-list items onto a Walmart
**list** (the "My Items"/lists feature — NOT the cart) by driving a headed
Chrome. The user then shops that list themselves — on their phone, in-store, or
by carting from it. The command never touches cart or checkout.

> **Status: VERIFIED** against the real UI in the first supervised run
> (2026-07-21, list "Mealboard — Week of 27 Jul",
> `https://www.walmart.com/lists/WL/7d83980b-a9e9-4736-a707-e9dfe3cef793`).
> Everything below is observed behavior.

## KNOWN (agent-buildable side, unit-tested)

- **Item assembly** (`App\Actions\Planning\BuildListPushItems`): latest locked
  week by default, `--week=YYYY-MM-DD` override; items come from
  `BuildShoppingList` (staples excluded), checked items are skipped
  (`checked_items` keys are `name|unit`), each line reduces to cleaned keywords
  via `CleanIngredientKeywords` ("2 cups diced yellow onion" → "yellow onion"),
  duplicates dedupe. Returns a flat `list<string>`.
- **Config** (both required; the command fails fast before launching anything
  when either is missing):
  - `MEALBOARD_CHROME_PROFILE` → set: `/Users/willem/.mealboard-chrome`
    (persistent dir, logged into walmart.com once by the user, 2026-07-21).
  - `MEALBOARD_WALMART_LIST_URL` → set: the `WL/{uuid}` URL above. A new
    week's list means updating this value.
- **Browser stack**: `chrome-php/chrome` (composer), headed
  (`headless => false`), persistent `userDataDir` profile. The command talks to
  the `App\Walmart\ListBrowser` interface; `ChromeListBrowser` is the only real
  implementation, so tests never launch Chrome.
- **Fail-loud contract**: every selector interaction is wrapped. Any miss
  aborts the whole run with a `RuntimeException` naming the selector and item.
  It never continues silently past a miss.

## VERIFIED (live-run findings, 2026-07-21)

- **Keyword entries ARE allowed.** The "Add an item" flow is a free-text input
  ("Enter an item, like 'milk' or 'coffee'."). Typed keywords + Enter creates a
  standing list entry — no product resolution required. Each entry later offers
  optional product suggestions and a "Find item" affordance; resolving to real
  products is a human/in-store step, not the command's job.
- **List URLs**: `walmart.com/lists/WL/{uuid}`. Create flow: "Create a new
  list" button on `/lists` → dialog, input labeled "Enter list name", "Create"
  button stays disabled until a real input event fires.
- **Add input selector**: `input[aria-label*="Add an item"]` (placeholder
  "Add an item to your list"), opened by a button with exact text
  `Add an item`. After a successful add the input stays open for the next
  entry; an "Added to list: <item>" toast confirms.
- **Pagination**: entries render 18/page, "Most recent" first. Count via the
  `| N items` header text, never visible rows.

## HAZARDS (each observed live)

1. **Captcha**: list creation triggered a "Robot or human?" press-and-hold
   dialog (heading `Robot or human?`). STOP and hand to the human — never
   automate. The pending action completes once the human passes it.
   **ESCALATION (2026-07-21, blocks this command)**: in the chrome-php headed
   browser the press-and-hold challenge LOOPS FOREVER even for a real human —
   PerimeterX rejects the flagged automation environment itself, not the
   gesture. The same challenge passed first-try in a playwright-cli session
   earlier the same night. Consequence: `walmart:push-list` cannot currently
   complete on Walmart via chrome-php. Paths forward (no evasion, ever):
   (a) reimplement the browser seam on the stack that empirically passed,
   (b) findings from the external-tools research (GitHub/forums), or
   (c) retire Tier 3 and keep Tier 1/2 (the 42-item week was assembled fine
   via the supervised interactive route).
   **Path (b) is answered in `docs/tier3-research.md`** — no maintained
   list-write tool exists; the live options are the affiliate cart link (§3) and
   an in-browser list writer (§6, built on the substrate in §7). Selecting one
   is the repo owner's call.
2. **Silent focus loss during bulk adds**: after ~8 rapid type+Enter adds the
   input lost focus and later keystrokes went to the page with NO error.
   Mitigation (now the required pattern): re-focus the input before EVERY
   entry, pace ≥1s between adds, and verify the `| N items` count incremented
   after each add — a non-incrementing count is the loud-abort condition.
3. **Cart is account-synced**: the cart badge can change with no automation
   action (saved carts sync at login). Not evidence of misbehavior — and the
   command still never touches cart/checkout.
4. **React-controlled input**: programmatic value-set + synthetic events do NOT
   register adds reliably; real keystrokes (CDP `type`/`press`) are required.

## Standing rules

Same hard rules as Tier 2 (docs/tier2-cart.md): headed only, no CAPTCHA
evasion, stop on any login/verification prompt, and nothing is ever carted,
checked out, or purchased. Re-runs may duplicate already-added items on the
Walmart side — clean up by hand; add idempotency (read existing entries first)
if it ever hurts.
