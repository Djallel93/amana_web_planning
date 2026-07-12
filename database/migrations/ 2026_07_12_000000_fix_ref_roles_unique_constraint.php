<?php
// database/migrations/2026_07_12_000000_fix_ref_roles_unique_constraint.php
//
// Corrige un oubli de la migration 2026_05_28_000004
// (refactor_auth_add_application_to_ref_roles) : celle-ci a ajouté la
// colonne id_application à ref_roles pour scoper chaque rôle par
// application, mais n'a jamais mis à jour la contrainte unique posée sur
// `code` en 2026_05_24_000001 (create_base_tables). Cette contrainte est
// restée globale (ref_roles_code_unique = UNIQUE(code) tout court), alors
// que le code applicatif (cf. register_familles_application) suppose déjà
// une unicité scopée par application.
//
// Conséquence concrète : impossible de créer un rôle 'admin' pour une
// deuxième application (ex. 'familles') puisque 'admin' existe déjà pour
// 'planning' — violation SQLSTATE 23000 sur ref_roles_code_unique.
//
// Cette migration doit s'exécuter AVANT toute migration qui insère des
// rôles pour une application autre que 'planning' (d'où le timestamp
// 2026_07_12_000000, antérieur à 2026_07_12_000001_register_familles_application).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Nom de la contrainte unique composite créée par cette migration.
     * Suit la convention déjà utilisée ailleurs dans le projet
     * (uq_settings_app_cle, uq_evenements_calendriers, uq_restrictions...).
     */
    private const NOUVEL_INDEX = 'uq_roles_code_app';

    public function up(): void
    {
        // Garde-fou : si des doublons (code, id_application) existaient déjà
        // pour une raison quelconque, la création de l'index composite
        // échouerait silencieusement avec une erreur SQL peu lisible.
        // On les détecte explicitement pour donner un message clair.
        $doublons = DB::table('ref_roles')
            ->select('code', 'id_application', DB::raw('COUNT(*) as total'))
            ->groupBy('code', 'id_application')
            ->having('total', '>', 1)
            ->get();

        if ($doublons->isNotEmpty()) {
            throw new \RuntimeException(
                'Impossible de corriger la contrainte unique de ref_roles : '
                . 'des doublons (code, id_application) existent déjà : '
                . $doublons->map(fn($d) => "{$d->code}/{$d->id_application}")->implode(', ')
            );
        }

        Schema::table('ref_roles', function (Blueprint $table) {
            // Supprime l'ancienne contrainte globale héritée de l'époque
            // mono-application (nom généré par défaut par Laravel :
            // {table}_{colonne}_unique).
            $table->dropUnique('ref_roles_code_unique');

            // Un même code de rôle (admin, membre...) redevient possible
            // d'une application à l'autre, tant qu'il reste unique au sein
            // d'une même application.
            $table->unique(['code', 'id_application'], self::NOUVEL_INDEX);
        });
    }

    public function down(): void
    {
        // Le retour arrière n'est possible que s'il n'existe qu'une seule
        // application utilisant des rôles à ce moment-là (ex. rollback
        // effectué juste après le déploiement, avant l'enregistrement de
        // Familles). Si plusieurs applications partagent déjà un même code
        // (admin pour planning ET familles), revenir à une unicité globale
        // provoquerait la même violation qu'à l'origine du bug.
        $codesPartages = DB::table('ref_roles')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(DISTINCT id_application) > 1')
            ->pluck('code');

        if ($codesPartages->isNotEmpty()) {
            throw new \RuntimeException(
                'Rollback impossible : les codes de rôle suivants sont '
                . 'partagés par plusieurs applications et redeviendraient '
                . 'des doublons sous une contrainte unique globale : '
                . $codesPartages->implode(', ')
            );
        }

        Schema::table('ref_roles', function (Blueprint $table) {
            $table->dropUnique(self::NOUVEL_INDEX);
            $table->unique('code', 'ref_roles_code_unique');
        });
    }
};