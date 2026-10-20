# Recipe sites: JSON-LD, access rules and new-recipe feeds

Researched 2026-10-20 from the Mac this app runs on, for ADR 0003 (a model may only restructure a human-written source) and the later daily web discovery lane.

Tags used below:

- **[M]** measured by me with curl against the live site. The capture path is in brackets.
- **[S]** stated by a primary source (the site's own robots.txt or terms, schema.org, Google Search Central, Web Data Commons). The capture path is in brackets.
- **[I]** my inference. Treat it as a hypothesis.

All captures sit under `docs/research/recipe-site-json-ld/` (written as `cap/` below; `cap/pages-summary.json` is the per-page analysis, `cap/STATUS-MATRIX.tsv` every status line). Sample: 20 sites, 3 recipe pages each (60 pages), picked from each site's own newest sitemap or feed entries, not hand-picked for quality. Terms excerpts are mechanical keyword extractions, not whole documents, and this is not legal advice.

## Short answers

**1. Which sites publish Recipe JSON-LD, and how complete is it?** 17 of 20 sites did. 51 of the 54 pages I could fetch carried a Recipe node (94%) [M: `cap/pages-summary.json`]. Every one of those 51 had `recipeIngredient` as a list of plain strings, non-empty `recipeInstructions`, and a yield. No block failed to parse as JSON. The one reachable site with none is Smitten Kitchen, which only has Jetpack/hRecipe HTML markup (class `hrecipe h-recipe jetpack-recipe` in the page HTML; header captures in `cap/pages/smittenkitchen/`). Two sites (Food Network, Woks of Life) could not be fetched at all, so their JSON-LD is unknown.

Shapes seen [M]: `@graph` 24 pages (Yoast/WPRM blogs and King Arthur), single object 15, top-level array 12. Instructions: `HowToStep` list 43, `HowToSection` 3, mixed section+step 2, plain string list 3 (King Arthur). The things a parser must tolerate:

- `@type` is sometimes an array: `["Recipe"]` (Allrecipes) and `["Recipe","NewsArticle"]` (Serious Eats, Simply Recipes). A check for `@type === "Recipe"` misses 9 of my 60 pages.
- Love and Lemons writes `type=application/ld+json` with no quotes. My first regex missed it.
- HTML entities are inside the strings: `&#39;`, `&amp;`, `&#8217;`, `&#039;` (Budget Bytes, Just One Cookbook, Love and Lemons, Pinch of Yum, RecipeTin Eats). Decode before the line-for-line check.
- Times are not always ISO 8601. Bon Appétit and Epicurious give `"50 minutes"` and no prep or cook time (6 of 6 pages). RecipeTin Eats omits `totalTime` on 2 of 3. Delish sets `cookTime` to `PT0S`. Taste of Home pads (`PT03H50M`). 9 of 51 pages had no prepTime and 10 no cookTime.
- `recipeYield` is a string (`"4 servings"`), a number, or a list (`["8","8 servings"]`), and once free text (`"Makes 4-6 (depending on your waffle maker)"`).
- Some ingredient lines legitimately have no amount ("Kosher salt", "maple syrup", Minimalist Baker's "Pasta"). Minimalist Baker's page data itself has an empty amount for that line, so the JSON-LD is faithful to the page [M: `cap/pages/minimalistbaker/p2.recipe-ldjson.json`].
- WPRM leaves `((…))` markers in ingredient text (Minimalist Baker, Just One Cookbook) and Budget Bytes puts a price in some lines ("$2.57*").
- I saw exactly one Recipe node per page (0 of 51 with more than one), so "multiple Recipe nodes" did not occur in my sample. Roundup pages would be a different case [I].

Google only requires `name` and `image`; `recipeIngredient` and `recipeInstructions` are "recommended" [S: `cap/spec/google-recipe.excerpt.txt`]. schema.org allows ingredients as free text or structured values and instructions as `ItemList`, `HowToStep`, `HowToSection` or `Text` [S: `cap/spec/schemaorg-recipe.excerpt.txt`]. So a valid Recipe node can lack ingredients or steps, and the app must refuse that itself.

**2. Robots, terms and plain fetch.**

- *Robots.* On all 19 sites whose robots.txt I could read, the `User-agent: *` group does not block recipe pages [S/M: `cap/robots/`; Food Network's robots.txt returned 403]. Woks of Life disallows `/wp-json/`; Bon Appétit and Epicurious disallow URLs with a query string. AI crawler blocks are common: People Inc (Allrecipes, Serious Eats, Simply Recipes) blocks ClaudeBot, anthropic-ai, Claude-User/-Web/-SearchBot, CCBot, PerplexityBot; NYT blocks those plus GPTBot, ChatGPT-User and OAI-SearchBot; Condé Nast blocks the Claude family and not GPTBot; Budget Bytes, BBC Good Food and several blogs block some. No file names `GuzzleHttp`, `curl` or `Mealboard`, so those fall under `*` (checked with rg over all 20 files).
- *Terms.* Explicit anti-scraping or anti-automation language: People Inc, NYT, Condé Nast, Warner Bros. Discovery (Food Network), Hearst (Delish), King Arthur, Budget Bytes, Pinch of Yum. The People Inc, NYT, Condé Nast, Hearst and Pinch of Yum texts also forbid feeding content to AI or LLM systems, and People Inc and Condé Nast name retrieval-augmented generation. Restructuring with a model is that kind of use [I]. The other sites have copying or republishing limits but no scraping clause I could find, or no terms page I could read (see table).
- *Plain GET.* With a Guzzle default User-Agent (`GuzzleHttp/7`, which is what Laravel's `Http` sends in this repo: `vendor/guzzlehttp/guzzle/src/Utils.php` line 251) 16 of 20 sites return a page with Recipe JSON-LD. The four that do not: Food Network (Akamai 403), Woks of Life (Cloudflare challenge), Pinch of Yum (403 from CloudFront) and Smitten Kitchen (200 but no JSON-LD) [M: `cap/STATUS-MATRIX.tsv`]. Behaviour depends on the User-Agent, in both directions: Budget Bytes and Cookie and Kate challenge a Chrome-style UA and the app's existing `Mozilla/5.0 … Mealboard/1.0` UA, but serve default curl and Guzzle. Pinch of Yum does the reverse. King Arthur gives default curl a 406. No site served a paywall to a plain GET: NYT returned the full ingredients and steps in JSON-LD on all three pages even though the same JSON-LD says `isAccessibleForFree:false` [M]. I tested three NYT recipes, all old (ids 1012xxx), so a newer one may differ [I].

**3. Spotting new recipes with no search API.** There is no single method that works everywhere.

- **RSS** works on every WordPress blog (10 items, or 4 to 8 on slow ones) and on Delish (50), Bon Appétit (30), Epicurious (30, all recipes), Taste of Home (30). It fails on Allrecipes, Serious Eats, King Arthur, NYT and Food Network (404 or 403 at the URLs I tried). Simply Recipes' `/feed/` returns a sitemap, not a feed.
- **Sitemaps** exist on 18 of 20 (Food Network's and Just One Cookbook's returned 403). Every sitemap file I read had `<lastmod>` (BBC Good Food's index lacks it, its child files have it), but that is the *modified* time: Budget Bytes' newest post had `modified` five days before its publish `date` in the REST API. So a new recipe is found by diffing the URL set against what is already known, not by trusting lastmod [M/I]. Big publishers give one big sitemap (Allrecipes 4 × 20,000 URLs; Serious Eats 14,762; Simply Recipes 10,252); BBC Good Food gives a small quarterly recipe sitemap (44 recipe URLs so far in Q4); Bon Appétit and Epicurious give monthly ones.
- **WordPress REST** (`/wp-json/wp/v2/posts?_fields=…`) answered on 8 of 9 blogs and returns `date`, `modified`, `link`. Just One Cookbook returns 403. `/wp-json/wp/v2/wprm_recipe` answered on 5 (it lists recipe cards only). Woks of Life's robots.txt disallows `/wp-json/`, so it is not usable there.
- **Most reliable per site:** WP blogs: the RSS feed polled daily (cheap, items link recipes), with the sitemap diff as a backstop. Delish, Bon Appétit, Epicurious, Taste of Home: the RSS feed, filtered by URL pattern (7 of 30 Bon Appétit items, 11 of 30 Taste of Home items, 16 to 19 of 50 Delish items are recipes). Allrecipes, Serious Eats, Simply Recipes, BBC Good Food, King Arthur, NYT: sitemap diff only.

**4. How often is JSON-LD missing or broken?** In my sample: 3 of 54 fetched pages had none (all Smitten Kitchen), 0 of 51 had invalid JSON or empty instructions or ingredients, 6 of 60 page requests were blocked outright (Food Network 3, Woks of Life 3), and Budget Bytes and Cookie and Kate would add 6 more with a Chrome-style UA [M]. Only 3 pages per site, one day, so these are rough. Web Data Commons counts Recipe entities but publishes no completeness or validity rates: October 2024 corpus has 2,922,378 Recipe entities on 2,746,545 URLs across 37,304 hosts; the October 2023 corpus had 5,683,640 on 4,489,240 URLs across 42,727 hosts [S: `cap/spec/webdatacommons-recipe.excerpt.txt`]. I found no primary dataset for "how often is it broken".

## Per-site table

"UA test" is the HTTP status for four User-Agents on the first recipe page: browser-style (Chrome string), default curl, `GuzzleHttp/7`, and the app's existing `Mozilla/5.0 … Mealboard/1.0`, in that order [M: `cap/STATUS-MATRIX.tsv`, `cap/pages/<site>/`]. "AI blocks" lists the AI crawlers disallowed from the whole site in robots.txt [S: `cap/robots/<site>.robots.txt`]. Feeds are in `cap/feeds/`, sitemaps in `cap/sitemaps/`, terms in `cap/tos/`, JSON-LD in `cap/pages/<site>/pN.recipe-ldjson.json`.

| Site | JSON-LD (3 pages) | Shape / steps / times | Robots (`*` on recipes; AI blocks) | Terms on automation | UA test | New-recipe source |
|---|---|---|---|---|---|---|
| Allrecipes | 3/3 | top-level array `["Recipe"]`; HowToStep; ISO times; yield `["8","8 servings"]` | allowed; blocks ClaudeBot, anthropic-ai, Claude-*, CCBot, Perplexity; GPTBot only barred from `/thmb/`. robots.txt comment bans AI/RAG use | People Inc: "shall not use any … spiders, robots, scrapers, crawlers … to 'scrape,' harvest, or download data"; no AI training use | 200 200 200 200 | no RSS (`/feeds/` 404). Sitemap: 4 × 20,000 URLs with lastmod |
| Serious Eats | 3/3 | array `["Recipe","NewsArticle"]`; HowToStep; ISO | as Allrecipes | People Inc (same text) | 200 ×4 | no feed at `/rss` (404). Sitemap 14,762 URLs, lastmod |
| Simply Recipes | 3/3 | array `["Recipe","NewsArticle"]`; HowToStep; ISO | as Allrecipes | People Inc | 200 ×4 | `/feed/` is a sitemap, not RSS. Sitemap 10,252 URLs, lastmod |
| NYT Cooking | 3/3 | object (4 blocks); HowToStep; totalTime ISO, prep/cook mostly null; yield "10 to 12 servings" | allowed (`/search`, `/recipe-box*`, `/guides/` barred); blocks GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, anthropic-ai, Claude-*, CCBot, Perplexity | "robots, spiders, scripts … designed to data mine or scrape the Content"; also AI training/grounding barred; "failing to abide by … the Robots Exclusion Protocol" | 200 ×4; full recipe in JSON-LD despite `isAccessibleForFree:false` | no feed (`/feeds/` 404). Sitemap index: 536 monthly files with lastmod |
| BBC Good Food | 3/3 | object; HowToStep; ISO; yield int or free text | allowed; blocks GPTBot, CCBot, Bytespider, Meta-ExternalAgent | Immediate Media: no scraping or robot wording found by keyword scan; "not copy, store in any medium … re-publish … any part of our Services without our prior written permission" | 200 ×4 | RSS has 10 items, 3 of them under `/recipes/`. Quarterly recipe sitemap (`2026-Q4-recipe.xml`, 78 URLs, lastmod) is better |
| Bon Appétit | 3/3 | object; HowToStep; times as "50 minutes" (non-ISO), no prep | allowed (query-string URLs barred); blocks Claude family, CCBot, Perplexity, Google-Extended | Condé Nast: "copy, harvest, crawl, index, scrape, spider, mine … any Content … other than indexing Content for inclusion in a Search Engine"; AI/RAG barred | 200 ×4 | RSS `/feed/rss`, 30 items, 7 recipes. Monthly sitemap 86 URLs (27 recipes) |
| Epicurious | 3/3 | as Bon Appétit (the three samples are syndicated BA recipes) | as Bon Appétit | Condé Nast | 200 ×4 | RSS `/feed/rss`, 30 items, all recipes. Monthly sitemap 18 URLs |
| Food Network | unknown, blocked | n/a | robots.txt itself returns 403 (Akamai "Access Denied") | WBD visitor agreement: "use an automatic device (such as a robot or spider) … to copy or 'scrape' the Website or Website Content for any purpose without the express written permission" (I did not confirm from foodnetwork.com that this text governs it, as that site was blocked) | 403 ×4 | feed and sitemap 403 |
| Delish | 3/3 | array; HowToStep; ISO but `cookTime PT0S`; yield "6 - 8 serving(s)" | allowed (`/search`, `/preview/` barred); no AI block in robots (GPTBot/OAI barred only from locale paths) | Hearst: "may also not use any software robots, spider, crawlers, or other data gathering or extraction tools … to access, acquire, copy, monitor, scrape or aggregate"; AI/RAG barred | 200 ×4 | RSS `/rss/all.xml`, 50 items, about 16 to 19 recipes. Sitemaps are `.xml.gz` |
| Taste of Home | 3/3 | object; HowToStep; ISO (zero-padded); "6 servings" | allowed (query-string pages barred); no AI blocks | Trusted Media Brands: content is copyrighted; permission needed for any use beyond personal copy; no scraping wording found by scan | 200 ×4 | RSS 30 items (11 recipes, 13 articles, 5 collections). 18 recipe sitemaps; the one I read (no. 18) had only 2024 lastmods, so where new ones land is unclear |
| King Arthur | 3/3 | `@graph`; **instructions are plain strings**; ISO | allowed (`/recipes/print` barred); no AI blocks | "use any means to scrape or crawl any web pages contained in the Website" | 200, **406**, 200, 200 | no feed (`/blog/rss` 404). Sitemap pages 1 to 4, about 5,600 URLs; many share one lastmod, so lastmod is not a publish date |
| Budget Bytes | 3/3 | `@graph`; HowToStep/Section; ISO; ingredient lines may carry price and `&amp;` | allowed (search/login barred); blocks ClaudeBot, anthropic-ai, GPTBot, CCBot, Amazonbot, Perplexity, others | "“scrape,” “crawl” or “spider” any web pages or other services contained in the Services" | **403 (Cloudflare challenge)**, 200, 200, **403** | RSS 10 items, full content (780 KB). WP REST posts and wprm_recipe both 200. Yoast sitemaps |
| RecipeTin Eats | 3/3 | `@graph`; HowToStep/Section; `totalTime` missing on 2 of 3; entities | open (`Disallow:` empty); blocks anthropic-ai, CCBot, Claude-Web | no ToS page found. "Policy: use of recipes & images": "I do not give permission to share my content where the ingredients or recipe directions are provided, except in very limited circumstances" | 200 ×4 | RSS only 4 items. WP REST 200 both. Yoast sitemaps |
| Cookie and Kate | 3/3 | `@graph`; HowToStep; ISO | allowed; blocks anthropic-ai, CCBot, Claude-Web, GPTBot, Google-Extended | terms page covers comments only; recipe policy: "my recipes are copyrighted material and may not be published elsewhere". No scraping clause | **403 (challenge)**, 200, 200, **403** | RSS 10 items but ordered Oct 14 back to Jul 13 and links carry `?adt_ei=*|EMAIL|*`. WP REST posts 200; wprm 404 |
| Love and Lemons | 3/3 | `@graph` (Yoast); HowToStep; ISO; **`type=` attribute unquoted** | open; no AI blocks | no terms page found; privacy policy only, nothing on scraping | 200 ×4 | RSS 10 items (incl. cookbook promos). WP REST 200 both. Sitemap lastmod |
| Smitten Kitchen | **0/3** (Jetpack hRecipe HTML only) | none | open; no AI blocks | no terms page found; FAQ is about copyright ("Reprinting a recipe word for word? A copyright violation.") | 200 ×4 | RSS 10 items, slow (May to Sep). Sitemap 1,001 URLs, lastmod. WP REST posts 200 |
| Pinch of Yum | 3/3 | `@graph`; HowToStep; ISO; entities | open; no AI blocks in robots | "must not conduct any systematic or automated data collection activities, including … scraping, data mining, data extraction, or data harvesting"; AI use barred | 200, **403**, **403**, 200 | RSS 6 items, full content. WP REST posts 200; wprm 404 |
| Minimalist Baker | 3/3 | `@graph`; HowToStep; ISO; `((…))` markers | no `*` rules; blocks anthropic-ai, CCBot, Claude-Web, Google-Extended, GPTBot | terms page unreadable: Cloudflare 403 even for default curl | 200 ×4 (pages) | RSS only 4 items. WP REST 200 both |
| Just One Cookbook | 3/3 | `@graph`; HowToSection (+Step); ISO; entities | open; no AI blocks | "Do not copy and paste the recipe in full. Copying and pasting our instructions verbatim is not permitted." No scraping clause found | 200 ×4 (but sitemap and wp-json 403) | RSS 8 items (mixed). Sitemap and REST blocked |
| Woks of Life | unknown, blocked | n/a | `/wp-json/` barred; blocks anthropic-ai, CCBot, Claude-Web, GPTBot, Google-Extended | terms page blocked (403) | **403 ×4 (Cloudflare challenge)** | RSS 10 items, sitemap index 200 |

## What the findings mean for the app

### "Refuse pages without usable JSON-LD, paste text instead" mostly holds, with three changes

It holds for the data: 94% of fetchable pages had complete Recipe JSON-LD, and the failure (Smitten Kitchen) is a known popular blog with no structured data at all, which is exactly the case ADR 0003 sends to paste. Nothing in my sample needed a model to fill a gap.

It needs three changes:

1. **Two different refusals.** "Could not fetch the page" (Food Network, Woks of Life, and UA-dependent blocks on Budget Bytes, Cookie and Kate, Pinch of Yum, King Arthur) is not "this page has no recipe". 6 of my 60 page requests were blocked outright (12 of 60 with a Chrome-style UA). The household should be told to paste text or an exported page, and not that the recipe is bad.
2. **A tolerant extractor.** Find the node by `@type` containing `Recipe` (string or array), look inside `@graph`, top-level arrays and several `ld+json` blocks, accept an unquoted `type=` attribute, accept string-list, `HowToStep` and `HowToSection` instructions, and decode HTML entities before the line-for-line comparison.
3. **Refuse on emptiness, not on validity.** Google does not require ingredients or steps, so refuse when either is empty. Amount-less lines ("salt", "pasta") exist on the page itself and should stay blank, as the glossary says.

A recipe's own times will often be unusable (Bon Appétit and Epicurious are not ISO; RecipeTin Eats lacks a total), which affects the weekday-limit check in discovery. Discovery cannot estimate a time (ADR 0003), so it should skip those pages and not guess [I].

### Shortlist for the daily discovery lane

Ranking uses three things: technically clean (JSON-LD, a working feed, no bot wall on a Guzzle-style fetch), no explicit anti-scraping clause I could find, and fit for weekday dinners. None of the 20 sites grants permission for automated reading. Whether a private per-household library copy of a recipe's ingredients and steps is acceptable is a policy and legal call for the household, not a technical one. I am flagging, not deciding.

Cleanest on all three, in order:

1. **Love and Lemons**: open robots, no terms page found, Yoast `@graph`, 10-item feed plus WP REST. Vegetarian, so it fits only part of the household's taste [I].
2. **Just One Cookbook**: pages and JSON-LD fine, short recipes (all 3 sample pages were 30 min or less and 10 ingredients or fewer), RSS works. Sitemap and REST are blocked. Terms forbid copying instructions verbatim.
3. **RecipeTin Eats**: open robots, WP REST and sitemap work. The feed has only 4 items. Its recipe policy says it does not allow sharing ingredients and directions. Recipes run 18 to 21 ingredients in my sample, so few will pass the 10-ingredient limit.
4. **Cookie and Kate** and **Minimalist Baker**: usable JSON-LD, no scraping clause found for Cookie and Kate; Minimalist Baker's terms could not be read and should be read by a person first. Cookie and Kate's bot rule rejects the app's current UA string.
5. **BBC Good Food**: easy to read, quarterly recipe sitemap, but UK units and a copy/store clause. Better as a later addition.

Not recommended for the daily lane: Allrecipes, Serious Eats, Simply Recipes, NYT Cooking, Bon Appétit, Epicurious, Food Network, Delish, King Arthur, Budget Bytes, Pinch of Yum. All have explicit anti-scraping clauses, and the first six also name AI use. Budget Bytes is the best weekday fit of the group but its terms name scraping and its Cloudflare rule rejects the app's current UA. Smitten Kitchen and Woks of Life cannot be read structurally.

A pasted-URL import is a different case from a daily crawl: the household chose one page, once. The terms still apply, but one fetch per user action is much closer to what a browser does [I].

### Practical points for the build

- **Pick one honest User-Agent and test it.** The app's current `AnthropicDriver` string imitates a Mac browser. It is challenged on Budget Bytes and Cookie and Kate (both Cloudflare) and works on the rest of the shortlist. I did not find one UA that all sites accept, and I do not recommend rotating strings to get past bot walls [I].
- **Honour robots.txt in code** for any crawl, including `Disallow: /wp-json/` on Woks of Life. Fetch each site's robots.txt before each crawl.
- **Poll gently:** one feed or one sitemap per site per day, then fetch only unseen recipe URLs.
- **Strip tracking junk** (`?adt_ei=*|EMAIL|*`) from feed links before storing.

## Limits of this research

- 3 pages per site on one day; counts are rough. Only the first page per site was tested with four User-Agents.
- Terms excerpts are keyword extractions. I could not read Minimalist Baker's, Woks of Life's or Food Network's own terms (Cloudflare or Akamai blocks), and found no terms page for Love and Lemons, Smitten Kitchen or RecipeTin Eats (only a recipe-use policy).
- For Budget Bytes and Cookie and Kate pages 1 to 3, the JSON-LD comes from the default-curl fetch because the browser-style fetch was challenged. The page files in `cap/pages/` are named `*.recipe-ldjson.json` for that reason.
- I made two requests to `https://thewoksoflife.com/wp-json/…` (one posts, one wprm_recipe) before I had read that its robots.txt disallows `/wp-json/`. Both answered; the captures are `cap/wp-json/woksoflife.*`. I made no further requests there.
- The Food Network terms text is the WBD visitor agreement at wbd.com. I assumed, but could not confirm, that it covers foodnetwork.com.
- Where a new recipe appears in Taste of Home's 18 recipe sitemaps, and NYT's newest monthly sitemap, I did not establish.
- Scripts were run from outside the captures directory with `python3 -I`; they are not kept in the repo.
