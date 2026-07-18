<?php
// database/migrations/2026_07_17_000001_create_ref_calendriers_google_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des calendriers Google Calendar connus de l'application.
 *
 * Pourquoi cette table : un compte de service Google N'A PAS de "Calendar
 * List" comme un utilisateur humain — calendarList.list() renvoie
 * systématiquement une liste vide pour un compte de service, MÊME quand des
 * calendriers lui ont été partagés individuellement et qu'il peut
 * parfaitement les lire/écrire via calendars.get()/events.insert()/etc.
 * C'est documenté par Google lui-même :
 * https://developers.google.com/workspace/calendar/api/concepts/sharing
 * ("Sharing a calendar with a user no longer automatically inserts the
 * calendar into their CalendarList.") — et confirmé par de nombreux
 * rapports d'implémentation (dont Google Issue Tracker #148804709).
 *
 * Il n'existe donc AUCUN moyen fiable de découvrir automatiquement, par
 * appel API, la liste des calendriers partagés avec le compte de service.
 * La découverte automatique (ancien GoogleCalendarService::listCalendars()
 * basé sur calendarList.list()) est remplacée par ce registre géré
 * manuellement : un administrateur ajoute chaque calendrier (ID copié
 * depuis Google Calendar → Paramètres → Intégrer l'agenda), l'application
 * valide l'accès via calendars.get($calendarId) au moment de
 * l'enregistrement, puis sert cette liste (sans appel API) à tous les
 * dropdowns de sélection de calendrier (/api/calendriers).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ref_calendriers_google', function (Blueprint $table) {
            $table->increments('id');
            $table->string('calendar_id', 200)->unique();
            $table->string('nom', 200);
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamp('derniere_verification_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_calendriers_google');
    }
};
