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
 * connectés (pas de notion de propriétaire). `date` est donc unique.
 *
 * ── Deux groupes indépendants ───────────────────────────────────────────────
 * Amana food et Présences ont chacun leurs propres colonnes de méta
 * (id_personne_maj_*, maj_*_at) et sont enregistrés séparément (upsert par
 * groupe), pour que deux personnes puissent éditer les deux groupes en
 * parallèle sans que l'une écrase les valeurs de l'autre avec une copie
 * obsolète.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_bilans_quotidiens', function (Blueprint $table) {
            $table->increments('id');

            $table->date('date')->unique('uq_bilans_date');

            // ── Amana food ────────────────────────────────────────────────
            // Saisie et méta séparées de Présences : deux personnes différentes
            // peuvent éditer chaque groupe en parallèle, chacune avec son
            // propre bouton d'enregistrement — pas d'upsert global qui
            // écraserait l'autre groupe avec des valeurs obsolètes.
            //
            // NULL = pas de cours ce jour-là (valeur volontairement absente,
            // jamais saisie, ou réinitialisée) ; 0 = un cours a bien eu lieu
            // et la valeur réelle est zéro. Les deux cas doivent rester
            // distinguables — d'où l'absence de valeur par défaut.
            $table->decimal('montant_carte', 8, 2)->nullable()->default(null)
                ->comment('Montant collecté par carte bancaire — NULL = pas de cours ce jour-là');
            $table->decimal('montant_espece', 8, 2)->nullable()->default(null)
                ->comment('Montant collecté en espèces — NULL = pas de cours ce jour-là');
            $table->unsignedInteger('id_personne_maj_food')->nullable()
                ->comment('Dernière personne ayant modifié le groupe Amana food');
            $table->timestamp('maj_food_at')->nullable()
                ->comment('Date de dernière modification du groupe Amana food');

            // ── Présences ─────────────────────────────────────────────────
            $table->unsignedSmallInteger('nb_presents')->nullable()->default(null)
                ->comment('Nombre de personnes présentes sur place — NULL = pas de cours ce jour-là');
            $table->unsignedSmallInteger('nb_en_ligne')->nullable()->default(null)
                ->comment('Nombre de personnes connectées en ligne — NULL = pas de cours ce jour-là');
            $table->unsignedInteger('id_personne_maj_presence')->nullable()
                ->comment('Dernière personne ayant modifié le groupe Présences');
            $table->timestamp('maj_presence_at')->nullable()
                ->comment('Date de dernière modification du groupe Présences');

            $table->timestamps();

            $table->foreign('id_personne_maj_food')
                ->references('id')->on('ref_personnes')
                ->onDelete('set null');
            $table->foreign('id_personne_maj_presence')
                ->references('id')->on('ref_personnes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_bilans_quotidiens');
    }
};
