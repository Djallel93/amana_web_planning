<?php
// database/migrations/2026_09_16_000001_drop_shadow_commun_tables.php
//
// Supprime de la base LOCALE du planning les copies fantômes des tables
// qui appartiennent à amana_commun (paquet amana/shared) :
//   ref_applications, ref_personnes, ref_roles, ref_personnes_roles,
//   ref_settings, audit_logs
//
// Ces tables étaient créées par create_base_tables / create_ref_tables /
// create_audit_logs_table, antérieurs à l'extraction du paquet partagé.
// Vides ou périmées, elles captaient silencieusement toute requête non
// explicitement routée vers la connexion 'commun' (règles de validation
// exists:/unique:, DB::table() nu, modèles sans getConnectionName()) — au
// lieu de lever une erreur. Leurs Schema::create() ont été retirés des
// migrations d'origine dans le même commit, donc `migrate:fresh` ne les
// recrée plus ; cette migration nettoie les bases existantes.
//
// PRÉREQUIS (même commit) : plus aucun code applicatif ne lit/écrit ces
// tables en local — App\Models\{Setting,AuditLog,Role,Application} sont
// supprimés, AuthController utilise Amana\Shared\Models\Setting, et
// AuditHelper/Admin\AuditLogController utilisent Amana\Shared\Models\AuditLog.
//
// password_reset_tokens N'EST PAS concernée : config/auth.php pointe déjà
// le broker 'personnes' sur la connexion commun, mais Laravel recrée /
// utilise la table locale dans certains flux de test ; elle est inoffensive
// et reste gérée par create_base_tables.php.
//
// Avant suppression, le contenu de chaque table est exporté en JSON dans
// storage/app/backups/ — filet de sécurité pour les entrées historiques de
// audit_logs (journal local d'avant le basculement vers le journal partagé)
// et pour d'éventuelles valeurs de ref_settings éditées en local.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Ordre imposé par les clés étrangères locales :
     * ref_personnes_roles → ref_roles/ref_personnes → ref_applications,
     * et ref_settings → ref_applications.
     */
    private const TABLES = [
        'audit_logs',
        'ref_settings',
        'ref_personnes_roles',
        'ref_roles',
        'ref_personnes',
        'ref_applications',
    ];

    public function up(): void
    {
        $this->assertNotCommunDatabase();

        $dossier = storage_path('app/backups');

        if (!is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        $horodatage = date('Ymd_His');

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $this->exporter($table, "{$dossier}/shadow_{$table}_{$horodatage}.json");

            Schema::disableForeignKeyConstraints();
            Schema::drop($table);
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Irréversible volontairement : recréer ces tables en local
        // ressusciterait exactement le bug que cette migration corrige.
        // Les données exportées restent disponibles dans storage/app/backups/.
        throw new RuntimeException(
            'Migration non réversible : les tables partagées vivent dans amana_commun. '
            . 'Voir storage/app/backups/ pour le contenu exporté avant suppression.'
        );
    }

    /**
     * Garde-fou : refuse de s'exécuter si la connexion par défaut pointe sur
     * la même base physique que la connexion 'commun'. Dans ce cas les
     * tables « fantômes » SERAIENT les vraies tables partagées, et les
     * supprimer détruirait les données de tout l'écosystème AMANA.
     */
    private function assertNotCommunDatabase(): void
    {
        $communConnexion = config('amana-shared.connection', 'commun');

        if (!config("database.connections.{$communConnexion}")) {
            throw new RuntimeException(
                "Connexion '{$communConnexion}' non configurée — migration interrompue par sécurité."
            );
        }

        $baseLocale = DB::connection()->getDatabaseName();
        $baseCommun = DB::connection($communConnexion)->getDatabaseName();

        if ($baseLocale === $baseCommun) {
            throw new RuntimeException(
                "La connexion par défaut et la connexion '{$communConnexion}' pointent toutes deux sur "
                . "'{$baseLocale}' : les tables visées sont les vraies tables partagées. "
                . 'Corrigez la configuration avant de relancer cette migration.'
            );
        }
    }

    /**
     * Écrit le contenu de la table dans un fichier JSON lines, par lots,
     * pour ne pas charger un audit_logs volumineux en mémoire.
     */
    private function exporter(string $table, string $chemin): void
    {
        $handle = fopen($chemin, 'w');

        if ($handle === false) {
            throw new RuntimeException("Impossible d'écrire la sauvegarde {$chemin}.");
        }

        try {
            // chunk() exige un tri déterministe ; ref_personnes_roles n'a pas
            // de colonne id, on prend donc la première colonne de la table.
            $colonneTri = Schema::getColumnListing($table)[0] ?? null;

            if ($colonneTri === null) {
                return;
            }

            DB::table($table)->orderBy($colonneTri)->chunk(500, function ($lignes) use ($handle): void {
                foreach ($lignes as $ligne) {
                    fwrite($handle, json_encode($ligne, JSON_UNESCAPED_UNICODE) . PHP_EOL);
                }
            });
        } finally {
            fclose($handle);
        }
    }
};
