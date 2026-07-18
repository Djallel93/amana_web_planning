<?php
// database/migrations/2026_07_03_000002_add_annulation_cours_settings.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute la tâche "annulation_cours" (inactive, webhook uniquement — même
 * traitement que annonce_cours / message_bot) ainsi que ses paramètres
 * (calendrier cible + décalages horaires) pour le bouton "Annulation cours"
 * du planning.
 *
 * Idempotent : peut être rejouée sans créer de doublons (updateOrInsert).
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('ref_taches')->updateOrInsert(
            ['code' => 'annulation_cours'],
            ['libelle' => 'Annulation Cours', 'actif' => false, 'description' => '']
        );

        $idApp = DB::table('ref_applications')->where('code', 'planning')->value('id');

        if (!$idApp) {
            return;
        }

        $settings = [
            [
                'cle' => 'offset_annulation_cours_debut',
                'valeur' => '-360',
                'type' => 'integer',
                'libelle' => 'Annulation cours : début (min)',
                'description' => null,
            ],
            [
                'cle' => 'offset_annulation_cours_fin',
                'valeur' => '-345',
                'type' => 'integer',
                'libelle' => 'Annulation cours : fin (min)',
                'description' => null,
            ],
            [
                'cle' => 'calendar_annulation_cours',
                'valeur' => '',
                'type' => 'string',
                'libelle' => 'Annulation Cours',
                'description' => null,
            ],
        ];

        foreach ($settings as $s) {
            DB::table('ref_settings')->updateOrInsert(
                ['id_application' => $idApp, 'cle' => $s['cle']],
                [
                    'valeur' => $s['valeur'],
                    'type' => $s['type'],
                    'libelle' => $s['libelle'],
                    'description' => $s['description'],
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('ref_taches')->where('code', 'annulation_cours')->delete();

        $idApp = DB::table('ref_applications')->where('code', 'planning')->value('id');
        if ($idApp) {
            DB::table('ref_settings')
                ->where('id_application', $idApp)
                ->whereIn('cle', [
                    'offset_annulation_cours_debut',
                    'offset_annulation_cours_fin',
                    'calendar_annulation_cours',
                ])
                ->delete();
        }
    }
};
