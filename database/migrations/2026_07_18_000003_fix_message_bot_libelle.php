<?php
// database/migrations/2026_07_18_000003_fix_message_bot_libelle.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige une incohérence de données de seed : ref_taches.code='message_bot'
 * avait pour libelle 'Message Général', alors que toutes les autres tâches
 * suivent la convention "libellé dérivé du code" (ex. annonce_cours →
 * "Annonce Cours") — et ref_settings.calendar_message_bot.libelle utilisait
 * déjà "Message Bot" pour la même tâche. Le libelle de ref_taches est celui
 * effectivement utilisé comme titre de l'événement Google Calendar (voir
 * WebhookPayloadBuilder::ligneAvecAssignation()), d'où l'écart observé.
 *
 * DatabaseSeeder.php corrigé en parallèle pour les futures installations —
 * cette migration corrige les bases déjà seedées (locale, IONOS).
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('ref_taches')
            ->where('code', 'message_bot')
            ->update(['libelle' => 'Message Bot']);
    }

    public function down(): void
    {
        DB::table('ref_taches')
            ->where('code', 'message_bot')
            ->update(['libelle' => 'Message Général']);
    }
};
