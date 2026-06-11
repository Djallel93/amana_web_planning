<?php
// database/migrations/2024_01_01_000002_create_planning_tables.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : tables du planning, des événements, restrictions et absences.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── ref_evenements ─────────────────────────────────────────────────
        Schema::create('ref_evenements', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nom', 150);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->text('description')->nullable();

            $table->index(['date_debut', 'date_fin'], 'idx_evenements_dates');
        });

        // ── plan_absences ──────────────────────────────────────────────────
        Schema::create('plan_absences', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_personne');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('raison', 255)->nullable();

            $table->index('id_personne', 'idx_absences_personne');
            $table->index(['date_debut', 'date_fin'], 'idx_absences_dates');

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── plan_creneaux ──────────────────────────────────────────────────
        Schema::create('plan_creneaux', function (Blueprint $table) {
            $table->increments('id');
            $table->date('date')->unique('uq_planning_date');
        });

        // ── plan_creneaux_evenements ───────────────────────────────────────
        Schema::create('plan_creneaux_evenements', function (Blueprint $table) {
            $table->unsignedInteger('id_planning');
            $table->unsignedInteger('id_evenement');

            $table->primary(['id_planning', 'id_evenement']);

            $table->foreign('id_planning')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_evenement')
                ->references('id')->on('ref_evenements')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        // ── plan_creneaux_taches ───────────────────────────────────────────
        Schema::create('plan_creneaux_taches', function (Blueprint $table) {
            $table->unsignedInteger('id_planning');
            $table->unsignedTinyInteger('id_tache');
            $table->unsignedInteger('id_personne')->nullable()
                ->comment('NULL = tâche non assignée');

            $table->primary(['id_planning', 'id_tache']);

            $table->foreign('id_planning')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('set null')->onUpdate('cascade');
        });

        // ── plan_restrictions ──────────────────────────────────────────────
        Schema::create('plan_restrictions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_personne');
            $table->unsignedTinyInteger('id_tache');
            $table->enum('jour', ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']);
            $table->boolean('autorise')->default(true);

            $table->unique(['id_personne', 'id_tache', 'jour'], 'uq_restrictions');

            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_restrictions');
        Schema::dropIfExists('plan_creneaux_taches');
        Schema::dropIfExists('plan_creneaux_evenements');
        Schema::dropIfExists('plan_creneaux');
        Schema::dropIfExists('plan_absences');
        Schema::dropIfExists('ref_evenements');
    }
};