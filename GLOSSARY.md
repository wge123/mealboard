# Mise

Plans a household's weekday meals, discovers new recipes, and turns a locked week into a shopping list that ends up in a Walmart cart.

## Household

**Household**:
The people who share one kitchen and plan meals together. Recipes, plans, kitchen tools and preferences belong to the household, never to one person, even while it has a single member.
_Avoid_: user, account, family

**Kitchen tool**:
A kind of equipment a recipe can require, from oven and stovetop to chef's knife and flat-top griddle. Named by kind ("skillet"), never by a specific item; owning any tool of that kind is enough.
_Avoid_: equipment, utensil, gadget, appliance, tool (alone)

**Other name**:
Another word for a kitchen tool's kind ("frying pan" for skillet), so a recipe that uses it still finds the tool. The app knows some; the household adds its own when a recipe flags a tool it already owns.
_Avoid_: alias, synonym, nickname

**Tool inventory**:
The kitchen tools the household owns, each owned or not, with no count. Starts with the common basics already in it; the household removes what its kitchen lacks and adds kinds the app does not know yet.
_Avoid_: kitchen, equipment list

**Missing tool**:
A kitchen tool a recipe needs where the tool inventory holds none of the recipe's alternatives for it; a kind the app does not know counts as missing. A recipe with a missing tool is still found, approved and kept, but shown as needing it, and auto-fill never plans it.
_Avoid_: unowned tool, tool gap

**Household preference**:
A structured rule the household sets for itself, such as a number or a list, that the app reads and can check. Tastes written as prose live in the household's food notes instead.
_Avoid_: setting, config, user preference

**Household size**:
How many people the household cooks for. Discovery looks for recipes that serve about this many.
_Avoid_: headcount, servings (a recipe's own count)

**Weekday limits**:
The most total time and the most ingredients a recipe found by daily discovery may have, so it fits a Mon–Fri meal. Pantry staples do not count toward the ingredient limit. A recipe the household asked for is not bound by them, and changing them never removes a recipe already in the library.
_Avoid_: weeknight caps, healthy/easy thresholds

**Avoided ingredient**:
An ingredient the household never wants in a meal. A hard exclusion: no discovered recipe may contain it, including recipes the household asked for, and auto-fill never plans a library recipe that does.
_Avoid_: dislike, blocked ingredient, allergy (an allergy is one reason to avoid)

## Recipes

**Recipe shape**:
The three parts every recipe has, in order: its kitchen tools, its mise en place, and its cooking steps. A recipe missing tools, ingredients or cooking steps is not a recipe.
_Avoid_: recipe format, template

**Mise en place**:
The part of a recipe that gets everything ready before the heat goes on: each ingredient with its amount and its prep note. Always present, because a recipe always has ingredients.
_Avoid_: mise (the app's name), prep section, ingredients section

**Prep note**:
What to do to one ingredient before cooking starts ("chopped", "whites and greens apart", "into the spice bowl"). Optional; a task spanning several ingredients is written on each of them.
_Avoid_: prep step, prep task, instruction

**Cooking step**:
One plain-text action once the heat is on. It names tools and ingredients in words only; the recipe declares its tools once, not per step.
_Avoid_: instruction, method step

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
An ingredient the household keeps in stock (salt, oil); the household decides which ingredients are staples. Shown on the shopping list on request, never on the buy list.
_Avoid_: staple item, basics

**Product match**:
The Walmart product the household has confirmed for an ingredient, remembered for future weeks.
_Avoid_: mapping, saved product
