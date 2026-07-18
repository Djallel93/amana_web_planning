<?php
// database/migrations/2026_07_16_000001_add_google_calendar_fields_to_ref_evenements_calendriers.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le suivi direct Google Calendar à ref_evenements_calendriers.
 *
 * Remplace le mécanisme Make.com (retrouver l'événement Google Calendar par
 * recherche nom + date, à chaque modification/suppression) par un suivi
 * exact :
 *   - google_calendar_id : identifiant Google Calendar cible (calendarId),
 *     remplace calendar_name comme valeur réellement envoyée à l'API — 
 *     calendar_name est conservé uniquement comme libellé d'affichage.
 *   - google_event_id : identifiant de l'événement Google Calendar
 *     (event.id) renvoyé à la création, réutilisé tel quel pour
 *     events.patch()/events.delete() — plus de recherche par nom/date.
 *
 * Les deux colonnes sont nullables : une ligne peut exister sans
 * google_calendar_id le temps que l'utilisateur resélectionne un calendrier
 * dans le nouveau dropdown (résolu par ID), et google_event_id reste NULL
 * tant que le premier événement Google Calendar n'a pas encore été créé.
 *
 * Environnement encore en développement : pas de backfill des lignes
 * existantes (créées par l'ancien flux Make.com) — elles resteront avec
 * google_calendar_id/google_event_id à NULL et seront simplement
 * retraitées comme une création au prochain upsert.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ref_evenements_calendriers', function (Blueprint $table) {
            $table->string('google_calendar_id', 200)->nullable()->after('calendar_name');
            $table->string('google_event_id', 200)->nullable()->after('google_calendar_id');
        });
    }

    public function down(): void
    {
        Schema::table('ref_evenements_calendriers', function (Blueprint $table) {
            $table->dropColumn(['google_calendar_id', 'google_event_id']);
        });
    }
};
