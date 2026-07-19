# Tier 3 — `walmart:push-list` (Walmart list page)

Tier 3 pushes the locked week's unchecked shopping-list items onto a Walmart
**list** (the "My Items"/lists feature — NOT the cart) by driving a headed
Chrome. The user then shops that list themselves — on their phone, in-store, or
by carting from it. The command never touches cart or checkout.

> **Status: PROVISIONAL.** Nothing about Walmart's list page has been verified
> against the real UI. Everything in "Unverified" below — including every
> selector — is a best guess. **The first task of the live supervised run is to
> open the real list page, inspect its markup, and update BOTH this document
> and the selector constants in `app/Walmart/ChromeListBrowser.php`.** The
> browser path is deliberately untested by Pest; the live run is its
> verification.

## KNOWN (verified, agent-buildable side)

- **Item assembly** (`App\Actions\Planning\BuildListPushItems`, unit-tested):
  latest locked week by default, `--week=YYYY-MM-DD` override; items come from
  `BuildShoppingList` (staples excluded), checked items are skipped
  (`checked_items` keys are `name|unit`), each line reduces to cleaned keywords
  via `CleanIngredientKeywords` ("2 cups diced yellow onion" → "yellow onion"),
  duplicates dedupe.
- **Config** (both required; the command fails fast before launching anything
  when either is missing):
  - `MEALBOARD_CHROME_PROFILE` → `config('mealboard.chrome_profile')` — a
    Chrome user-data directory that is already logged into walmart.com. Use a
    dedicated profile dir (e.g. `~/.mealboard-chrome`), log in once manually.
  - `MEALBOARD_WALMART_LIST_URL` → `config('mealboard.walmart_list_url')` —
    the URL of the target list page.
- **Browser stack**: `chrome-php/chrome` (composer), headed
  (`headless => false`), persistent `userDataDir` profile. The command talks to
  the `App\Walmart\ListBrowser` interface; `ChromeListBrowser` is the only real
  implementation, so tests never launch Chrome.
- **Fail-loud contract**: every selector interaction is wrapped. Any miss
  aborts the whole run with a `RuntimeException` naming the selector and item:
  `selector miss: <selector> (…) — failed on item N of M ("<keywords>"); list
  may be half-filled up to item N-1; inspect docs/tier3-list.md and update the
  selectors.` It never continues silently past a miss.

## UNVERIFIED (all PROVISIONAL — live run must confirm)

| Piece | Provisional assumption | Where |
| --- | --- | --- |
| List page URL shape | A stable per-list URL exists (something under `walmart.com/lists/...`) that renders the list with an add-item input when logged in. Whatever it really is goes into `MEALBOARD_WALMART_LIST_URL`. | `.env` |
| Add-item input | `input[placeholder*="Add an item" i]` | `ChromeListBrowser::ADD_ITEM_INPUT` |
| Confirm gesture | Typing keywords then pressing **Enter** adds a free-text item (or accepts the top typeahead suggestion) | `ChromeListBrowser::addItem()` |
| Post-add settle | ~750 ms is enough for the page to register the add before re-finding the input | `ChromeListBrowser::addItem()` |
| Page readiness | `NETWORK_IDLE` after navigation means the list UI is interactive; the input exists within 15 s | `ChromeListBrowser::open()` / `find()` |
| Typeahead behavior | Unknown whether Enter adds free text or force-selects a product suggestion; unknown whether an item can land as the *wrong* product silently | live run to characterize |
| Logged-out/expired session | Assumed the page simply won't contain the input (→ loud selector miss). There may instead be a login redirect — the run stops either way, but the doc should record what actually happens | live run |

## Live supervised run — checklist

1. Log the dedicated Chrome profile into walmart.com manually (never the
   agent); set `MEALBOARD_CHROME_PROFILE` and `MEALBOARD_WALMART_LIST_URL`.
2. Open the real list page; inspect the add-item input and the confirm flow in
   devtools.
3. Update `ChromeListBrowser` selectors + the table above; delete rows that
   move to KNOWN.
4. Run `php artisan walmart:push-list` with the human watching; on any
   selector-miss abort, fix and re-run (already-added items will duplicate on
   the Walmart side — clean up by hand, then consider idempotency if it hurts).
5. Same hard rules as Tier 2 (docs/tier2-cart.md): headed only, no CAPTCHA
   evasion, stop on any login/verification prompt, and nothing is ever carted,
   checked out, or purchased.
