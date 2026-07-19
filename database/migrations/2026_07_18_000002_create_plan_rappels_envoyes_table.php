<?php
// database/migrations/2026_07_18_000002_create_plan_rappels_envoyes_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de suivi des rappels par email déjà envoyés pour un (créneau, tâche,
 * personne, type de rappel) — évite les doublons si une commande planifiée
 * est relancée manuellement, ou si sa fenêtre d'exécution chevauche une
 * exécution précédente (voir RappelService::envoyerRappelsImminents()).
 *
 * Trois types de rappel (colonne `type_rappel`, voir RappelService) :
 *   - '3_jours'  : envoyé 3 jours avant la date du créneau, à 08:00
 *   - 'jour_j'   : envoyé le jour même du créneau, à 08:00
 *   - '3h_avant' : envoyé ~3h avant l'heure de début réelle de la tâche
 *                  (variable selon la tâche — voir RappelsImminents, exécutée
 *                  toutes les 15 min)
 *
 * `id_tache` référence ref_taches.id (comme plan_calendrier_evenements) —
 * couvre aussi bien les 5 tâches assignables (plan_creneaux_taches) que les
 * tâches dépendantes calculées à la volée (rappel_sandwich,
 * assistance_amana_food), qui n'ont pas de ligne dédiée ailleurs.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_rappels_envoyes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_planning');
            $table->unsignedTinyInteger('id_tache');
            $table->unsignedInteger('id_personne');
            $table->string('type_rappel', 20);
            $table->timestamp('envoye_at');

            $table->unique(
                ['id_planning', 'id_tache', 'id_personne', 'type_rappel'],
                'uq_plan_rappels_envoyes'
            );

            $table->foreign('id_planning')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_personne')
                ->references('id')->on('ref_personnes')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_rappels_envoyes');
    }
};
