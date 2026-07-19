<?php

use App\Http\Controllers\Api\PlanController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(AuthenticateApiToken::class)->group(function () {
    Route::get('/plan/current', [PlanController::class, 'current'])->name('api.plan.current');
    Route::get('/plan/ical', [PlanController::class, 'ical'])->name('api.plan.ical');
});
