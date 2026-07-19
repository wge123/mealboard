<?php

namespace App\Providers;

use App\Walmart\ChromeListBrowser;
use App\Walmart\ListBrowser;
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
        //
    }
}
