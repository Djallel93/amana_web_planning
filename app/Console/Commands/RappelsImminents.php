<?php
// app/Console/Commands/RappelsImminents.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RappelService;
use Illuminate\Console\Command;

/**
 * Commande planifiée : envoie les rappels "3h avant" pour les tâches
 * assignées dont l'heure de début réelle (variable par tâche, ex.
 * rappel_sandwich = 08:00 fixe, les autres tâches ~20:00) approche.
 *
 * Exécutée fréquemment (toutes les 15 min) car l'heure de début diffère
 * d'une tâche à l'autre — contrairement à RappelsQuotidiens qui n'a besoin
 * de tourner qu'une fois par jour à heure fixe.
 *
 * À enregistrer dans routes/console.php :
 *   Schedule::command('amana:rappels-imminents')->everyFifteenMinutes();
 */
class RappelsImminents extends Command
{
    protected $signature = 'amana:rappels-imminents';
    protected $description = 'Envoie les rappels par email "3h avant" pour les créneaux assignés dont l\'heure de début approche.';

    public function __construct(
        private readonly RappelService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $nb = $this->service->envoyerRappelsImminents();

        if ($nb === 0) {
            $this->components->info('Aucun rappel "3h avant" à envoyer pour le moment.');
        } else {
            $this->components->info("{$nb} rappel(s) \"3h avant\" envoyé(s).");
        }

        return Command::SUCCESS;
    }
}
