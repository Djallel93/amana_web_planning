<?php
// app/Console/Commands/DiagnosticRole.php
//
// Commande de diagnostic temporaire — dump ce que l'application voit
// réellement à l'exécution pour une personne donnée (connexion, base,
// app_code, rôles, résultats des méthodes isX()). À supprimer une fois
// le diagnostic terminé.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Personne;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnosticRole extends Command
{
    protected $signature = 'diagnostic:role {email}';

    protected $description = 'Affiche la résolution des rôles telle que vue par l\'application pour un email donné';

    public function handle(): int
    {
        $email = $this->argument('email');

        $this->line('');
        $this->info('── Configuration ────────────────────────────────');
        $this->line('amana-shared.connection : ' . var_export(config('amana-shared.connection'), true));
        $this->line('amana-shared.app_code   : ' . var_export(config('amana-shared.app_code'), true));
        $this->line('db.default              : ' . var_export(config('database.default'), true));

        $connName = config('amana-shared.connection', 'commun');
        $this->line('');
        $this->info('── Bases réellement atteintes ───────────────────');
        $this->line('connexion "' . $connName . '" -> base : '
            . DB::connection($connName)->getDatabaseName());
        $this->line('connexion par défaut      -> base : '
            . DB::connection()->getDatabaseName());

        // Résolution telle que le login la fait (provider Eloquent -> first())
        $personne = Personne::where('email', $email)->first();

        if (!$personne) {
            $this->error('Aucune personne trouvée pour cet email.');
            return self::FAILURE;
        }

        $this->line('');
        $this->info('── Personne résolue (comme au login) ────────────');
        $this->line('id      : ' . $personne->id);
        $this->line('nom     : ' . $personne->prenom . ' ' . $personne->nom);
        $this->line('statut  : ' . var_export($personne->statut, true));
        $this->line('connexion du modèle : ' . var_export($personne->getConnectionName(), true));
        $this->line('nb lignes pour cet email : '
            . Personne::where('email', $email)->count());

        $this->line('');
        $this->info('── Rôles vus par la relation roles() ────────────');
        $roles = $personne->roles()->with('application')->get();

        if ($roles->isEmpty()) {
            $this->warn('AUCUN rôle retourné par la relation.');
        }

        foreach ($roles as $r) {
            $this->line(sprintf(
                'role.code=%s | role.id=%d | app.code=%s | app.id=%s',
                $r->code,
                $r->id,
                $r->application->code ?? '(application introuvable)',
                $r->application->id ?? '?'
            ));
        }

        $this->line('');
        $this->info('── Méthodes de rôle ─────────────────────────────');
        $this->line('isAdmin()        : ' . var_export($personne->isAdmin(), true));
        $this->line('isGestionnaire() : ' . var_export($personne->isGestionnaire(), true));
        $this->line('isMembre()       : ' . var_export($personne->isMembre(), true));
        $this->line('isBenevole()     : ' . var_export($personne->isBenevole(), true));
        $this->line('');

        return self::SUCCESS;
    }
}
