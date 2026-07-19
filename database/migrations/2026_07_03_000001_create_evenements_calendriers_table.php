<?php
// database/migrations/2026_07_03_000001_create_evenements_calendriers_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace l'ancienne colonne unique ref_evenements.calendar_name par cette
 * table ref_evenements_calendriers, permettant d'associer PLUSIEURS
 * calendriers Google Calendar à un même événement (ex : un événement
 * synchronisé à la fois sur "AMANA - Événements" et "AMANA - Communications").
 *
 * Un événement sans ligne ici → pas de synchronisation calendrier demandée.
 *
 * google_calendar_id / google_event_id : suivi direct de l'événement Google
 * Calendar (calendarId + event.id renvoyé à la création), réutilisé tel
 * quel pour events.patch()/events.delete() — pas de recherche par nom/date.
 * Nullable : une ligne peut exister sans google_calendar_id le temps que
 * l'utilisateur resélectionne un calendrier, et google_event_id reste NULL
 * tant que le premier événement Google Calendar n'a pas encore été créé.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ref_evenements_calendriers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_evenement');
            $table->string('calendar_name', 200);
            $table->string('google_calendar_id', 200)->nullable();
            $table->string('google_event_id', 200)->nullable();

            $table->unique(['id_evenement', 'calendar_name'], 'uq_evenements_calendriers');

            $table->foreign('id_evenement')
                ->references('id')->on('ref_evenements')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_evenements_calendriers');
    }
};
