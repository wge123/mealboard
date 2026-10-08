# Mealboard

Plans a household's weekday meals, discovers new recipes, and turns a locked week into a shopping list that ends up in a Walmart cart.

## Shopping

**Shopping list**:
Everything the meals of one locked week need, merged across recipes and grouped by store category.
_Avoid_: grocery list, push items

**Line**:
One entry on the shopping list: an ingredient with its merged amount. One ingredient can have two lines when its amounts can't be added together (cups and grams of flour).
_Avoid_: row, item

**Merge bucket**:
What a line's amounts are added up under: the unit family for units that convert into each other (spoon for tsp, tbsp and cup; mass for g and kg; volume for ml and l), else the raw unit. Part of the line key (ingredient name and merge bucket), so a checked line stays checked when its amount moves to another display unit.
_Avoid_: unit group, unit key

**Checked**:
A line the household has handled and must not buy again: it is in the cart or already in the kitchen.
_Avoid_: ticked, in cart, bought

**Buy list**:
The ingredients still to buy for a week: one entry per ingredient, leaving out pantry staples and any ingredient whose every line is checked. What the cart link, the cart agent and the list push act on.
_Avoid_: to-buy, cart items

**Stale week**:
A locked week whose dates are already past when its shopping list is used, because no newer week was locked.
_Avoid_: old week, outdated list

**Pantry staple**:
An ingredient the household keeps in stock (salt, oil). Shown on the shopping list on request, never on the buy list.
_Avoid_: staple item, basics

**Product match**:
The Walmart product the household has confirmed for an ingredient, remembered for future weeks.
_Avoid_: mapping, saved product
