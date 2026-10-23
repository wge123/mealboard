<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\DailyCatchUp;
use App\Livewire\Insights;
use App\Livewire\PlanBuilder;
use App\Livewire\RecipeApproval;
use App\Livewire\RecipeCreate;
use App\Livewire\RecipeDetail;
use App\Livewire\RecipeLibrary;
use App\Livewire\RecipeRequester;
use App\Livewire\ReviewMatches;
use App\Livewire\Settings;
use App\Livewire\ShoppingList;
use App\Models\MealPlan;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/plan')->name('home');

// The component kit's showcase page: a development aid, so it 404s outside local.
Route::get('/kit', function () {
    abort_unless(app()->environment('local'), 404);

    return view('kit');
})->name('kit');

Route::middleware('auth')->group(function () {
    Route::get('/plan', PlanBuilder::class)->name('plan.builder');
    // Shop is always in the nav: it goes to the latest locked week, or says to lock one.
    Route::get('/shop', function () {
        $plan = MealPlan::latestLocked();

        return $plan
            ? redirect()->route('plan.shopping-list', $plan->id)
            : view('shop-empty');
    })->name('shop');
    Route::get('/plan/{mealPlan}/shopping-list', ShoppingList::class)->name('plan.shopping-list');
    Route::get('/walmart/review', ReviewMatches::class)->name('walmart.review');
    Route::get('/log', DailyCatchUp::class)->name('log.catch-up');
    Route::get('/recipes', RecipeLibrary::class)->name('recipes.index');
    // /recipes/create must be registered before /recipes/{recipe} so "create"
    // isn't captured as a model id.
    Route::get('/recipes/create', RecipeCreate::class)->name('recipes.create');
    Route::get('/recipes/{recipe}', RecipeDetail::class)->name('recipes.show');
    Route::get('/request', RecipeRequester::class)->name('recipes.request');
    Route::get('/approve', RecipeApproval::class)->name('recipes.approve');
    Route::get('/insights', Insights::class)->name('insights');
    Route::get('/settings', Settings::class)->name('settings');
    // The sections used to be pages of their own; old links land on their section.
    Route::permanentRedirect('/settings/kitchen-tools', '/settings#tools');
    Route::permanentRedirect('/settings/preferences', '/settings#preferences');
    Route::permanentRedirect('/settings/pantry-staples', '/settings#pantry');
    Route::permanentRedirect('/settings/channels', '/settings#channels');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
