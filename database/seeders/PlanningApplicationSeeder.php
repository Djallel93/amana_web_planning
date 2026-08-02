<?php
// database/seeders/PlanningApplicationSeeder.php
//
// amana_web_planning n'avait historiquement pas besoin de s'auto-enregistrer
// (ref_applications/ref_roles vivaient déjà dans sa propre base, créées
// avec les données existantes). Maintenant que ces tables vivent dans
// amana_commun (voir amana/shared) et sont partagées par plusieurs apps,
// planning doit s'enregistrer explicitement, comme familles le fait déjà
// (voir FamillesApplicationSeeder dans amana_web_familles).
//
// À exécuter après `php artisan amana:migrate-shared`, AVANT tout autre
// seeder qui dépend de ref_applications/ref_roles (ex. la création du
// premier compte admin) :
//
//   php artisan db:seed --class=Database\\Seeders\\PlanningApplicationSeeder
//
// Idempotent — peut être relancé sans risque.

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanningApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $commun = DB::connection(config('amana-shared.connection', 'commun'));

        $dejaPresent = $commun->table('ref_applications')->where('code', 'planning')->exists();

        if (!$dejaPresent) {
            $commun->table('ref_applications')->insert([
                'code' => 'planning',
                'libelle' => 'AMANA Planning',
                'actif' => true,
            ]);
        }

        $planningId = $commun->table('ref_applications')->where('code', 'planning')->value('id');

        // Pas de rôle 'benevole' pour planning — Personne::isBenevole() du
        // modèle partagé retombe sur isAdmin()||isGestionnaire() en son
        // absence, ce qui reste correct pour cette app.
        $roles = [
            ['code' => 'admin', 'libelle' => 'Administrateur'],
            ['code' => 'gestionnaire', 'libelle' => 'Gestionnaire'],
            ['code' => 'membre', 'libelle' => 'Membre'],
        ];

        foreach ($roles as $role) {
            $existe = $commun->table('ref_roles')
                ->where('id_application', $planningId)
                ->where('code', $role['code'])
                ->exists();

            if (!$existe) {
                $commun->table('ref_roles')->insert([
                    'code' => $role['code'],
                    'libelle' => $role['libelle'],
                    'id_application' => $planningId,
                ]);
            }
        }

        $this->command?->info("Application 'planning' + rôles enregistrés dans amana_commun.");
    }
}
