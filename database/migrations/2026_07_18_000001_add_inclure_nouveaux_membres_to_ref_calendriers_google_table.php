<?php
// database/migrations/2026_07_18_000001_add_inclure_nouveaux_membres_to_ref_calendriers_google_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne inclure_nouveaux_membres à ref_calendriers_google.
 *
 * Quand un admin valide une candidature (Admin\CandidaturesController::valider()),
 * l'application partage automatiquement — via l'API Google Calendar (Acl::insert)
 * — chaque calendrier actif ayant cette colonne à true avec l'adresse email du
 * nouveau bénévole, avec un niveau d'accès dépendant du rôle attribué (voir
 * CalendarSharingService). Par défaut à false : ajouter un calendrier au
 * registre ne l'inclut pas automatiquement dans ce partage, un admin/gestionnaire
 * doit l'activer explicitement depuis /parametres.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ref_calendriers_google', function (Blueprint $table) {
            $table->boolean('inclure_nouveaux_membres')->default(false)->after('actif');
        });
    }

    public function down(): void
    {
        Schema::table('ref_calendriers_google', function (Blueprint $table) {
            $table->dropColumn('inclure_nouveaux_membres');
        });
    }
};
