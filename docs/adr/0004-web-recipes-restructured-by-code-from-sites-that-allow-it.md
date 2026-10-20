# Web recipes are restructured by code, from sites that allow it

A recipe page's schema.org Recipe data is turned into the recipe shape by code, not by a model: ingredient lines are parsed into amount, unit, name and prep note, and kitchen tools are found by matching known kind words and other names in the steps. No model sees the author's text, which respects the sites that block AI agents in robots.txt, and the line-for-line check of ADR 0003 holds by construction. A model is still used where the household hands it text itself (pasting a recipe's text) and for video transcripts.

Daily discovery reads only recipe sites whose own terms do not ban automated reading and whose robots rules do not block AI agents; that rule is the test for adding a site, not a fixed list. The app always sends one honest User-Agent, never imitates a browser and never works around a challenge: a site that blocks it is saying no. A pasted import is one page the household chose to open, so it works from any site that serves its recipe data.

## Considered Options

- **Restructure with the model, as video extraction does**: rejected. It would send authors' pages to a model several of them block by name, and it makes the no-invention check something to verify rather than something guaranteed.
- **Read any recipe site at low volume**: rejected. Most large publishers' terms ban scraping and feeding content to AI (see docs/research/recipe-site-json-ld.md).
- **Imitate a browser User-Agent to get past bot walls**: rejected. It is evasion, and a browser string drew more challenges than the default one anyway.

## Consequences

- Some tools are missed and some ingredient lines split badly. On an import the household fixes them in the form; a discovered candidate with no tool found fails the recipe shape and is skipped.
- The first shortlist is small: Love and Lemons and Just One Cookbook, then Minimalist Baker once a person has read its terms, and BBC Good Food later.
