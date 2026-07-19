<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\ChannelSettings;
use App\Livewire\PlanBuilder;
use App\Livewire\RecipeApproval;
use App\Livewire\RecipeCreate;
use App\Livewire\RecipeDetail;
use App\Livewire\RecipeLibrary;
use App\Livewire\ShoppingList;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/plan', PlanBuilder::class)->name('plan.builder');
    Route::get('/plan/{mealPlan}/shopping-list', ShoppingList::class)->name('plan.shopping-list');
    Route::get('/recipes', RecipeLibrary::class)->name('recipes.index');
    // /recipes/create must be registered before /recipes/{recipe} so "create"
    // isn't captured as a model id.
    Route::get('/recipes/create', RecipeCreate::class)->name('recipes.create');
    Route::get('/recipes/{recipe}', RecipeDetail::class)->name('recipes.show');
    Route::get('/approve', RecipeApproval::class)->name('recipes.approve');
    Route::get('/settings/channels', ChannelSettings::class)->name('settings.channels');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
