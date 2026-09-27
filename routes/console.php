<?php
// routes/console.php

use App\Helpers\DateHelper;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
*/

// PHP CLI absolu utilisé pour RE-lancer chaque commande planifiée, au lieu de
// laisser Schedule::command() résoudre lui-même le binaire via Symfony
// PhpExecutableFinder. Sur IONOS, ce finder ne cherche jamais un binaire
// nommé "php8.4" (seulement php/php7/php5/php-cli ou $PHP_BINARY) : même si
// le cron appelle bien "php8.4 artisan schedule:run", la commande enfant
// repart via "/usr/bin/php" — souvent une version incompatible (voir
// storage/logs/laravel.log : exit code 255 sur amana:rappels-imminents).
// SCHEDULE_PHP_BINARY est injecté depuis la même variable IONOS_PHP_CLI_PATH
// que le cron lui-même (voir .github/workflows/deploy.yaml), pour ne
// jamais désynchroniser les deux ; PHP_BINARY reste le repli local (php artisan serve).
$phpPourCron = env('SCHEDULE_PHP_BINARY', PHP_BINARY);
$artisan = base_path('artisan');

// Expire les demandes d'échange de créneaux dont la date est passée
// sans réponse de la personne cible. Envoie une notification au demandeur.
Schedule::exec("{$phpPourCron} {$artisan} amana:expire-echanges")
    ->dailyAt('01:00')
    ->name('amana:expire-echanges');

// Rappels par email pour les créneaux assignés (voir RappelService) — les
// événements Google Calendar eux-mêmes n'ont plus d'attendee/invitation
// (restriction Google pour les comptes de service), ces deux commandes sont
// donc l'unique canal de rappel personnel et ciblé pour les bénévoles.
//
// Fuseau explicite : l'application tourne en UTC (APP_TIMEZONE=UTC en
// production), donc sans ->timezone() « 08:00 » signifierait 08:00 UTC, soit
// 09:00 ou 10:00 à Paris selon la saison. RappelService raisonne déjà en
// Europe/Paris (dates J / J+3, fenêtre « 3h avant ») — le déclenchement doit
// suivre le même fuseau. amana:expire-echanges reste volontairement en UTC :
// il compare `expires_at` à now() (instants absolus), l'heure de passage n'a
// aucune incidence métier.
Schedule::exec("{$phpPourCron} {$artisan} amana:rappels-quotidiens")
    ->dailyAt('08:00')
    ->timezone(DateHelper::FUSEAU_METIER)
    ->name('amana:rappels-quotidiens');
Schedule::exec("{$phpPourCron} {$artisan} amana:rappels-imminents")
    ->everyFifteenMinutes()
    ->timezone(DateHelper::FUSEAU_METIER)
    ->name('amana:rappels-imminents');
