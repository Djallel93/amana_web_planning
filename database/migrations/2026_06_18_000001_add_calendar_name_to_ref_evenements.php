<?php
// database/migrations/2026_06_18_000001_add_calendar_name_to_ref_evenements.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne calendar_name à ref_evenements.
 *
 * Permet de configurer par événement le calendrier Google Calendar
 * cible dans lequel Make.com créera l'événement.
 * Null = pas de synchronisation calendrier demandée.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ref_evenements', function (Blueprint $table) {
            $table->string('calendar_name', 200)->nullable()->after('description')
                ->comment('Nom du calendrier Google Calendar cible — null = pas de synchro');
        });
    }

    public function down(): void
    {
        Schema::table('ref_evenements', function (Blueprint $table) {
            $table->dropColumn('calendar_name');
        });
    }
};
