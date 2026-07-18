<?php
// database/migrations/2026_07_16_000002_create_plan_calendrier_evenements_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de suivi des événements Google Calendar créés pour le planning.
 *
 * Remplace la résolution "par nom + date" faite côté Make.com par un suivi
 * exact de l'event_id Google Calendar renvoyé à la création, pour que les
 * modifications/suppressions ultérieures appellent directement
 * events.patch(calendarId, eventId) / events.delete(calendarId, eventId).
 *
 * Pourquoi une table dédiée plutôt qu'ajouter des colonnes à
 * plan_creneaux_taches :
 * Sur les 10 codes de tâches (`ref_taches`) qui peuvent produire un
 * événement calendrier pour un créneau donné, seuls 5 sont "assignables"
 * et possèdent une ligne dans plan_creneaux_taches (entree, mektaba, salle,
 * amana_food, cours). Les 5 autres (rappel_sandwich, assistance_amana_food,
 * annonce_cours, message_bot, annulation_cours) sont calculés à la volée à
 * chaque construction de payload (WebhookPayloadBuilder) et n'ont jamais de
 * ligne dédiée dans plan_creneaux_taches — il n'y a donc pas de colonne
 * commune sur laquelle accrocher google_event_id pour ces 5 codes.
 *
 * Une ligne = un (créneau, code de tâche, calendrier Google) → un event_id.
 * Un même (créneau, tâche) peut apparaître plusieurs fois si demain
 * plusieurs calendriers sont configurés pour un même code (aujourd'hui un
 * seul, voir Setting::get("calendar_{code}")) — d'où l'unicité sur le
 * triplet plutôt que sur (id_planning, id_tache) seul.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_calendrier_evenements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_planning');
            $table->unsignedTinyInteger('id_tache');
            $table->string('google_calendar_id', 200);
            $table->string('google_event_id', 200);
            $table->timestamps();

            $table->unique(
                ['id_planning', 'id_tache', 'google_calendar_id'],
                'uq_plan_calendrier_evenements'
            );

            $table->foreign('id_planning')
                ->references('id')->on('plan_creneaux')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_tache')
                ->references('id')->on('ref_taches')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_calendrier_evenements');
    }
};
