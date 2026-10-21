# Prep bowls are a label on each ingredient, not a list of prep tasks

The mise en place can show which ingredients are prepped into the same bowl before the heat goes on (a spice bowl, the aromatics, a sauce), so the household preps bowl by bowl. A prep bowl is an optional label on each ingredient of a recipe, stored beside its prep note; ingredients sharing a label are shown together under it with their own progress, and fold away once all of them are ready. Prep still lives on the ingredient, so the recipe shape of ADR 0001 holds: this amends only its wording that a task spanning several ingredients is written on each of them, which now may also name the bowl they share.

The label comes from the source. A model restructuring a video or pasted text gives an ingredient a bowl only when the source itself combines it with others before cooking; code restructuring a recipe page leaves bowls empty, because schema.org recipe data carries no groups. The household sets or changes bowls in the recipe form.

## Considered Options

- **A separate list of prep tasks spanning several ingredients**: rejected. It reverses ADR 0001's strict recipe shape, and prep would live in two places.
- **Backfill bowls on existing recipes with a one-off model pass**: rejected for now. None of the 168 stored prep notes named a bowl (2026-10-15), so a pass would be guessing; existing recipes stay ungrouped until edited.

## Consequences

- The shopping list ignores bowls: lines still merge by ingredient.
- Cooking steps stay unlinked to bowls, as ADR 0001 rejected step links.
- Bowls appear in the order of their first ingredient, so a source that lists its ingredients in cooking order keeps that order.
