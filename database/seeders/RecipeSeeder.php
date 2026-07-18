<?php

namespace Database\Seeders;

use App\Enums\IngredientCategory;
use App\Enums\MealType;
use App\Enums\RecipeSource;
use App\Enums\RecipeStatus;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $approver = User::where('email', 'willem@example.com')->first()
            ?? User::factory()->create(['email' => 'willem@example.com']);

        foreach ($this->recipes() as $data) {
            $recipe = Recipe::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'source' => RecipeSource::Manual,
                'status' => $data['status'],
                'meal_type' => $data['meal_type'],
                'prep_minutes' => $data['prep_minutes'],
                'cook_minutes' => $data['cook_minutes'],
                'servings' => $data['servings'],
                'instructions' => $data['instructions'],
                'cuisine' => $data['cuisine'],
                'tags' => $data['tags'],
                'approved_at' => $data['status'] === RecipeStatus::Approved ? now() : null,
                'approved_by' => $data['status'] === RecipeStatus::Approved ? $approver->id : null,
            ]);

            foreach ($data['ingredients'] as [$name, $category, $staple, $qty, $unit, $note]) {
                $ingredient = Ingredient::firstOrCreate(
                    ['name' => mb_strtolower($name)],
                    ['category' => $category, 'is_pantry_staple' => $staple],
                );

                $recipe->ingredients()->attach($ingredient->id, [
                    'qty' => $qty,
                    'unit' => $unit,
                    'note' => $note,
                ]);
            }
        }
    }

    /**
     * Ingredient rows: [name, category, is_pantry_staple, qty, unit, note]
     *
     * @return array<int, array<string, mixed>>
     */
    private function recipes(): array
    {
        $produce = IngredientCategory::Produce;
        $meat = IngredientCategory::Meat;
        $dairy = IngredientCategory::Dairy;
        $pantry = IngredientCategory::Pantry;
        $frozen = IngredientCategory::Frozen;
        $bakery = IngredientCategory::Bakery;
        $other = IngredientCategory::Other;

        return [
            [
                'title' => 'Spinach and Feta Scrambled Eggs',
                'description' => 'Soft scrambled eggs folded with wilted spinach and crumbled feta. Fast enough for a weekday.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Breakfast,
                'prep_minutes' => 5,
                'cook_minutes' => 8,
                'servings' => 2,
                'cuisine' => 'greek',
                'tags' => ['quick', 'vegetarian', 'high-protein'],
                'instructions' => "1. Whisk the eggs with a pinch of salt.\n2. Melt the butter in a nonstick pan over medium-low heat and wilt the spinach.\n3. Pour in the eggs and stir gently until barely set.\n4. Fold in the feta off the heat and serve immediately.",
                'ingredients' => [
                    ['eggs', $dairy, false, 4, 'count', null],
                    ['baby spinach', $produce, false, 60, 'g', 'roughly chopped'],
                    ['feta', $dairy, false, 50, 'g', 'crumbled'],
                    ['butter', $dairy, false, 1, 'tbsp', null],
                    ['salt', $pantry, true, 0.5, 'tsp', null],
                ],
            ],
            [
                'title' => 'Blueberry Banana Oatmeal',
                'description' => 'Creamy stovetop oats sweetened with banana and studded with frozen blueberries.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Breakfast,
                'prep_minutes' => 5,
                'cook_minutes' => 10,
                'servings' => 2,
                'cuisine' => null,
                'tags' => ['healthy', 'vegetarian', 'make-ahead'],
                'instructions' => "1. Combine the oats, milk, and a pinch of salt in a saucepan.\n2. Simmer 8 minutes, stirring occasionally.\n3. Mash in the banana, then stir in the frozen blueberries until warmed through.\n4. Finish with a drizzle of honey.",
                'ingredients' => [
                    ['rolled oats', $pantry, false, 1, 'cup', null],
                    ['milk', $dairy, false, 2, 'cup', null],
                    ['banana', $produce, false, 1, 'count', 'ripe'],
                    ['frozen blueberries', $frozen, false, 0.75, 'cup', null],
                    ['honey', $pantry, false, 1, 'tbsp', null],
                    ['salt', $pantry, true, null, null, 'pinch'],
                ],
            ],
            [
                'title' => 'Chicken Caesar Wraps',
                'description' => 'Grilled chicken, crisp romaine, and parmesan in a tortilla — a lunch that travels well.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Lunch,
                'prep_minutes' => 15,
                'cook_minutes' => 10,
                'servings' => 2,
                'cuisine' => 'american',
                'tags' => ['quick', 'high-protein', 'packable'],
                'instructions' => "1. Season the chicken with salt and pan-cook 4-5 minutes per side; rest and slice.\n2. Toss the romaine with the caesar dressing and parmesan.\n3. Pile the salad and chicken onto the tortillas.\n4. Roll tightly, slice in half, and serve.",
                'ingredients' => [
                    ['chicken breast', $meat, false, 300, 'g', null],
                    ['romaine lettuce', $produce, false, 1, 'count', 'heart, chopped'],
                    ['flour tortillas', $bakery, false, 2, 'count', 'large'],
                    ['parmesan', $dairy, false, 30, 'g', 'shaved'],
                    ['caesar dressing', $other, false, 3, 'tbsp', null],
                    ['salt', $pantry, true, 0.5, 'tsp', null],
                ],
            ],
            [
                'title' => 'Thai Peanut Noodle Salad',
                'description' => 'Cold rice noodles in a punchy peanut-lime dressing with crunchy vegetables.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Lunch,
                'prep_minutes' => 20,
                'cook_minutes' => 5,
                'servings' => 3,
                'cuisine' => 'thai',
                'tags' => ['vegetarian', 'make-ahead', 'no-reheat'],
                'instructions' => "1. Cook the rice noodles per the package, rinse cold, and drain.\n2. Whisk the peanut butter, soy sauce, lime juice, and a splash of water into a dressing.\n3. Toss the noodles with the cucumber and carrot.\n4. Coat with the dressing and chill until lunch.",
                'ingredients' => [
                    ['rice noodles', $pantry, false, 200, 'g', null],
                    ['peanut butter', $pantry, false, 3, 'tbsp', 'smooth'],
                    ['soy sauce', $pantry, true, 2, 'tbsp', null],
                    ['lime', $produce, false, 1, 'count', 'juiced'],
                    ['cucumber', $produce, false, 1, 'count', 'julienned'],
                    ['carrot', $produce, false, 1, 'count', 'julienned'],
                ],
            ],
            [
                'title' => 'Spaghetti Bolognese',
                'description' => 'A weeknight-friendly ragu: beef, tomatoes, and aromatics simmered until rich.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 15,
                'cook_minutes' => 45,
                'servings' => 4,
                'cuisine' => 'italian',
                'tags' => ['comfort', 'family', 'freezer-friendly'],
                'instructions' => "1. Sweat the onion and garlic in olive oil until soft.\n2. Brown the beef, breaking it up as it cooks.\n3. Add the canned tomatoes, season with salt, and simmer 30 minutes.\n4. Cook the spaghetti, toss with the sauce, and serve.",
                'ingredients' => [
                    ['ground beef', $meat, false, 500, 'g', null],
                    ['spaghetti', $pantry, false, 400, 'g', null],
                    ['canned tomatoes', $pantry, false, 800, 'g', 'crushed'],
                    ['onion', $produce, false, 1, 'count', 'diced'],
                    ['garlic', $produce, false, 3, 'count', 'cloves, minced'],
                    ['olive oil', $pantry, true, 2, 'tbsp', null],
                    ['salt', $pantry, true, 1, 'tsp', null],
                ],
            ],
            [
                'title' => 'Chicken Tikka Masala',
                'description' => 'Yogurt-marinated chicken thighs in a spiced tomato-cream sauce over basmati rice.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 25,
                'cook_minutes' => 35,
                'servings' => 4,
                'cuisine' => 'indian',
                'tags' => ['comfort', 'spicy', 'weekend'],
                'instructions' => "1. Marinate the chicken in yogurt and half the garam masala for 20 minutes.\n2. Sear the chicken in oil until browned.\n3. Add the canned tomatoes, cream, and remaining spices; simmer 20 minutes.\n4. Serve over steamed basmati rice.",
                'ingredients' => [
                    ['chicken thighs', $meat, false, 600, 'g', 'boneless, cubed'],
                    ['yogurt', $dairy, false, 0.5, 'cup', 'plain'],
                    ['garam masala', $pantry, false, 2, 'tbsp', null],
                    ['canned tomatoes', $pantry, false, 400, 'g', 'crushed'],
                    ['heavy cream', $dairy, false, 0.5, 'cup', null],
                    ['basmati rice', $pantry, false, 1.5, 'cup', null],
                    ['olive oil', $pantry, true, 2, 'tbsp', null],
                ],
            ],
            [
                'title' => 'Weeknight Beef Tacos',
                'description' => 'Seasoned beef with charred corn, cheddar, and salsa in warm tortillas.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 10,
                'cook_minutes' => 15,
                'servings' => 4,
                'cuisine' => 'mexican',
                'tags' => ['quick', 'family', 'crowd-pleaser'],
                'instructions' => "1. Brown the beef with salt and cumin.\n2. Char the frozen corn in a dry skillet.\n3. Warm the tortillas.\n4. Assemble with cheddar and salsa; serve immediately.",
                'ingredients' => [
                    ['ground beef', $meat, false, 500, 'g', null],
                    ['corn tortillas', $bakery, false, 8, 'count', null],
                    ['frozen corn', $frozen, false, 1, 'cup', null],
                    ['cheddar', $dairy, false, 100, 'g', 'grated'],
                    ['salsa', $other, false, 0.5, 'cup', null],
                    ['ground cumin', $pantry, false, 1, 'tsp', null],
                    ['salt', $pantry, true, 1, 'tsp', null],
                ],
            ],
            [
                'title' => 'Salmon Teriyaki with Broccoli',
                'description' => 'Pan-seared salmon glazed in a quick homemade teriyaki, with steamed broccoli and rice.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 10,
                'cook_minutes' => 20,
                'servings' => 2,
                'cuisine' => 'japanese',
                'tags' => ['healthy', 'quick', 'high-protein'],
                'instructions' => "1. Simmer the soy sauce, honey, and grated ginger into a glaze.\n2. Sear the salmon skin-side down 4 minutes, flip, and brush with the glaze.\n3. Steam the broccoli until crisp-tender.\n4. Serve over rice with the remaining glaze spooned over.",
                'ingredients' => [
                    ['salmon fillets', $meat, false, 2, 'count', 'skin-on'],
                    ['broccoli', $produce, false, 300, 'g', 'florets'],
                    ['soy sauce', $pantry, true, 3, 'tbsp', null],
                    ['honey', $pantry, false, 1, 'tbsp', null],
                    ['fresh ginger', $produce, false, 1, 'tbsp', 'grated'],
                    ['basmati rice', $pantry, false, 1, 'cup', null],
                ],
            ],
            [
                'title' => 'Margherita Flatbread',
                'description' => 'Store-bought flatbread with fresh mozzarella, tomato, and basil — dinner in 20 minutes.',
                'status' => RecipeStatus::Pending,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 10,
                'cook_minutes' => 12,
                'servings' => 2,
                'cuisine' => 'italian',
                'tags' => ['quick', 'vegetarian'],
                'instructions' => "1. Heat the oven to 220°C with a tray inside.\n2. Brush the flatbreads with olive oil and top with tomato and torn mozzarella.\n3. Bake 10-12 minutes until blistered.\n4. Scatter with basil and a pinch of salt before serving.",
                'ingredients' => [
                    ['flatbread', $bakery, false, 2, 'count', null],
                    ['fresh mozzarella', $dairy, false, 125, 'g', 'torn'],
                    ['tomato', $produce, false, 2, 'count', 'sliced'],
                    ['fresh basil', $produce, false, null, null, 'a handful'],
                    ['olive oil', $pantry, true, 1, 'tbsp', null],
                    ['salt', $pantry, true, null, null, 'pinch'],
                ],
            ],
            [
                'title' => 'Hearty Lentil Soup',
                'description' => 'A pot of brown lentils with carrot, celery, and stock. Works for lunch or dinner, freezes well.',
                'status' => RecipeStatus::Pending,
                'meal_type' => MealType::Any,
                'prep_minutes' => 15,
                'cook_minutes' => 40,
                'servings' => 6,
                'cuisine' => 'french',
                'tags' => ['healthy', 'vegetarian', 'freezer-friendly', 'budget'],
                'instructions' => "1. Sweat the onion, carrot, and celery in olive oil until softened.\n2. Add the lentils and vegetable stock.\n3. Simmer 35 minutes until the lentils are tender.\n4. Season with salt, then blend a third of the pot for body and serve.",
                'ingredients' => [
                    ['brown lentils', $pantry, false, 300, 'g', 'rinsed'],
                    ['carrot', $produce, false, 2, 'count', 'diced'],
                    ['celery', $produce, false, 2, 'count', 'stalks, diced'],
                    ['onion', $produce, false, 1, 'count', 'diced'],
                    ['vegetable stock', $other, false, 1.5, 'l', null],
                    ['olive oil', $pantry, true, 2, 'tbsp', null],
                    ['salt', $pantry, true, 1, 'tsp', null],
                ],
            ],
        ];
    }
}
