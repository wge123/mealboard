# Every recipe has the strict shape: tools, mise en place, cooking

A recipe is stored as three required parts, in order: the kitchen tools it needs (each with alternatives and a count), its mise en place (every ingredient with an optional prep note), and plain-text cooking steps. The old free-text `instructions` blob goes away, and existing recipes get a one-time backfill. It is enforced the way `source_url` is (DECISIONS #9): form validation, the discovery candidate check, and the database. We chose strict over an optional shape so that the tool inventory can be checked against every recipe and every recipe reads the same way at the stove.

## Considered Options

- **Prep as its own list of tasks**, or as the opening cooking steps: rejected. Prep acts on ingredients, so it sits on each ingredient as a prep note. A task that spans several ingredients ("mix the spices") is written on each of them.
- **Cooking steps linked to the tools and ingredients they use**: rejected. Model-written recipes would fail the strict check more often, for little gain on a weeknight. Tools are declared once per recipe.

## Consequences

The planned order "mise en place with tools, then ingredients, then cooking" became tools, then mise en place, then cooking: ingredients live inside mise en place. Prototype: branch `chore/prototype-recipe-shape`.
