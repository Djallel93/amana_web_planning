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

// Expire les demandes d'échange de créneaux dont la date est passée
// sans réponse de la personne cible. Envoie une notification au demandeur.
Schedule::command('amana:expire-echanges')->dailyAt('01:00');

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
Schedule::command('amana:rappels-quotidiens')
    ->dailyAt('08:00')
    ->timezone(DateHelper::FUSEAU_METIER);
Schedule::command('amana:rappels-imminents')
    ->everyFifteenMinutes()
    ->timezone(DateHelper::FUSEAU_METIER);
