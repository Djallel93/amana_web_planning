<?php
// database/migrations/2026_07_14_000001_add_description_calendrier_to_ref_taches_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne description_calendrier à ref_taches.
 *
 * Jusqu'ici, une seule colonne `description` servait à la fois :
 *   - côté app (formulaire d'inscription, page Disponibilités) : un
 *     résumé court, à destination des membres, de ce que couvre la tâche ;
 *   - côté webhook Make.com (champ `description` envoyé dans le body de
 *     l'événement Google Calendar) : un texte plus long/formaté, à
 *     destination de la personne assignée ce jour-là.
 *
 * Ces deux usages n'ont pas le même public ni le même format (l'un est une
 * phrase de présentation générale, l'autre un texte d'instructions parfois
 * long avec puces/emojis) — on les sépare donc en deux colonnes :
 *   - `description`            : reste le texte affiché dans l'app (inchangé).
 *   - `description_calendrier` : nouveau, texte envoyé dans le body de
 *                                 l'événement Google Calendar via le webhook.
 *                                 Nullable — un texte vide n'empêche pas la
 *                                 création de l'événement calendrier.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ref_taches', function (Blueprint $table) {
            $table->text('description_calendrier')->nullable()->after('description')
                ->comment('Texte envoyé dans le body de l\'événement Google Calendar (webhook Make.com) — distinct de la description affichée dans l\'app');
        });
    }

    public function down(): void
    {
        Schema::table('ref_taches', function (Blueprint $table) {
            $table->dropColumn('description_calendrier');
        });
    }
};
