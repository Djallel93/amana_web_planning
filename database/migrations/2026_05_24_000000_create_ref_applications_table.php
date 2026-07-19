<?php
// database/migrations/2026_05_24_000000_create_ref_applications_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Référence toutes les applications du système AMANA partageant cette base
 * de données (planning, livraisons, tirelire, familles, benevoles...).
 *
 * Chaque rôle dans ref_roles est lié à une application spécifique,
 * permettant à une même personne d'avoir des rôles différents selon l'app.
 * Créée en tout premier : ref_roles (id_application) et audit_logs
 * (id_application) en dépendent toutes deux dès leur création.
 */
return new class extends Migration {
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
