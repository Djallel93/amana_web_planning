<?php
// routes/console.php

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
Schedule::command('amana:rappels-quotidiens')->dailyAt('08:00');
Schedule::command('amana:rappels-imminents')->everyFifteenMinutes();
