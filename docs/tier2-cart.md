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
3. **Mealboard MCP server registered** with Claude Code (see README →
   *MCP server*):

   ```bash
   claude mcp add mealboard -- php /Users/willem/Developer/Personal/mealboard/artisan mcp:serve
   ```

4. **A locked week.** `get_current_shopping_list` serves the latest *locked*
   week; lock the plan in Mealboard first.
5. **The human is present.** This is a supervised run. If the human steps away,
   the agent pauses.

## Readiness check (run before the human sits down)

One command proves prerequisites 3 and 4 at once — the server starts and a
locked week exists — so a supervised run never dies on its first tool call:

```bash
cd /Users/willem/Developer/Personal/mealboard
printf '%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' \
  '{"jsonrpc":"2.0","method":"notifications/initialized"}' \
  '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"get_current_shopping_list","arguments":{}}}' \
  | php artisan mcp:serve | tail -1
```

Expect a JSON-RPC result whose text payload starts with a `week_start_date`.
`No locked week.` means prerequisite 4 is unmet — lock the plan in Mealboard
and re-run. If `php` is not found (Herd puts it on PATH only in an interactive
shell), use the absolute binary:
`"$HOME/Library/Application Support/Herd/bin/php" artisan mcp:serve`.

## The loop

1. Call `get_current_shopping_list`. It returns the locked week's items grouped
   by store category; each item carries:
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
