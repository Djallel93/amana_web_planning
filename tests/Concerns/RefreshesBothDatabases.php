<?php
// tests/Concerns/RefreshesBothDatabases.php
//
// RefreshDatabase de Laravel, adapté aux DEUX bases de l'app :
//   - connexion par défaut : plan_* / ref_taches / ref_evenements… (migrate)
//   - connexion 'commun'   : ref_personnes, ref_roles, audit_logs… (paquet
//     amana/shared — ses migrations ne sont volontairement PAS vues par
//     `migrate`, il faut `amana:migrate-shared`, voir amana_shared/README.md)
//
// Le schéma est (re)créé UNE fois par exécution de la suite, puis chaque test
// tourne dans une transaction sur les deux connexions, annulée à la fin.
//
// Piège connu : un test qui exerce du code faisant lui-même beginTransaction()
// (SchedulerMain) reste correct ici — Laravel imbrique via des SAVEPOINT — mais
// un commit() interne ne persiste rien tant que la transaction externe du test
// n'est pas validée. Voir tests/README.md.

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait RefreshesBothDatabases
{
    use RefreshDatabase;

    /** null = connexion par défaut. */
    protected array $connectionsToTransact = [null, 'commun'];

    protected function refreshTestDatabase(): void
    {
        $this->refuserBasesQuiNeSontPasDeTest();

        if (!RefreshDatabaseState::$migrated) {
            // Artisan::call() et non $this->artisan() : ce dernier passe par PendingCommand, qui
            // exige Mockery — absent des require-dev de ce projet.
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('amana:migrate-shared', ['--fresh' => true, '--force' => true]);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();

        // Enregistré APRÈS le rollback posé par beginDatabaseTransaction() : les rappels
        // de fin de test s'exécutent dans l'ordre d'enregistrement.
        $this->beforeApplicationDestroyed(fn() => $this->reinitialiserAutoIncrementsEtroits());
    }

    /**
     * Un rollback n'annule pas l'AUTO_INCREMENT. Quatre tables ont une clé TINYINT UNSIGNED
     * (max 255) : sans remise à zéro, la suite entière n'en consomme que 255 (ref_taches
     * dès ~30 tests, ref_applications/ref_roles à chaque test qui utilise un rôle) et
     * échoue ensuite avec « Out of range value for column 'id' ». MySQL ramène le
     * compteur à MAX(id)+1, donc les lignes posées par les migrations sont préservées.
     * Le DDL valide implicitement : à n'exécuter qu'une fois la transaction du test fermée.
     */
    private function reinitialiserAutoIncrementsEtroits(): void
    {
        $tables = [
            [null, ['ref_taches']],
            ['commun', ['ref_applications', 'ref_roles', 'ref_settings']],
        ];

        foreach ($tables as [$connexion, $noms]) {
            $db = DB::connection($connexion);
            if ($db->transactionLevel() > 0) {
                continue;
            }
            foreach ($noms as $table) {
                $db->statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
            }
        }
    }

    /**
     * Deuxième filet, en plus du force="true" de phpunit.xml : `migrate:fresh`
     * SUPPRIME toutes les tables, on refuse donc de continuer si une des deux
     * connexions ne vise pas une base dont le nom finit par _test.
     */
    private function refuserBasesQuiNeSontPasDeTest(): void
    {
        foreach ([config('database.default'), 'commun'] as $connexion) {
            $base = (string) config("database.connections.{$connexion}.database");

            if (!str_ends_with($base, '_test')) {
                throw new RuntimeException(
                    "Refus de lancer les tests : la connexion '{$connexion}' vise '{$base}', "
                    . 'qui ne finit pas par _test. Voir phpunit.xml et tests/README.md.'
                );
            }
        }
    }
}
