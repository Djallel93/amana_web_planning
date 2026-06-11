<?php
// database/migrations/2026_05_28_000006_create_ref_settings_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table ref_settings.
 *
 * Stocke les paramètres de configuration par application AMANA.
 * Toujours filtrer par id_application lors de la lecture.
 *
 * Exemples de clés pour l'application planning :
 *   heure_cours, lieu, inscription_ouverte,
 *   offset_entree_debut, offset_entree_fin, …
 *   calendar_entree, calendar_mektaba, …
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ref_settings', function (Blueprint $table) {
            $table->tinyIncrements('id');

            $table->unsignedTinyInteger('id_application')->nullable()
                ->comment('NULL = paramètre global, sinon lié à une application');

            $table->string('cle', 100)
                ->comment('Identifiant technique du paramètre (ex: heure_cours)');

            $table->string('valeur', 500)
                ->comment('Valeur stockée sous forme de chaîne, castée à la lecture');

            $table->enum('type', ['string', 'integer', 'time', 'boolean'])
                ->default('string')
                ->comment('Type de casting appliqué à la valeur lors de la lecture');

            $table->string('libelle', 200)
                ->comment('Label lisible affiché dans l\'UI (ex: Heure du cours)');

            $table->text('description')->nullable()
                ->comment('Description longue optionnelle pour l\'aide contextuelle');

            $table->unique(['id_application', 'cle'], 'uq_settings_app_cle');

            $table->foreign('id_application')
                ->references('id')->on('ref_applications')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_settings');
    }
};