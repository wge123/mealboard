<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\RecipeCreate;
use App\Livewire\RecipeDetail;
use App\Livewire\RecipeLibrary;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/recipes', RecipeLibrary::class)->name('recipes.index');
    // /recipes/create must be registered before /recipes/{recipe} so "create"
    // isn't captured as a model id.
    Route::get('/recipes/create', RecipeCreate::class)->name('recipes.create');
    Route::get('/recipes/{recipe}', RecipeDetail::class)->name('recipes.show');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
