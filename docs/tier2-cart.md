# Tier 2 — supervised agent-driven Walmart cart fill

Tier 2 is the middle rung of the Walmart handoff ladder: a Claude agent fills the
cart **while the human watches**, one item at a time, using the mealboard MCP
server as its source of truth and its memory. The agent never buys anything —
the run ends with the human reviewing the cart and placing the order themselves.

## Prerequisites

1. **Logged-in browser.** The user is logged into walmart.com in Chrome with
   their normal account (cart, addresses, payment already set up). The agent
   never logs in and never handles credentials.
2. **Browser control attached to that session.** Claude in Chrome (the
   `claude-in-chrome` extension) or a Playwright MCP attached to that same
   logged-in Chrome instance. Not a fresh headless browser — the whole point is
   to reuse the human's real, logged-in, watched session.
3. **Mealboard MCP server available** to Claude Code. The repo ships a
   project-scoped [`.mcp.json`](../.mcp.json), so a Claude Code session started
   in this directory discovers the server itself — the only human step is
   approving it once at the trust prompt (`/mcp` lists it as `mealboard`).
   Nothing needs to be added to `~/.claude.json` by hand.

   The entry invokes Herd's php and this repo's `artisan` by **absolute path**
   on purpose. `php` is not on the PATH a GUI-launched Claude Code inherits
   (Herd only puts it on an interactive shell's PATH), so a bare `php` command
   would fail at spawn time with nothing but a dead server to show for it.
   Verified 2026-08-31 by running the file's own `command` + `args` with an
   empty environment: the list came back, `rc 0`.

   If you ever need it registered globally instead (a session started outside
   this directory), the equivalent is:

   ```bash
   claude mcp add mealboard -- "$HOME/Library/Application Support/Herd/bin/php" /Users/willem/Developer/Personal/mealboard/artisan mcp:serve
   ```

4. **A locked week — the one you actually want.**
   `get_current_shopping_list` serves the latest *locked* plan by
   `week_start_date` and applies no recency rule whatsoever
   (`McpServer::getCurrentShoppingList`), so a draft plan for this week loses
   to a locked plan from a month ago. Lock the current week's plan in
   Mealboard first. The payload reports `weeks_stale` alongside the date —
   `0` is this week, anything higher is how many weeks old the list you are
   about to shop actually is.
5. **The human is present.** This is a supervised run. If the human steps away,
   the agent pauses.

## Readiness check (run before the human sits down)

**First: does Claude Code see the server at all?** From the repo root:

```bash
cd /Users/willem/Developer/Personal/mealboard && claude mcp list | grep mealboard
```

Three answers, and each one names exactly what is left to do:

- `⏸ Pending approval`: the shipped `.mcp.json` is found and its spawn line is
  valid, so approving it at the trust prompt is the human moment (the line says
  so: "run `claude` to approve"). This is the expected answer before the first
  run (observed 2026-09-01).
- `✔ Connected`: already approved on this machine, nothing to do.
- `✘ Failed to connect`: the spawn itself is broken (almost always the php
  path, see prerequisite 3). Fix it before the human sits down. A supervised
  run that discovers this live has already wasted their attention.

`claude mcp list` reports the pending state **without approving anything**, so
it is safe to run ahead of the human and it writes nothing to `~/.claude.json`.

Then, one command proves prerequisites 3 and 4 at once — the server starts and a
locked week exists — so a supervised run never dies on its first tool call:

```bash
cd /Users/willem/Developer/Personal/mealboard
printf '%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' \
  '{"jsonrpc":"2.0","method":"notifications/initialized"}' \
  '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"get_current_shopping_list","arguments":{}}}' \
  | php artisan mcp:serve | tail -1
```

Expect a JSON-RPC result whose text payload starts with a `week_start_date`
followed by `"weeks_stale":0`. `No locked week.` means prerequisite 4 is unmet
— lock the plan in Mealboard and re-run. If `php` is not found (Herd puts it on PATH only in an interactive
shell), use the absolute binary:
`"$HOME/Library/Application Support/Herd/bin/php" artisan mcp:serve`.

**A non-zero `weeks_stale` stops the run.** The only failure this command
reports loudly is a total absence of locked plans (`No locked week.`); a stale
week comes back as an ordinary, healthy-looking list. Observed 2026-08-29 and
again 2026-08-30: the tool served `2026-07-27` — locked 2026-07-20 — while the
newest plan, `2026-08-17`, sat in `draft` and was therefore invisible to it;
that list reports `"weeks_stale":5` when re-measured on 2026-09-01, and the
number keeps growing every Monday it is left alone. Anything but `0` means you
are one lock away from shopping the wrong week into the human's real grocery
cart, so go lock the right plan before the human sits down. (Shopping an older week on
purpose is fine — but say so out loud first.)

Two more things worth knowing before the first run:

- **Match memory is nearly empty, and the single row in it did not come from a
  cart run.** `walmart_matches` holds exactly 1 row on this machine
  (re-measured 2026-09-04): ingredient `onion` → *Fresh Whole Yellow Onions,
  Each*, written 2026-09-03 08:04 while building the Tier 3 add-to-cart link,
  not by anyone filling a cart. So one item opens directly via loop step 2a and
  the other 42 are fresh searches — the first supervised run is still the slow
  one, and it exists to fill that table.
- **Take a baseline first, because a bare count no longer proves anything.**
  This runbook's acceptance test is "≥1 *new* match", and
  `WalmartMatch::count() > 0` has been true since 2026-09-03 without a single
  cart ever being filled. Record the count *and the clock* before the run:

  ```bash
  "$HOME/Library/Application Support/Herd/bin/php" artisan tinker --execute='
    $p = App\Models\MealPlan::where("status","locked")->orderByDesc("week_start_date")->first();
    echo $p->week_start_date->toDateString()," checked=",count($p->checked_items ?? []),
         " matches=",App\Models\WalmartMatch::count()," at=",now()->toDateTimeString(),PHP_EOL;'
  ```

  Observed 2026-09-04: `2026-07-27 checked=1 matches=1 at=2026-09-04 ...`.

## The loop

1. Call `get_current_shopping_list`. Check `weeks_stale` first — if it is not
   `0`, say so and confirm the week before adding anything. It returns the
   locked week's items grouped by store category; each item carries:
   - `keywords` — cleaned Walmart search keywords (quantities/units/prep
     stripped: "2 cups diced yellow onion" → "yellow onion"),
   - `product_url` — the remembered Walmart product when a previous run
     confirmed a match, else `null`,
   - `checked` — already in the cart (or otherwise handled); **skip these**.
2. For each unchecked item, in list order:
   - **Known match** (`product_url` present): open the product page directly.
   - **No match**: search walmart.com for the item's `keywords`.
3. Add the product to the cart (the product page / search tile "Add to cart"
   button — never anything on the cart or checkout pages).
4. **Ambiguous match → ask the human, never guess.** If the search results
   don't contain one obviously-right product (multiple plausible brands/sizes,
   sponsored lookalikes, out-of-stock substitutions offered), stop and ask:
   show 2–3 candidates and let the human pick. A wrong guess poisons the match
   memory for every future week.
5. **Write every confirmed pick back** via `save_product_match`
   (`ingredient` = the shopping-list name, `product_url`, `product_name` as
   listed on Walmart). This is what makes next week's run mostly step-2a
   direct-opens. Do this for *every* confirmed item, including ones that
   already had a `product_url` when the human confirms a different product.
6. When every unchecked item is handled: report a summary (added / asked /
   skipped) and stop. **The human reviews the cart and places the order
   themselves.** After the order is placed, the human (or the agent, on the
   human's say-so) calls `mark_list_purchased` with the week's start date.

## Hard rules

- **NOTHING is ever purchased or checked out by the agent.** No "Place order",
  no "Continue to checkout", no payment or address screens, no substitution
  confirmations at checkout. Cart adds only.
- **No headless.** The browser is visible and the human can see every action.
- **No CAPTCHA evasion.** If a CAPTCHA, press-and-hold check, or bot challenge
  appears, stop and hand the browser to the human.
- **Stop and ask on any login/verification prompt** (re-auth, OTP, "verify
  it's you"). The agent never enters credentials or codes.
- **Ambiguity is a question, not a judgment call.** When in doubt about which
  product, ask (loop step 4).
- Stay inside walmart.com pages needed for the run: search, product pages,
  and the cart page for verification counts only.

## Worked example — one item

> Shopping list item: `{"name": "chicken thighs", "qty": 1, "unit": "lb",
> "keywords": "chicken thighs", "product_url": null, "checked": false}`

```text
Agent:  get_current_shopping_list → "chicken thighs" is unchecked, no saved match.
Agent:  Searching walmart.com for "chicken thighs".
Agent:  Results show (1) Freshness Guaranteed Boneless Skinless Chicken Thighs,
        1.7–2.1 lb tray, $4.32/lb; (2) Tyson Bone-In Chicken Thighs, 5 lb bag;
        (3) a sponsored frozen 3 lb bag. The list wants ~1 lb and doesn't say
        bone-in or boneless — which one?
Human:  The Freshness Guaranteed boneless tray.
Agent:  Adding it to the cart… added (cart shows 1 item).
Agent:  save_product_match(
          ingredient: "chicken thighs",
          product_url: "https://www.walmart.com/ip/Freshness-Guaranteed-Boneless-Skinless-Chicken-Thighs/123456789",
          product_name: "Freshness Guaranteed Boneless Skinless Chicken Thighs, 1.7–2.1 lb")
        → saved; next week this item opens directly.
Agent:  Next item…
```

At the end of the run: "12 added, 2 you picked from candidates, 3 were already
checked. Cart is ready for your review — I have not placed the order."

## Done when

Judge the run against the baseline you took in the readiness check, never
against zero — one match row already exists and it proves nothing about this
run.

1. **Match memory grew or was refreshed.** `WalmartMatch::count()` is higher
   than the baseline number, or — if the run only re-confirmed products that
   were already remembered — the newest `last_confirmed_at` is later than the
   baseline timestamp:

   ```bash
   "$HOME/Library/Application Support/Herd/bin/php" artisan tinker --execute='
     echo App\Models\WalmartMatch::count()," matches, newest confirmation ",
          App\Models\WalmartMatch::max("last_confirmed_at"),PHP_EOL;'
   ```

2. **The Walmart cart holds at least one item the run added**, visible on the
   cart page in the human's own browser.
3. **Nothing was bought.** No order confirmation page, no new order in the
   account's order history, no charge. If any of those exist, the run broke the
   first hard rule and the outcome is a failure regardless of the other two
   checks.
