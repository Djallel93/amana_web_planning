<?php
// database/migrations/2026_06_18_000002_create_plan_echanges_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table plan_echanges.
 *
 * Un échange est une demande d'échange de créneau entre deux membres.
 *
 * Cycle de vie :
 *   en_attente → accepte (par B ou par admin/gestionnaire)
 *   en_attente → refuse  (par B)
 *   en_attente → expire  (date du créneau du demandeur passée sans réponse)
 *   en_attente → annule  (demandeur annule sa demande)
 *
 * La table plan_creneaux_taches n'a pas de PK auto-increment —
 * on stocke donc (id_planning + id_tache) séparément pour chaque slot.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_echanges', function (Blueprint $table) {
            $table->increments('id');

            // ── Demandeur (A) ──────────────────────────────────────────────
            $table->unsignedInteger('id_personne_demandeur')
                ->comment('Personne qui demande l\'échange');
            $table->unsignedInteger('id_creneau_demandeur')
                ->comment('id_planning du créneau du demandeur');
            $table->unsignedTinyInteger('id_tache_demandeur')
                ->comment('id_tache du slot du demandeur');

            // ── Cible (B) ──────────────────────────────────────────────────
            $table->unsignedInteger('id_personne_cible')
                ->comment('Personne avec qui échanger');
            $table->unsignedInteger('id_creneau_cible')
                ->comment('id_planning du créneau cible');
            $table->unsignedTinyInteger('id_tache_cible')
                ->comment('id_tache du slot cible');

            // ── Statut & tokens ────────────────────────────────────────────
            $table->enum('statut', ['en_attente', 'accepte', 'refuse', 'expire', 'annule'])
                ->default('en_attente');
            $table->string('token_accept', 64)->unique()
                ->comment('Token URL pour accepter — invalidé après action');
            $table->string('token_refuse', 64)->unique()
                ->comment('Token URL pour refuser — invalidé après action');
            $table->timestamp('expires_at')
                ->comment('Date/heure d\'expiration = date du créneau du demandeur');

            // ── Méta ───────────────────────────────────────────────────────
            $table->unsignedInteger('approuve_par')->nullable()
                ->comment('ID admin/gestionnaire qui a approuvé (si override)');
            $table->timestamps();

            // ── Index ──────────────────────────────────────────────────────
            $table->index('id_personne_demandeur');
            $table->index('id_personne_cible');
            $table->index('statut');
            $table->index('expires_at');

            // ── Clés étrangères ────────────────────────────────────────────
            $table->foreign('id_personne_demandeur')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade');
            $table->foreign('id_personne_cible')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade');
            $table->foreign('id_creneau_demandeur')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade');
            $table->foreign('id_creneau_cible')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade');
            $table->foreign('id_tache_demandeur')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict');
            $table->foreign('id_tache_cible')
                ->references('id')->on('ref_taches')
                ->onDelete('restrict');
            $table->foreign('approuve_par')
                ->references('id')->on('ref_personnes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_echanges');
    }
};
