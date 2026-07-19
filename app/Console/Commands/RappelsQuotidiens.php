<?php
// app/Console/Commands/RappelsQuotidiens.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RappelService;
use Illuminate\Console\Command;

/**
 * Commande planifiée : envoie les rappels "3 jours avant" et "jour J" pour
 * toutes les tâches assignées du jour concerné.
 *
 * À enregistrer dans routes/console.php :
 *   Schedule::command('amana:rappels-quotidiens')->dailyAt('08:00');
 */
class RappelsQuotidiens extends Command
{
    protected $signature = 'amana:rappels-quotidiens';
    protected $description = 'Envoie les rappels par email "3 jours avant" et "jour J" pour les créneaux assignés.';

    public function __construct(
        private readonly RappelService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Envoi des rappels quotidiens (3 jours avant / jour J)…');

        $resultats = $this->service->envoyerRappelsQuotidiens();

        $this->components->info("3 jours avant : {$resultats['3_jours']} rappel(s) envoyé(s).");
        $this->components->info("Jour J : {$resultats['jour_j']} rappel(s) envoyé(s).");

        return Command::SUCCESS;
    }
}
