<?php

namespace App\Providers;

use Amana\Shared\Contracts\ActivityStatisticsProvider;
use Amana\Shared\Contracts\NavBadgeProvider;
use App\Services\AuditStatistics;
use App\Services\NavBadges;
use App\Services\SessionPolicy;
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

        // La durée d'inactivité est appliquée par ApplySessionPolicy (paramètre admin
        // `session_lifetime`, jusqu'à 24 h) et la case « Rester connecté » tient jusqu'à
        // minuit. Le stockage de session (ligne en base) et le cookie de session doivent
        // donc vivre au moins 24 h, quelle que soit la valeur de SESSION_LIFETIME dans
        // .env — sinon ils expireraient avant la règle configurée. Aucun accès base ici.
        config(['session.lifetime' => max((int) config('session.lifetime'), SessionPolicy::MAX_MINUTES)]);
    }
}
