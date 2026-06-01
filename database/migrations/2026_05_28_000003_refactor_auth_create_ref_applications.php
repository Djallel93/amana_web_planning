<?php
// database/migrations/2026_05_28_000003_refactor_auth_create_ref_applications.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Étape 3 du refactoring auth.
 *
 * Crée la table ref_applications qui référence toutes les applications
 * du système AMANA partageant cette base de données.
 *
 * Chaque rôle dans ref_roles sera lié à une application spécifique,
 * permettant à une même personne d'avoir des rôles différents selon l'app.
 *
 * Exemples :
 *   admin     sur planning
 *   livreur   sur livraisons
 *   tresorier sur tirelire
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_applications', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)->unique()
                ->comment('Identifiant technique : planning, livraisons, tirelire, familles, benevoles');
            $table->string('libelle', 100)
                ->comment('Nom lisible : AMANA Planning, Livraisons, etc.');
            $table->boolean('actif')->default(true);
        });

        // Insérer l'application planning d'emblée
        DB::table('ref_applications')->insert([
            'code'    => 'planning',
            'libelle' => 'AMANA Planning',
            'actif'   => true,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_applications');
    }
};
