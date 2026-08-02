<?php
// app/Console/Commands/ExpirerEchanges.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EchangeService;
use Illuminate\Console\Command;

/**
 * Commande planifiée : expire les échanges en attente dont la date est passée.
 *
 * À enregistrer dans routes/console.php ou bootstrap/app.php :
 *   Schedule::command('amana:expire-echanges')->dailyAt('01:00');
 */
class ExpirerEchanges extends Command
{
    protected $signature   = 'amana:expire-echanges';
    protected $description = 'Expire les demandes d\'échange de créneaux dont la date est passée sans réponse.';

    public function __construct(
        private readonly EchangeService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('Vérification des échanges expirés…');

        $nb = $this->service->expirerEchanges();

        if ($nb === 0) {
            $this->components->info('Aucun échange expiré.');
        } else {
            $this->components->warn("{$nb} échange(s) expiré(s) — notifications envoyées.");
        }

        return Command::SUCCESS;
    }
}
