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
