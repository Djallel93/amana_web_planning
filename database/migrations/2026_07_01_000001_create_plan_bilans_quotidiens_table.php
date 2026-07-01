<?php
// database/migrations/2026_07_01_000001_create_plan_bilans_quotidiens_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table plan_bilans_quotidiens.
 *
 * Un bilan quotidien regroupe, pour une date donnée, les montants Amana food
 * (carte bancaire / espèces) et les effectifs Présences (présents / en ligne).
 *
 * ── Un seul enregistrement partagé par date ────────────────────────────────
 * Contrairement à plan_absences (une ligne par personne), il n'existe qu'UN
 * SEUL bilan par date — visible et modifiable par tous les utilisateurs
 * connectés (pas de notion de propriétaire). `date` est donc unique, et
 * chaque sauvegarde fait un upsert (updateOrCreate) plutôt qu'un insert.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_bilans_quotidiens', function (Blueprint $table) {
            $table->increments('id');

            $table->date('date')->unique('uq_bilans_date');

            // ── Amana food ────────────────────────────────────────────────
            $table->decimal('montant_carte', 8, 2)->default(0)
                ->comment('Montant collecté par carte bancaire');
            $table->decimal('montant_espece', 8, 2)->default(0)
                ->comment('Montant collecté en espèces');

            // ── Présences ─────────────────────────────────────────────────
            $table->unsignedSmallInteger('nb_presents')->default(0)
                ->comment('Nombre de personnes présentes sur place');
            $table->unsignedSmallInteger('nb_en_ligne')->default(0)
                ->comment('Nombre de personnes connectées en ligne');

            // ── Méta ──────────────────────────────────────────────────────
            $table->unsignedInteger('id_personne_maj')->nullable()
                ->comment('Dernière personne ayant modifié ce bilan');
            $table->timestamps();

            $table->foreign('id_personne_maj')
                ->references('id')->on('ref_personnes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_bilans_quotidiens');
    }
};
