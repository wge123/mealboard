# Tier 3 v2: external research findings (2026-08-13, §6 added 2026-08-21, §7 added 2026-08-23)

Research sweep answering the question left open by `docs/tier3-list.md` hazard 1
(PerimeterX press-and-hold loops forever in the chrome-php headed browser, so
`walmart:push-list` cannot complete):

> Is there a maintained tool or documented internal API for Walmart list-writes
> that doesn't require fighting PerimeterX?

**Short answer: no maintained tool and no usable internal API for _lists_. But
there is an official write path for _carts_ that requires no automation at all
(§3), and there is one unmaintained but instructive reference implementation that
does write _lists_ without evasion (§6). Since 2026-08-23 that posture also has
a maintained substrate to build on (§7).**

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
disqualified, and the third (§6) is the only project found that writes to Walmart
**lists** without fighting PerimeterX (though "live" overstates it, as §6
records). The two disqualified:

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

## 6. The browser-extension list-writer: the one prior art that writes lists without evasion

`esabisch/grocery-bridge` (MIT, verified against the GitHub API 2026-08-21) is a
Chrome MV3 extension that syncs a Todoist grocery list into a Walmart **list**.
It is the third consumer-side exception §1 referred to, and it matters because it
is the only project found that does list-writes while satisfying this repo's
"no CAPTCHA evasion, ever" rule.

**Why it never fights PerimeterX.** It is not an automation environment. The
extension runs inside the user's own already-signed-in Chrome and drives ordinary
DOM clicks (find the "Add to list" button, open the dialog, match the list by
name, click "Done", confirm via the success toast). There is no headless browser,
no driver flag, no stealth patching, and no `/orchestra/` replay. Hazard 1 was PX
rejecting the *automation environment*; this design simply doesn't present one.

**It stops at the challenge rather than solving it.** The whole of its
challenge handling is a predicate:

```js
function detectChallenge() {
  if (/\/blocked(\?|$)/.test(location.pathname)) return true;
  if (/robot or human|are you a robot/i.test(document.title || "")) return true;
  const txt = (document.body?.innerText || "").slice(0, 2000).toLowerCase();
  return /press\s*and\s*hold|are you a robot|verify you are human|access denied/.test(txt);
}
```

It returns a boolean and the run pauses for the human to press-and-hold, then
resumes. Nothing in the repo attempts to hold, spoof, or bypass the gesture. That
is the opposite posture from the two projects §1 disqualified, and it is
compatible with this repo's standing rule.

**Its own history is evidence about option (b).** The commit log is five commits
over 2026-05-08 to 2026-05-10, and one of them is `cfdde4d` *"feat: scaffold MV3
extension, retire Playwright path"*, immediately after `d143a59` *"snapshot:
pre-pivot Playwright scaffold"*. An independent developer built the Playwright
version first and then abandoned it for the in-browser extension. That is one
data point, not a proof, but it points the same direction as §1's finding that no
external Playwright-based Walmart list-writer has stayed working.

**Honest limits, and they are large:**

- **It is not maintained.** 5 commits, one author, all within three days, last
  push 2026-05-10, 0 stars, 0 forks. The doc's headline ("no *maintained* tool
  for lists") survives intact; this is a reference implementation, not a
  dependency. Its `lib/walmart-dom.js` is 11.9 KB of Walmart DOM selectors, which
  is exactly the brittle surface that rots on Walmart's next front-end deploy.
- **Wrong source system.** It reads Todoist, not Mealboard. Only the Walmart half
  is reusable.
- **A Chrome extension is a new artifact class for this repo**, with its own
  install and upkeep story, and it only runs when that browser is open.

**What it changes for Mealboard.** It establishes that a list-write path exists
which is both PerimeterX-free and evasion-free, so the choice is no longer
"cart link or nothing" for list semantics. Call it **option (f): a Mealboard
browser extension that walks matched `walmart_matches` rows and adds each to a
named Walmart list, pausing for the human on challenge.**

## 7. OpenTabs: option (f) now has a maintained substrate (2026-08-23)

`opentabs-dev/opentabs` (MIT, 912 stars, last push 2026-08-22, checked via the
GitHub API 2026-08-23) is a Chrome extension plus a local MCP/CLI bridge whose
whole premise is §6's posture, generalised: plugins call a site's own APIs
**from inside the user's already-authenticated tab** (`fetchFromPage`), rather
than driving a browser from outside it. It ships ~100 plugins, and one of them is
Walmart.

**What the Walmart plugin has today** (`plugins/walmart`, 10 tools):
`search_products`, `get_product`, `get_product_reviews`, `get_store`,
`get_cart`, `list_orders`, `get_current_user`, plus `navigate_to_product` /
`navigate_to_search` / `navigate_to_checkout`. Auth is nothing but the user's
`customer` / `hasCID` cookies read in-page. **There is no list tool and no write
tool** — the framework is what is on offer here, not a ready-made list writer.

**Why this matters to option (f).** (f) as written in §6 means Mealboard owns a
new artifact class: an MV3 extension, its install story, and ~12 KB of Walmart
DOM selectors. On this substrate Mealboard instead owns **one plugin tool**
(`add_to_list`), and the extension, the browser bridge, the MCP surface, the
permission model and the install path are someone else's maintained code.
OpenTabs also ships network capture as a built-in browser tool, which is exactly
the instrument needed to obtain — and later re-obtain — the persisted-query hash
§2 warns about.

**This refines §2's dismissal of the same-session `fetch()`.** That parenthetical
gave two objections; only one survives:

- *"It still needs the PX pass it was supposed to avoid."* — Not in this shape.
  The call originates in the user's ordinary browsing session, so there is no
  automation environment for PX to score. That is the same argument §6 makes for
  DOM clicks, and it applies unchanged to an in-page `fetch()`.
- *"It still needs the rotating hash."* — Correct, and unchanged. The trade
  against §6 is therefore narrow: **one rotating persisted hash instead of ~12 KB
  of DOM selectors.** Both are rot surfaces. Which rots slower is untested here
  and should not be asserted; the hash has the advantage of being re-capturable
  automatically from the tab's own traffic, and of failing loudly
  (`PersistedQueryNotFound`) rather than silently clicking the wrong element.

**The list mutation is still uncaptured.** Code search on 2026-08-23 for
`/orchestra/lists/graphql`, `walmart addToList mutation` and
`orchestra graphql walmart list` returned **0 hits**, so no one has published it.
Step one of this path is unchanged from §2: add one item to a Mealboard list by
hand with network capture running, and read the operation name, hash and
variables off that request.

**New corroboration for §2's hash treadmill, and a project to *not* depend on.**
`teebs4140/walmart-pp-cli` (Go, last push 2026-07-11) is an agent-native Walmart
grocery CLI that hard-codes four persisted operations with explicit
`LastValidated: "2026-07-11"` fields — `Search` (service `snb`), `ItemById`
(`pdp`), `getCart` and `updateItems` (both `home`), addressed as
`/orchestra/<service>/graphql/<Op>/<sha256>`. That is the treadmill as a shipped
artifact rather than an inference, and its header set (`Origin`, per-operation
`Referer`, `Sec-CH-UA*`, `Traceparent`, `Baggage`, `WM-Client-TraceId`) documents
what a request must carry. **It is disqualified as a dependency on the same rule
that killed the two projects in §1**: its transport is `github.com/enetx/surf`
over `refraction-networking/utls`, i.e. Chrome TLS/JA3 impersonation, which is
fingerprint evasion. Take its endpoint map as documentation; never its transport.
Its very existence is also the cleanest illustration of the §7 thesis — the only
reason it needs uTLS at all is that it calls from outside the browser.

**Cost and caveats of adopting OpenTabs**: a third-party extension with access to
the user's logged-in sessions is a real trust decision. Its posture is
defensible (every tool off until explicitly enabled, per-tool Ask/Auto
permissions, permissions reset on version change, local-only with an audit log,
source review encouraged before enabling), but it is still third-party code in
the browser where the user banks. Pin the version, enable only the Walmart tools,
and keep the Mealboard tool in a locally-installed plugin rather than a published
one.

**Search refresh.** Re-running the §1 sweep on 2026-08-23
(`gh search repos "walmart grocery"` sorted by last update, `gh search repos
"walmart list"`, and the three code searches above) surfaced nothing that writes
to a Walmart list. The headline holds ten days on.

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
2. **If and only if that gate closes "no", build (f), the browser extension**
   (§6). This is the fallback that the 2026-08-13 sweep missed, and it is a
   better one than (b): it preserves genuine *list* semantics, so it satisfies
   "nothing is ever carted" as written and needs no reinterpretation of the rule.
   It never fights PerimeterX, it pauses for the human instead of evading, and
   `esabisch/grocery-bridge` is an MIT reference implementation of exactly this
   against exactly Walmart. Cost: a new artifact class (a Chrome extension) and
   ownership of a brittle DOM-selector layer. **Build it on OpenTabs (§7) rather
   than from scratch** unless running a third-party extension with session access
   is itself unacceptable: that reduces Mealboard's share to a single
   `add_to_list` plugin tool, and swaps the DOM-selector layer for one
   re-capturable persisted-query hash.
3. **(b), the Playwright ListBrowser rewrite, is now third and probably dead.**
   The evidence for the stack difference is real (playwright-cli passed the same
   challenge first-try on 2026-07-21), but two findings undercut it: no external
   project has done this and kept it working, and the one developer found who
   tried it for Walmart lists explicitly retired his Playwright path for an
   in-browser extension (§6). If list semantics are the goal, (f) reaches them
   without the treadmill, for a list the user still has to cart from by hand
   either way.
4. **Reject (a)** on the §2 evidence: it is downstream of (b), not an alternative
   to it, and adds rotating persisted-query hashes on top.
5. **(d) Kroger** is the strongest engineering answer in the abstract, an
   official cart write with a maintained client, and is worth doing *only* if a
   Kroger-family store is actually available.
6. **(c) retire Tier 3** is now the weakest of the live options rather than a
   likely landing spot. It was the right call while "cart link or Playwright
   treadmill" were the only choices; with (f) on the table there is a list-write
   path that breaks neither the carting rule nor the evasion rule. Retire only if
   neither (e) nor (f) is wanted. Tier 1/2 already assembled the 42-item week
   fine via the supervised interactive route.

## Sources

- GitHub repo metadata and repo/code search via `gh api` (2026-08-13; §7 sweep
  re-run 2026-08-23).
- §7 `opentabs-dev/opentabs`: repo metadata, `plugins/walmart/README.md`,
  `plugins/walmart/src/tools/` listing and `walmart-api.ts` read from the public
  GitHub API on 2026-08-23. §7 `teebs4140/walmart-pp-cli`: `go.mod` and
  `internal/walmart/{operations,headers,cookies,api}.go` read the same way.
- §6 `esabisch/grocery-bridge`: repo metadata, commit list, file tree, and the
  verbatim `lib/walmart-dom.js` / `manifest.json` contents read from the public
  GitHub API and `raw.githubusercontent.com` on 2026-08-21. The
  no-evasion and retired-Playwright claims are from that source read, not from
  the project's README alone.
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
