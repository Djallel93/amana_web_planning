<?php
// app/Helpers/GoogleCalendarColors.php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Palette de couleurs événement Google Calendar (colorId '1' à '11' — la
 * palette est fixe côté Google, aucune couleur personnalisée possible).
 * Référence : https://developers.google.com/calendar/api/v3/reference/colors
 *
 * Deux usages :
 *   - PALETTE : pour peupler le <select> couleur du formulaire événement
 *     (evenements/form.blade.php) et retrouver un libellé lisible.
 *   - TACHES  : mapping fixe code ref_taches → colorId, aligné sur les
 *     couleurs chip-* déjà utilisées dans l'UI (public/css/custom.css),
 *     pour qu'un même code ait une couleur cohérente dans l'app ET sur
 *     Google Calendar. Voir WebhookPayloadBuilder::COULEURS.
 */
class GoogleCalendarColors
{
    /** @var array<string, array{nom: string, hex: string}> */
    public const PALETTE = [
        '1'  => ['nom' => 'Lavande',    'hex' => '#7986cb'],
        '2'  => ['nom' => 'Sauge',      'hex' => '#33b679'],
        '3'  => ['nom' => 'Raisin',     'hex' => '#8e24aa'],
        '4'  => ['nom' => 'Flamant',    'hex' => '#e67c73'],
        '5'  => ['nom' => 'Banane',     'hex' => '#f6bf26'],
        '6'  => ['nom' => 'Mandarine',  'hex' => '#f4511e'],
        '7'  => ['nom' => 'Paon',       'hex' => '#039be5'],
        '8'  => ['nom' => 'Graphite',   'hex' => '#616161'],
        '9'  => ['nom' => 'Myrtille',   'hex' => '#3f51b5'],
        '10' => ['nom' => 'Basilic',    'hex' => '#0b8043'],
        '11' => ['nom' => 'Tomate',     'hex' => '#d50000'],
    ];

    /**
     * Mapping code ref_taches → colorId, aligné sur les couleurs chip-*
     * (public/css/custom.css) des 5 tâches principales :
     *   entree → bleu, mektaba → vert, salle → ambre, amana_food → rouge,
     *   cours → violet. Les codes secondaires (webhook uniquement) reçoivent
     *   une couleur distincte de leur tâche parente pour rester repérables.
     *
     * @var array<string, string>
     */
    /**
     * Couleur fixe (non configurable) utilisée pour TOUTES les absences
     * synchronisées sur Google Calendar — colorId 8 = Graphite (gris),
     * cf. PALETTE ci-dessus. Contrairement à TACHES, aucun paramètre
     * `couleur_absence` n'existe : le gris est imposé, pas un défaut.
     */
    public const ABSENCE = '8';

    public const TACHES = [
        'entree'                 => '7',  // Paon (bleu)     — aligné chip-entree
        'mektaba'                => '10', // Basilic (vert)  — aligné chip-mektaba
        'salle'                  => '5',  // Banane (ambre)  — aligné chip-salle
        'amana_food'             => '11', // Tomate (rouge)  — aligné chip-amana_food
        'cours'                  => '3',  // Raisin (violet) — aligné chip-cours
        'rappel_sandwich'        => '6',  // Mandarine — distinct d'amana_food
        'assistance_amana_food'  => '9',  // Myrtille — distinct d'entree
        'annonce_cours'          => '8',  // Graphite — neutre, annonce sociale
        'message_bot'            => '1',  // Lavande — informatif
        'annulation_cours'       => '4',  // Flamant — alerte, distinct de amana_food
    ];
}
