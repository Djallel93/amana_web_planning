<?php
// database/migrations/2024_01_01_000001_create_base_tables.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : tables de référence (véhicules, rôles, tâches, personnes)
 * Ces tables ne dépendent d'aucune autre — créées en premier.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ref_vehicules ──────────────────────────────────────────────────
        Schema::create('ref_vehicules', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('type', 50);
            $table->unsignedSmallInteger('capacite_kg')->default(0)
                ->comment('Capacité maximale en kilogrammes');
            $table->unsignedSmallInteger('nombre_parts_max')->default(0)
                ->comment('Nombre maximum de parts alimentaires transportables');
        });

        // ── ref_roles ──────────────────────────────────────────────────────
        Schema::create('ref_roles', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('libelle', 100);
        });

        // ── ref_taches ─────────────────────────────────────────────────────
        Schema::create('ref_taches', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('code', 50)->unique()
                ->comment('entree, mektaba, salle, amana_food');
            $table->string('libelle', 100)
                ->comment('Entrée, Médiathèque, Salle, Nourriture');
            $table->boolean('actif')->default(true)
                ->comment('FALSE = archivée, exclue des nouveaux plannings');
        });

        // ── ref_personnes ──────────────────────────────────────────────────
        Schema::create('ref_personnes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 255)->unique();
            $table->string('telephone', 20)->nullable();
            $table->date('date_debut_planning')->nullable()
                ->comment('NULL si la personne n\'est pas membre officiel');
            $table->date('date_inscription_benevole')->nullable()
                ->comment('NULL si la personne n\'est pas bénévole');
            $table->enum('statut', ['En attente', 'Validé', 'Suspendu', 'Archivé'])
                ->default('En attente');
            $table->boolean('tirelire')->default(false);
            $table->unsignedTinyInteger('id_vehicule')->nullable();
            $table->timestamp('derniere_maj')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('id_vehicule')
                ->references('id')->on('ref_vehicules')
                ->onDelete('set null')->onUpdate('cascade');
        });

        // ── ref_personnes_roles ────────────────────────────────────────────
        Schema::create('ref_personnes_roles', function (Blueprint $table) {
            $table->unsignedInteger('id_personne');
            $table->unsignedTinyInteger('id_role');
            $table->date('date_attribution')->default(DB::raw('(curdate())'));

            $table->primary(['id_personne', 'id_role']);

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_role')
                ->references('id')->on('ref_roles')
                ->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_personnes_roles');
        Schema::dropIfExists('ref_personnes');
        Schema::dropIfExists('ref_taches');
        Schema::dropIfExists('ref_roles');
        Schema::dropIfExists('ref_vehicules');
    }
};
