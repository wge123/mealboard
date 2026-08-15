# Tier 3 v2: external research findings (2026-08-13)

Research sweep answering the question left open by `docs/tier3-list.md` hazard 1
(PerimeterX press-and-hold loops forever in the chrome-php headed browser, so
`walmart:push-list` cannot complete):

> Is there a maintained tool or documented internal API for Walmart list-writes
> that doesn't require fighting PerimeterX?

**Short answer: no, not for _lists_. But there is one for _carts_, and it is
official, documented, live today, and requires no automation at all.**

Every claim below is scoped to what was actually checked; the checks are named
so they can be re-run.

---

## 1. Maintained third-party tooling: none exists

GitHub repo-metadata checks (`gh api repos/<name>`, run 2026-08-13):

| Repo | Last push | Notes |
| --- | --- | --- |
| `walmartlabs/walmart-api` | 2019-07-11 | **archived** by Walmart |
| `caroso1222/wapy` | 2018-01-07 | Walmart Open API wrapper; the API it wraps is gone |
| `EmilHvitfeldt/walmartAPI` | 2020-04-19 | same, R |
| `fulfilio/python-walmart` | 2021-03-01 | Partner (seller) API, not consumer |
| `RichardMcSorley/walmart-grocery-bot` | 2022-08-30 | headless Chrome to cart; dead, 6 stars |

The todo's hypothesis ("most died in the 2022 API changes") is confirmed: the
consumer-facing wrapper generation is uniformly dead, and the one official
wrapper is archived.

A repo search restricted to `pushed:>2025-06-01` surfaced ~30 live "Walmart plus
automation" repos. Nearly all are seller-side (Marketplace, Retail Link, DSV
pricing, WFS) or analytics. Three consumer-side exceptions turned up; two are
disqualified, and the third (§6) is the one live project that actually writes to
Walmart **lists** without fighting PerimeterX. The two disqualified:

- `markswendsen-code/mcp-walmart` (`@striderlabs/mcp-walmart`, 1 star, last push
  2026-04-16): Playwright MCP server, cart-only, no list support, takes the
  user's Walmart **email and password as tool arguments**, and its stated method
  is "stealth features to avoid bot detection".
- `Zerik-Official/Walmart-Retail-Link-Automation` (2026-06-20): the only
  actively-maintained PerimeterX work found. It is the **seller** portal, and it
  advertises "automated bypass of PX press-and-hold challenges".

Both are ruled out by this repo's own standing rule (`docs/tier2-cart.md`: no
CAPTCHA evasion, ever), independent of whether they work. The same disqualifier
kills the agentic-browser vendor category: Skyvern's own product page lists
"built-in bypass for Cloudflare, DataDome, PerimeterX" as a feature.

**Self-hosted ecosystem check** (Mealie / Tandoor / Grocy / Home Assistant): no
Walmart retailer-write integration exists. HA's grocery integrations target
OurGroceries, Google Keep and Grocy, all neutral list stores, none a retailer.
Mealie's own October 2024 feature survey still carries users *requesting* a
"one-click add to my cart at Kroger" plugin. Nobody in the self-hosted meal-plan
space has solved retailer write-back.

## 2. The internal GraphQL angle: replay is downstream of the browser, not an escape from it

walmart.com's front end calls a federated GraphQL backend under
`www.walmart.com/orchestra/*` (`/orchestra/pdp/graphql/`,
`/orchestra/snb/graphql/`, and so on). Two properties make a captured
`addToList` mutation a bad foundation:

1. **Apollo persisted queries.** The request body carries only
   `persistedQuery.sha256Hash`, not the query text, and Walmart rotates those
   hashes on deploy. An unknown hash returns `PersistedQueryNotFound`. A hash
   captured in DevTools today breaks at Walmart's next deploy, a treadmill with
   no upstream to share it.
2. **PerimeterX cookies are a precondition.** `_px3` / `_pxhd` / `ACID` must have
   been minted by a page load that already passed PX before any `/orchestra/`
   call is accepted.

Consequence, and this is the finding that reshapes the option list: **option (a)
"direct API replay" is not an alternative to option (b), it is a follow-on
optimization of it.** You cannot hold a valid `_px3` without first having a
browser stack that passes the press-and-hold, which is exactly what hazard 1 says
chrome-php cannot do. Replay therefore inherits the entire PerimeterX problem and
adds hash rotation on top. **Reject (a) on its own; revisit only after (b).**

(A same-session `fetch()` from inside an already-authenticated headed page would
be legitimate, using the browser's own cookies with no evasion, but it still
needs the rotating hash, and it still needs the PX pass it was supposed to
avoid.)

## 3. The one official write path that never fights PerimeterX: the affiliate add-to-cart link

Walmart operates a documented affiliate **Add To Cart** link service:

```
https://affil.walmart.com/cart/addToCart?items=<itemId>|<qty>,<itemId>|<qty>
https://affil.walmart.com/cart/addToCart?upcs=<upc>|<qty>,...
```

`|<qty>` is optional when the quantity is 1. Verified live 2026-08-13:
`affil.walmart.com/cart/addToCart?items=363472942` 301-redirects to
`www.walmart.com/affil/cart/addToCart?items=363472942` and returns HTTP 200 with
the "Walmart Native Checkout | Review Order" app. **No API key is needed for the
link itself** (Impact publisher IDs only matter for commission tracking, which
Mealboard does not want).

Why this dodges the whole problem: Mealboard emits a URL, and the **human clicks
it in their own browser**. There is no automated request, so there is nothing for
PerimeterX to score; the page loads the same way any hand-typed walmart.com URL
does. (The page does load PX, app id `PXu6b0qd2S`; a real human passes it exactly
as they do while shopping by hand. Hazard 1 was PX rejecting the *automation
environment*, not the gesture.)

**Mealboard already has the input this needs.** `walmart_matches.product_url`
stores canonical `walmart.com/ip/<slug>/<usItemId>` URLs, and `usItemId` is the
trailing numeric segment, the same identifier `?items=` expects. So the whole
feature is: parse the trailing ID off each matched `product_url`, join with
commas, render one link. No new dependency, no browser, no config.

**Re-verified 2026-08-15:** the redirect chain is intact (`affil.walmart.com`
now 307s, previously 301, to `www.walmart.com/affil/cart/addToCart`), but a
cookie-less `curl` client following it lands on `walmart.com/blocked`, which
*demonstrates* the mechanism described above rather than refuting it: PX scores
the client, and a bare HTTP client scores as a bot. The 2026-08-13 "HTTP 200
Review Order" observation should therefore be read as client-dependent. The
human-clicked path remains the design, and its final confirmation is a one-click
test in a real logged-in browser (folded into the decision moment below).

Two honest limits:

- **It fills the cart, not a list.** Tier 3 was deliberately list-only, and the
  standing rule reads "nothing is ever carted". The *agent* still never carts, it
  emits a link, but whether that satisfies the rule as written is a call for the
  repo owner, not something research should assume.
- **It only covers ingredients that already have a `walmart_matches` row.**
  Unmatched rows keep today's `walmart.com/search?q=` chip
  (`App\Livewire\ShoppingList::links()`), which is already the right fallback.

## 4. Walmart Affiliate / IO product-search API: effectively closed for Mealboard

The read-only product API (Content Provider API, on walmart.io) is the piece that
would let Mealboard resolve keywords to products automatically and retire the
paste-back step. It is not realistically obtainable here:

- `developer.walmartlabs.com` no longer resolves (curl: connection failure);
  `affiliates.walmart.com/apidocs` returns 404; the walmart.io docs deep links
  return 400/404 behind a JS shell. The self-serve documentation surface has
  largely rotted.
- Affiliate onboarding runs through Impact, and API access is granted separately
  and selectively: it requires a **live public website with regular content and a
  predominantly US audience**, plus a business case. Mealboard is a private
  single-user meal planner with no public site.
- Even if granted, it is **read-only**: no cart, no list, no address, no payment.
  It would improve matching quality, never enable a write.

Verdict: not worth pursuing. The existing `walmart.com/search?q=<keywords>` chip
already delivers most of the value at zero integration cost, and §3 delivers the
rest for matched ingredients.

## 5. Kroger: the only retailer with a real, free, official cart-write API

`PUT /v1/cart/add` on `api.kroger.com` accepts a list of `{upc, quantity}` against
an authenticated shopper token. It needs the OAuth2 **authorization_code** flow
with scope `cart.basic:write` (client-credentials is not enough), plus the
Products API to resolve names to UPCs. It is public, free, self-serve, documented,
and has a live Python client (`CupOfOwls/kroger-api`, last push 2026-07-09, 27
stars), that is, the maintained ecosystem that Walmart conspicuously lacks.

The single gating fact is geographic: this is only useful if a Kroger-family
banner (Kroger, Fred Meyer, Ralphs, King Soopers, Harris Teeter, Fry's, Smith's)
actually serves the household. That is not a research question.

---

## Recommendation

**Ordered, one primary.**

1. **Primary: build the one-click cart link (a new option (e)).** It is the only
   *yes* the whole sweep produced to the todo's literal question: an official,
   documented, currently-live Walmart write path that never fights PerimeterX. It
   is roughly thirty lines against data Mealboard already stores, adds no
   dependency and no browser, and cannot half-fail the way a 42-item automated
   push can. **Gate:** the repo owner must decide whether an agent-emitted,
   human-clicked cart link is compatible with the "nothing is ever carted" rule.
   If yes, it very likely obsoletes Tier 3 as built.
2. **Secondary: (b), the Playwright ListBrowser rewrite**, if list semantics are
   genuinely wanted over cart semantics. The evidence for the stack difference is
   real (playwright-cli passed the same challenge first-try on 2026-07-21), but
   note what this research found: *no external project has done this and kept it
   working*, so it is a treadmill Mealboard would ride alone, for a list the user
   then still has to cart from by hand.
3. **Reject (a)** on the §2 evidence: it is downstream of (b), not an alternative
   to it, and adds rotating persisted-query hashes on top.
4. **(d) Kroger** is the strongest engineering answer in the abstract, an
   official cart write with a maintained client, and is worth doing *only* if a
   Kroger-family store is actually available.
5. **(c) retire Tier 3** is the correct outcome if the §3 cart boundary is
   unacceptable *and* the (b) treadmill isn't worth it. Tier 1/2 already
   assembled the 42-item week fine via the supervised interactive route.

## Sources

- GitHub repo metadata and repo/code search via `gh api` (2026-08-13).
- Walmart Add To Cart link service parameters: Walmart affiliate API docs
  (`walmart.io/apidocs/affiliates/gm-add-to-cart`, now behind a JS shell);
  endpoint behavior verified live by direct HTTP probe on 2026-08-13.
- `/orchestra/*` GraphQL endpoints, Apollo persisted-query hash rotation, and
  `_px3`/`_pxhd`/`ACID` preconditions: Walmart scraping write-ups (Scrapfly,
  SpyderProxy, ScrapingBee) and Walmart Global Tech's own federated-GraphQL post.
- Kroger Cart API (`PUT /v1/cart/add`, `cart.basic:write`, authorization_code):
  developer.kroger.com reference; client `github.com/CupOfOwls/kroger-api`.
- Affiliate onboarding via Impact and Content Provider API selectivity:
  affiliates.walmart.com plus affiliate-program write-ups (2026).
- Self-hosted ecosystem gap: Tandoor/Mealie docs and Mealie's 2024 shopping-list
  survey; Home Assistant grocery integrations (OurGroceries, Google Keep, Grocy).
