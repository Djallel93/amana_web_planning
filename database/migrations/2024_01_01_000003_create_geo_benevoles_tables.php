<?php
// database/migrations/2024_01_01_000003_create_geo_benevoles_tables.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ── geo_villes ─────────────────────────────────────────────────────
        Schema::create('geo_villes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 100);
            $table->string('code_postal', 10)->nullable()
                ->comment('Ex: 44800');
            $table->string('departement', 100)->nullable()
                ->comment('Ex: Loire-Atlantique');
        });
        // Ajout de la colonne geometry séparément car Blueprint ne supporte
        // pas nativement le type MySQL GEOMETRY
        DB::statement('ALTER TABLE geo_villes ADD COLUMN polygon geometry NULL');

        // ── geo_secteurs ───────────────────────────────────────────────────
        Schema::create('geo_secteurs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 100);
            $table->unsignedInteger('id_ville');

            $table->unique(['nom', 'id_ville'], 'unique_secteur_nom_ville');

            $table->foreign('id_ville')
                ->references('id')->on('geo_villes')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── geo_quartiers ──────────────────────────────────────────────────
        Schema::create('geo_quartiers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 100);
            $table->unsignedInteger('id_secteur');

            $table->foreign('id_secteur')
                ->references('id')->on('geo_secteurs')
                ->onDelete('cascade')->onUpdate('cascade');
        });
        DB::statement('ALTER TABLE geo_quartiers ADD COLUMN polygon geometry NULL');

        // ── benv_disponibilites ────────────────────────────────────────────
        Schema::create('benv_disponibilites', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_personne');
            $table->unsignedInteger('id_evenement');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->text('remarques')->nullable();
            $table->timestamp('derniere_maj')->useCurrent()->useCurrentOnUpdate();

            $table->index(['id_personne', 'id_evenement'], 'idx_dispo_personne_event');

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_evenement')
                ->references('id')->on('ref_evenements')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── benv_couverture ────────────────────────────────────────────────
        Schema::create('benv_couverture', function (Blueprint $table) {
            $table->unsignedInteger('id_personne');
            $table->unsignedInteger('id_quartier');
            $table->unsignedInteger('id_evenement');
            $table->enum('type_couverture', ['Livraison', 'Collecte', 'Les deux'])
                ->default('Livraison');
            $table->text('remarques')->nullable();
            $table->timestamp('derniere_maj')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['id_personne', 'id_quartier', 'id_evenement']);

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_evenement')
                ->references('id')->on('ref_evenements')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_quartier')
                ->references('id')->on('geo_quartiers')
                ->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benv_couverture');
        Schema::dropIfExists('benv_disponibilites');
        Schema::dropIfExists('geo_quartiers');
        Schema::dropIfExists('geo_secteurs');
        Schema::dropIfExists('geo_villes');
    }
};