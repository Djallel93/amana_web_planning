<?php

namespace App\Providers;

use Amana\Shared\Contracts\ActivityStatisticsProvider;
use Amana\Shared\Contracts\NavBadgeProvider;
use App\Services\AuditStatistics;
use App\Services\NavBadges;
use Illuminate\Http\Resources\Json\JsonResource;
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
        // Désactive l'enveloppe {"data": ...} que Laravel ajoute par défaut
        // autour des API Resources (collections notamment). Les endpoints
        // JSON existants (CalendriersController, PlanningEditController,
        // BilanController, PlanningApiController…) renvoient déjà des
        // formes précises (ex. {'calendars': [...]}, ou un tableau nu pour
        // /planning/personnes) consommées telles quelles par le frontend
        // Vue — voir resources/js/types/planning.ts. Introduire des
        // Resources (app/Http/Resources) ne doit rien changer à ces
        // formes ; sans ce réglage, ::collection() envelopperait
        // silencieusement le JSON dans 'data'.
        JsonResource::withoutWrapping();
    }
}