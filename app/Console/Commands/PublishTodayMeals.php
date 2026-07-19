<?php

namespace App\Console\Commands;

use App\Actions\Planning\PublishMealsMarkdown;
use Illuminate\Console\Command;

class PublishTodayMeals extends Command
{
    protected $signature = 'meals:publish-today';

    protected $description = "Publish today's meals to the vault as meals/today.md";

    public function handle(PublishMealsMarkdown $publisher): int
    {
        if (! $publisher->publishToday()) {
            $this->info('No locked week covers today — nothing published.');

            return self::SUCCESS;
        }

        $this->info('meals/today.md published.');

        return self::SUCCESS;
    }
}
