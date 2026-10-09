<?php

namespace Database\Seeders;

use App\Actions\Recipes\SyncRecipeShape;
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

        $shape = app(SyncRecipeShape::class);

        foreach ($this->recipes() as $data) {
            $recipe = Recipe::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'source' => RecipeSource::Manual,
                'source_url' => $data['source_url'],
                'status' => $data['status'],
                'meal_type' => $data['meal_type'],
                'prep_minutes' => $data['prep_minutes'],
                'cook_minutes' => $data['cook_minutes'],
                'servings' => $data['servings'],
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

            $shape->handle($recipe, $this->tools($data['tools']), $data['steps']);
        }
    }

    /**
     * @param  list<list<string>>  $tools  each entry is the alternative words for one tool
     * @return list<array{alternatives: list<string>, count: int}>
     */
    private function tools(array $tools): array
    {
        return array_map(fn (array $words) => ['alternatives' => $words, 'count' => 1], $tools);
    }

    /**
     * Each recipe is written by hand in the recipe shape: tools (the words are
     * kitchen tool kinds or other names from the catalog), ingredients with
     * their prep note, and cooking steps.
     *
     * Ingredient rows: [name, category, is_pantry_staple, qty, unit, prep note]
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
                'source_url' => 'https://www.budgetbytes.com/scrambled-eggs-with-spinach-and-feta/',
                'description' => 'Soft scrambled eggs folded with wilted spinach and crumbled feta. Fast enough for a weekday.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Breakfast,
                'prep_minutes' => 5,
                'cook_minutes' => 8,
                'servings' => 2,
                'cuisine' => 'greek',
                'tags' => ['quick', 'vegetarian', 'high-protein'],
                'tools' => [
                    ['skillet', 'frying pan'],
                    ['mixing bowls'],
                    ['whisk'],
                    ['spatula'],
                ],
                'steps' => [
                    'Whisk the eggs with a pinch of salt in a bowl.',
                    'Melt the butter in the skillet over medium-low heat and wilt the spinach.',
                    'Pour in the eggs and stir gently with the spatula until barely set.',
                    'Fold in the feta off the heat and serve immediately.',
                ],
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
                'source_url' => 'https://www.budgetbytes.com/blueberry-banana-baked-oatmeal/',
                'description' => 'Creamy stovetop oats sweetened with banana and studded with frozen blueberries.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Breakfast,
                'prep_minutes' => 5,
                'cook_minutes' => 10,
                'servings' => 2,
                'cuisine' => null,
                'tags' => ['healthy', 'vegetarian', 'make-ahead'],
                'tools' => [
                    ['saucepan'],
                    ['wooden spoon'],
                ],
                'steps' => [
                    'Combine the oats, milk and a pinch of salt in the saucepan.',
                    'Simmer 8 minutes, stirring occasionally.',
                    'Mash in the banana, then stir in the frozen blueberries until warmed through.',
                    'Finish with a drizzle of honey.',
                ],
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
                'source_url' => 'https://www.budgetbytes.com/chicken-caesar-wrap/',
                'description' => 'Grilled chicken, crisp romaine, and parmesan in a tortilla — a lunch that travels well.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Lunch,
                'prep_minutes' => 15,
                'cook_minutes' => 10,
                'servings' => 2,
                'cuisine' => 'american',
                'tags' => ['quick', 'high-protein', 'packable'],
                'tools' => [
                    ['skillet', 'grill pan'],
                    ['tongs'],
                    ['cutting board'],
                    ['chef\'s knife'],
                    ['mixing bowls'],
                ],
                'steps' => [
                    'Season the chicken with salt and pan-cook 4-5 minutes per side; rest and slice.',
                    'Toss the romaine with the caesar dressing and parmesan.',
                    'Pile the salad and chicken onto the tortillas.',
                    'Roll tightly, slice in half and serve.',
                ],
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
                'source_url' => 'https://www.feastingathome.com/thai-noodle-salad-with-peanut-sauce/',
                'description' => 'Cold rice noodles in a punchy peanut-lime dressing with crunchy vegetables.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Lunch,
                'prep_minutes' => 20,
                'cook_minutes' => 5,
                'servings' => 3,
                'cuisine' => 'thai',
                'tags' => ['vegetarian', 'make-ahead', 'no-reheat'],
                'tools' => [
                    ['pots'],
                    ['colander', 'strainer'],
                    ['whisk'],
                    ['mixing bowls'],
                ],
                'steps' => [
                    'Cook the rice noodles per the package, rinse cold and drain.',
                    'Whisk the peanut butter, soy sauce, lime juice and a splash of water into a dressing.',
                    'Toss the noodles with the cucumber and carrot.',
                    'Coat with the dressing and chill until lunch.',
                ],
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
                'source_url' => 'https://www.bbcgoodfood.com/recipes/best-spaghetti-bolognese-recipe',
                'description' => 'A weeknight-friendly ragu: beef, tomatoes, and aromatics simmered until rich.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 15,
                'cook_minutes' => 45,
                'servings' => 4,
                'cuisine' => 'italian',
                'tags' => ['comfort', 'family', 'freezer-friendly'],
                'tools' => [
                    ['skillet', 'dutch oven'],
                    ['pots'],
                    ['wooden spoon'],
                    ['colander'],
                ],
                'steps' => [
                    'Sweat the onion and garlic in olive oil until soft.',
                    'Brown the beef, breaking it up as it cooks.',
                    'Add the canned tomatoes, season with salt and simmer 30 minutes.',
                    'Cook the spaghetti, toss with the sauce and serve.',
                ],
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
                'source_url' => 'https://cafedelites.com/chicken-tikka-masala/',
                'description' => 'Yogurt-marinated chicken thighs in a spiced tomato-cream sauce over basmati rice.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 25,
                'cook_minutes' => 35,
                'servings' => 4,
                'cuisine' => 'indian',
                'tags' => ['comfort', 'spicy', 'weekend'],
                'tools' => [
                    ['skillet', 'dutch oven'],
                    ['mixing bowls'],
                    ['wooden spoon'],
                    ['saucepan', 'pots'],
                ],
                'steps' => [
                    'Marinate the chicken in yogurt and half the garam masala for 20 minutes.',
                    'Sear the chicken in oil until browned.',
                    'Add the canned tomatoes, cream and remaining spices; simmer 20 minutes.',
                    'Cook the basmati rice and serve the chicken over it.',
                ],
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
                'source_url' => 'https://natashaskitchen.com/ground-beef-tacos/',
                'description' => 'Seasoned beef with charred corn, cheddar, and salsa in warm tortillas.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 10,
                'cook_minutes' => 15,
                'servings' => 4,
                'cuisine' => 'mexican',
                'tags' => ['quick', 'family', 'crowd-pleaser'],
                'tools' => [
                    ['skillet'],
                    ['spatula'],
                    ['box grater', 'grater'],
                ],
                'steps' => [
                    'Brown the beef with salt and cumin.',
                    'Char the frozen corn in a dry skillet.',
                    'Warm the tortillas.',
                    'Assemble with cheddar and salsa; serve immediately.',
                ],
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
                'source_url' => 'https://www.recipetineats.com/teriyaki-salmon/',
                'description' => 'Pan-seared salmon glazed in a quick homemade teriyaki, with steamed broccoli and rice.',
                'status' => RecipeStatus::Approved,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 10,
                'cook_minutes' => 20,
                'servings' => 2,
                'cuisine' => 'japanese',
                'tags' => ['healthy', 'quick', 'high-protein'],
                'tools' => [
                    ['skillet', 'frying pan'],
                    ['saucepan'],
                    ['steamer basket', 'steamer'],
                    ['rice cooker', 'pots'],
                    ['spatula'],
                ],
                'steps' => [
                    'Simmer the soy sauce, honey and grated ginger into a glaze.',
                    'Sear the salmon skin-side down 4 minutes, flip and brush with the glaze.',
                    'Steam the broccoli until crisp-tender.',
                    'Serve over rice with the remaining glaze spooned over.',
                ],
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
                'source_url' => 'https://www.bunsinmyoven.com/margherita-flatbread/',
                'description' => 'Store-bought flatbread with fresh mozzarella, tomato, and basil — dinner in 20 minutes.',
                'status' => RecipeStatus::Pending,
                'meal_type' => MealType::Dinner,
                'prep_minutes' => 10,
                'cook_minutes' => 12,
                'servings' => 2,
                'cuisine' => 'italian',
                'tags' => ['quick', 'vegetarian'],
                'tools' => [
                    ['oven'],
                    ['sheet pan', 'baking sheet'],
                ],
                'steps' => [
                    'Heat the oven to 220°C with a tray inside.',
                    'Brush the flatbreads with olive oil and top with tomato and torn mozzarella.',
                    'Bake 10-12 minutes until blistered.',
                    'Scatter with basil and a pinch of salt before serving.',
                ],
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
                'source_url' => 'https://cookieandkate.com/best-lentil-soup-recipe/',
                'description' => 'A pot of brown lentils with carrot, celery, and stock. Works for lunch or dinner, freezes well.',
                'status' => RecipeStatus::Pending,
                'meal_type' => MealType::Any,
                'prep_minutes' => 15,
                'cook_minutes' => 40,
                'servings' => 6,
                'cuisine' => 'french',
                'tags' => ['healthy', 'vegetarian', 'freezer-friendly', 'budget'],
                'tools' => [
                    ['stockpot', 'pots'],
                    ['immersion blender', 'blender'],
                    ['wooden spoon'],
                ],
                'steps' => [
                    'Sweat the onion, carrot and celery in olive oil until softened.',
                    'Add the lentils and vegetable stock.',
                    'Simmer 35 minutes until the lentils are tender.',
                    'Season with salt, then blend a third of the pot for body and serve.',
                ],
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
