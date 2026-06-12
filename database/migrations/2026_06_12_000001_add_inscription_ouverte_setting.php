<?php
// database/migrations/2026_06_12_000001_add_inscription_ouverte_setting.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le paramètre `inscription_ouverte` dans ref_settings pour
 * les déploiements existants qui ont déjà joué les migrations précédentes.
 *
 * Idempotente : utilise updateOrInsert — sans effet si la clé existe déjà.
 */
return new class extends Migration {
    public function up(): void
    {
        $planningId = DB::table('ref_applications')
            ->where('code', 'planning')
            ->value('id');

        if (!$planningId) {
            return; // Application pas encore créée (fresh install via seeder)
        }

        DB::table('ref_settings')->updateOrInsert(
            ['id_application' => $planningId, 'cle' => 'inscription_ouverte'],
            [
                'valeur' => '1',
                'type' => 'boolean',
                'libelle' => 'Inscriptions ouvertes',
                'description' => "Active ou désactive le formulaire public d'inscription (/inscription). Seuls les administrateurs peuvent modifier ce paramètre.",
            ]
        );
    }

    public function down(): void
    {
        $planningId = DB::table('ref_applications')
            ->where('code', 'planning')
            ->value('id');

        if (!$planningId) {
            return;
        }

        DB::table('ref_settings')
            ->where('id_application', $planningId)
            ->where('cle', 'inscription_ouverte')
            ->delete();
    }
};