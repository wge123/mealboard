# One quiet design rulebook replaces the tactile rules

Mealboard takes the Tabler-quiet direction (wayfinder ticket 01), so the "tactile rules" at the top of `resources/css/app.css` (rounded-2xl cards, generous whitespace, every interactive element at least 44px tall) are replaced by a denser rulebook, kept in that same comment. Text is 14px on a 16px root, so spacing stays rem-based and phone inputs stay at 16px to stop iOS zooming in. There is one muted text colour, `base-content/70`, chosen because `/60` fails 4.5:1 contrast at 14px. Corners use only daisyUI's box/field/selector radii (8/6/4px) plus `rounded-full` for pills. A shadow marks something that floats (dropdown, modal, toast, floating bar, top swipe card) and nothing at rest. Herb green is the only UI colour: success, secondary and accent all point at it. The four meal-type hues remain as data colours, allowed only as markers. `mealboard-dark` becomes a cool mirror of the light theme. The theme names and the light/dark/system toggle stay the same.

## Considered Options

- **Root font size of 14px**: rejected. It would shrink every rem-based gap, and inputs would trigger iOS zoom.
- **A larger radius for the swipe-deck card**: rejected. One radius scale with no exceptions is the point of the rulebook.
- **Tabler's own success green**: rejected. It would put two greens side by side.

## Consequences

The 44px rule now applies to the hit area, not to what is drawn: a dense row is a `<label>` wrapping a small checkbox. Dark mode was never prototyped, so it gets checked by eye on the shopping list, recipe library, plan grid and swipe deck once the shared component kit lands.
