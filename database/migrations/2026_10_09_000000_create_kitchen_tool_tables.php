<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The kitchen tool inventory: kinds (with the household's owned flag and note)
 * and the other names that point at them. The catalog ships as data in this
 * migration; later catalog additions come as new migrations that leave
 * household rows untouched. Words are stored trimmed and lowercased.
 */
return new class extends Migration
{
    /**
     * kind => other names. Basics are owned from the start.
     *
     * @var array<string, list<string>>
     */
    private const BASICS = [
        'oven' => [],
        'stovetop' => ['stove', 'cooktop', 'range', 'hob'],
        "chef's knife" => ['chef knife', 'cooks knife', 'kitchen knife'],
        'cutting board' => ['chopping board'],
        'pots' => ['pot'],
        'mixing bowls' => ['mixing bowl', 'bowl'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const CATALOG = [
        // Appliances
        'air fryer' => [],
        'blender' => ['smoothie blender'],
        'immersion blender' => ['stick blender', 'hand blender'],
        'food processor' => [],
        'stand mixer' => ['kitchenaid'],
        'hand mixer' => ['electric whisk'],
        'slow cooker' => ['crock pot', 'crockpot'],
        'pressure cooker' => ['instant pot', 'multicooker'],
        'rice cooker' => [],
        'toaster oven' => [],
        'microwave' => ['microwave oven'],
        'waffle iron' => ['waffle maker'],
        // Cookware
        'skillet' => ['frying pan', 'fry pan'],
        'cast iron skillet' => ['cast-iron pan'],
        'nonstick pan' => ['non-stick pan'],
        'dutch oven' => ['casserole pot', 'cocotte'],
        'wok' => [],
        'flat-top griddle' => ['griddle', 'flat top', 'plancha'],
        'grill pan' => [],
        'saucepan' => [],
        'stockpot' => ['soup pot'],
        'roasting pan' => ['roaster'],
        'steamer basket' => ['steamer'],
        // Bakeware
        'sheet pan' => ['baking sheet', 'baking tray', 'cookie sheet'],
        'baking dish' => ['casserole dish'],
        'loaf pan' => ['bread pan'],
        'muffin tin' => ['muffin pan'],
        'cake pan' => ['cake tin'],
        'pie dish' => ['pie plate'],
        'wire rack' => ['cooling rack'],
        // Small tools
        'colander' => ['strainer'],
        'whisk' => [],
        'box grater' => ['grater'],
        'vegetable peeler' => ['peeler'],
        'tongs' => [],
        'spatula' => ['turner'],
        'wooden spoon' => [],
        'ladle' => [],
        'rolling pin' => [],
        'measuring cups' => ['measuring cup'],
        'measuring spoons' => ['measuring spoon'],
        'kitchen scale' => ['food scale'],
        'meat thermometer' => ['probe thermometer'],
        'mortar and pestle' => ['pestle'],
        'can opener' => [],
        'bread knife' => ['serrated knife'],
        'paring knife' => [],
    ];

    public function up(): void
    {
        Schema::create('kitchen_tool_kinds', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('origin'); // catalog | household
            $table->boolean('owned')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('kitchen_tool_other_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_tool_kind_id')->constrained('kitchen_tool_kinds')->cascadeOnDelete();
            // Unique, so one word never names two kinds. Not being also a
            // kind's name is enforced by the KitchenToolInventory module.
            $table->string('name')->unique();
            $table->string('origin'); // catalog | household
            $table->timestamps();
        });

        $now = now();

        foreach ([[self::BASICS, true], [self::CATALOG, false]] as [$entries, $owned]) {
            foreach ($entries as $kind => $otherNames) {
                $kindId = DB::table('kitchen_tool_kinds')->insertGetId([
                    'name' => $kind,
                    'origin' => 'catalog',
                    'owned' => $owned,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($otherNames as $otherName) {
                    DB::table('kitchen_tool_other_names')->insert([
                        'kitchen_tool_kind_id' => $kindId,
                        'name' => $otherName,
                        'origin' => 'catalog',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tool_other_names');
        Schema::dropIfExists('kitchen_tool_kinds');
    }
};
