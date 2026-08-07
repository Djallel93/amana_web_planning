<?php

namespace App\Providers;

use Amana\Shared\Contracts\ActivityStatisticsProvider;
use Amana\Shared\Contracts\NavBadgeProvider;
use App\Services\AuditStatistics;
use App\Services\NavBadges;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Lie l'implémentation planning au contrat consommé par
        // Amana\Shared\Http\Controllers\ActivityStatsController (partagé).
        $this->app->bind(ActivityStatisticsProvider::class, AuditStatistics::class);

        // Lie l'implémentation planning au contrat consommé par la sidebar
        // partagée pour afficher les badges de navigation (ex. nombre de
        // candidatures en attente — voir NavBadges).
        $this->app->bind(NavBadgeProvider::class, NavBadges::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}