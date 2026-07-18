<?php

use App\Actions\Recipes\SetRecipeStatus;
use App\Enums\RecipeStatus;
use App\Models\Recipe;
use App\Models\User;

it('stamps approved_at and approved_by on approve', function () {
    $approver = User::factory()->create();
    $recipe = Recipe::factory()->create(['status' => RecipeStatus::Pending]);

    app(SetRecipeStatus::class)->handle($recipe, RecipeStatus::Approved, $approver);

    $recipe->refresh();

    expect($recipe->status)->toBe(RecipeStatus::Approved)
        ->and($recipe->approved_at)->not->toBeNull()
        ->and($recipe->approved_by)->toBe($approver->id);
});

it('sets rejected and archived without stamping approval', function (RecipeStatus $status) {
    $recipe = Recipe::factory()->create(['status' => RecipeStatus::Pending]);

    app(SetRecipeStatus::class)->handle($recipe, $status, User::factory()->create());

    $recipe->refresh();

    expect($recipe->status)->toBe($status)
        ->and($recipe->approved_at)->toBeNull()
        ->and($recipe->approved_by)->toBeNull();
})->with([RecipeStatus::Rejected, RecipeStatus::Archived]);
