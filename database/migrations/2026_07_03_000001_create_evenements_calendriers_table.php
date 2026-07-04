<?php
// database/migrations/2026_07_03_000001_create_evenements_calendriers_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace la colonne unique ref_evenements.calendar_name par une table
 * ref_evenements_calendriers, permettant d'associer PLUSIEURS calendriers
 * Google Calendar à un même événement (ex : un événement synchronisé à la
 * fois sur "AMANA - Événements" et "AMANA - Communications").
 *
 * Un événement sans ligne ici → pas de synchronisation calendrier demandée
 * (équivalent de l'ancien calendar_name = null).
 *
 * Environnement encore en développement : pas de migration de données
 * existantes, on droppe directement l'ancienne colonne.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ref_evenements', function (Blueprint $table) {
            $table->dropColumn('calendar_name');
        });

        Schema::create('ref_evenements_calendriers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_evenement');
            $table->string('calendar_name', 200);

            $table->unique(['id_evenement', 'calendar_name'], 'uq_evenements_calendriers');

            $table->foreign('id_evenement')
                ->references('id')->on('ref_evenements')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_evenements_calendriers');

        Schema::table('ref_evenements', function (Blueprint $table) {
            $table->string('calendar_name', 200)->nullable()->after('description')
                ->comment('Nom du calendrier Google Calendar cible — null = pas de synchro');
        });
    }
};
