<?php
// database/migrations/2026_05_28_000004_refactor_auth_add_application_to_ref_roles.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Étape 4 du refactoring auth.
 *
 * Ajoute id_application dans ref_roles pour lier chaque rôle
 * à une application spécifique.
 *
 * Les rôles existants (admin, membre, benevole) sont mis à jour
 * pour pointer vers l'application 'planning' (id=1).
 *
 * Nouveaux rôles ajoutés pour planning :
 *   - admin   : accès complet
 *   - membre  : accès lecture + gestion de ses propres données
 *
 * Note : le rôle 'benevole' existant est conservé et rattaché à planning
 * pour compatibilité, mais sera principalement utilisé par l'app 'benevoles'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ref_roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id_application')->nullable()->after('libelle')
                ->comment('Application à laquelle ce rôle appartient');

            $table->foreign('id_application')
                ->references('id')->on('ref_applications')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // Récupérer l'id de l'application planning
        $planningId = DB::table('ref_applications')->where('code', 'planning')->value('id');

        // Mettre à jour les rôles existants pour les rattacher à planning
        DB::table('ref_roles')->update(['id_application' => $planningId]);

        // Rendre la colonne non nullable maintenant que les données sont à jour
        Schema::table('ref_roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id_application')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ref_roles', function (Blueprint $table) {
            $table->dropForeign(['id_application']);
            $table->dropColumn('id_application');
        });
    }
};
