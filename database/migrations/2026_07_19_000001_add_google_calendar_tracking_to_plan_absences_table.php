<?php
// database/migrations/2026_07_19_000001_add_google_calendar_tracking_to_plan_absences_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le suivi de synchronisation Google Calendar à plan_absences.
 *
 * Contrairement à ref_evenements (table pivot ref_evenements_calendriers,
 * car un événement peut être synchronisé sur PLUSIEURS calendriers), une
 * absence n'est jamais synchronisée que sur UN SEUL calendrier cible (voir
 * paramètre `calendar_absence`) — une simple paire de colonnes directement
 * sur plan_absences suffit donc, pas besoin de table pivot dédiée.
 *
 * google_calendar_id : calendarId Google Calendar cible au moment de la
 *   synchronisation (copie de la valeur du paramètre `calendar_absence` à
 *   cet instant — permet de retrouver le bon calendrier pour un
 *   update/delete même si le paramètre a changé depuis).
 * google_event_id : renseigné après la première création réussie côté
 *   Google Calendar (voir SynchroniserGoogleCalendar), réutilisé tel quel
 *   pour les mises à jour/suppressions suivantes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('plan_absences', function (Blueprint $table) {
            $table->string('google_calendar_id', 200)->nullable()->after('raison');
            $table->string('google_event_id', 200)->nullable()->after('google_calendar_id');
        });
    }

    public function down(): void
    {
        Schema::table('plan_absences', function (Blueprint $table) {
            $table->dropColumn(['google_calendar_id', 'google_event_id']);
        });
    }
};
