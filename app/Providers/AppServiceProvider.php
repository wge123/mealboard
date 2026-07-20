<?php

namespace App\Providers;

use App\Enums\MealPlanStatus;
use App\Enums\RecipeStatus;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Walmart\ChromeListBrowser;
use App\Walmart\ListBrowser;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The one production list browser. Construction is harmless (Chrome
        // only launches in open()), and walmart:push-list validates the
        // profile config before resolving this.
        $this->app->bind(ListBrowser::class, fn () => new ChromeListBrowser(
            (string) config('mealboard.chrome_profile'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nav shell data: two cheap per-request counts for the tab bar /
        // navbar (pending-approval badge, Shop tab target).
        View::composer('layouts.app', function (\Illuminate\View\View $view) {
            $view->with([
                'pendingRecipeCount' => auth()->check()
                    ? Recipe::query()->where('status', RecipeStatus::Pending)->count()
                    : 0,
                'latestLockedPlanId' => auth()->check()
                    ? MealPlan::query()
                        ->where('status', MealPlanStatus::Locked)
                        ->orderByDesc('week_start_date')
                        ->value('id')
                    : null,
            ]);
        });
    }
}
