# Recipes come from people; a model only restructures them

Every new recipe's ingredients, amounts and steps come from a source a person published: a recipe page the household imports by pasting its address, or a video or page that discovery finds. A model may restructure that source into the recipe shape (split ingredient lines, read the kitchen tools off the steps), but never writes, adapts, scales or shortens a recipe. So the claude driver that wrote recipes and cited a source for them is retired from daily discovery and from requests, and the TheMealDB random fallback goes with it. This replaces the citation rule in DECISIONS #9: a cited address that loads proved only that the page existed, not that the recipe came from it, and 7 of the driver's 17 recipes were rejected.

## Considered Options

- **Keep the claude driver with its citation check**: rejected. It is the volume source, but the household wants real recipes, and the check cannot tell a copied recipe from an invented one.
- **Require the source to name its kitchen tools too**: rejected. Most recipe pages never list tools; reading them off "sear in a skillet" is restructuring, not invention.
- **Let a model pull a recipe out of a page's text when it has no structured recipe data**: rejected for now. That is where a model starts filling gaps; such a page is pasted as text by hand instead.

## Consequences

- Requests search YouTube only until web discovery exists, so they return fewer candidates.
- The YouTube lane falls under the same rule: an amount the video and its description never state stays blank instead of being guessed, so some shopping list lines carry no amount.
- An import from a page's structured recipe data is checked in code against that data, line for line, because a model's promise not to invent is not enough. A transcript has no list to check against, so videos rely on the rule alone.
- The ten approved recipes the claude driver wrote stay in the library unmarked.
- Times, servings and meal type a source leaves out may be estimated on an import, where the household sees them in the form, but never on a discovered recipe: a guessed time could slip past the weekday limits.
