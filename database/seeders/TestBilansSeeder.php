<?php
// database/seeders/TestBilansSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bilan;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Personne;
use App\Models\Tache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeder de données de test pour le développement local de la page
 * Bilan / Statistiques.
 *
 * Génère, pour les vendredis et samedis des NB_SEMAINES dernières semaines
 * (jusqu'à aujourd'hui inclus) :
 *   - un plan_creneaux (s'il n'existe pas déjà pour cette date)
 *   - une assignation amana_food + mektaba (seulement si non déjà assignée,
 *     pour ne jamais écraser un vrai planning existant)
 *   - un plan_bilans_quotidiens avec des montants/effectifs réalistes
 *
 * Idempotent : peut être relancé sans créer de doublons (updateOrCreate /
 * firstOrCreate partout), et ne modifie jamais une assignation de tâche
 * déjà existante.
 *
 * Prérequis : au moins quelques personnes "Validé" en base — lancez d'abord
 * TestPersonnesSeeder si besoin.
 *
 * Utilisation :
 *   php artisan db:seed --class=TestBilansSeeder
 */
class TestBilansSeeder extends Seeder
{
    private const NB_SEMAINES = 12;
    private const JOURS = [5, 6]; // Carbon: 5 = Vendredi, 6 = Samedi

    public function run(): void
    {
        $personnes = Personne::where('statut', 'Validé')->get();

        if ($personnes->count() < 2) {
            $this->command->warn(
                'Pas assez de personnes "Validé" en base — lancez d\'abord TestPersonnesSeeder.'
            );
            return;
        }

        $taches = Tache::whereIn('code', ['amana_food', 'mektaba'])->get()->keyBy('code');

        if ($taches->count() < 2) {
            $this->command->warn(
                'Tâches amana_food / mektaba introuvables — lancez d\'abord DatabaseSeeder.'
            );
            return;
        }

        $dates = $this->datesRotation();

        $this->command->info('Génération de bilans de test pour ' . count($dates) . ' date(s)…');

        $nbCreneauxCrees = 0;
        $nbAssignationsCrees = 0;
        $nbBilansCrees = 0;

        foreach ($dates as $date) {
            $creneau = Creneau::firstOrCreate(['date' => $date]);
            if ($creneau->wasRecentlyCreated) {
                $nbCreneauxCrees++;
            }

            foreach ($taches as $code => $tache) {
                $existant = CreneauTache::where('id_planning', $creneau->id)
                    ->where('id_tache', $tache->id)
                    ->first();

                // On ne touche jamais à une assignation déjà présente
                // (réelle ou générée lors d'un run précédent).
                if ($existant && $existant->id_personne !== null) {
                    continue;
                }

                CreneauTache::updateOrCreate(
                    ['id_planning' => $creneau->id, 'id_tache' => $tache->id],
                    ['id_personne' => $personnes->random()->id]
                );
                $nbAssignationsCrees++;
            }

            $bilanExiste = Bilan::whereDate('date', $date)->exists();

            $bilan = Bilan::updateOrCreate(
                ['date' => $date],
                [
                    'montant_carte'            => fake()->randomFloat(2, 40, 220),
                    'montant_espece'           => fake()->randomFloat(2, 15, 130),
                    'id_personne_maj_food'     => $personnes->random()->id,
                    'maj_food_at'              => now(),
                    'nb_presents'              => fake()->numberBetween(20, 70),
                    'nb_en_ligne'              => fake()->numberBetween(0, 20),
                    'id_personne_maj_presence' => $personnes->random()->id,
                    'maj_presence_at'          => now(),
                ]
            );

            if (!$bilanExiste) {
                $nbBilansCrees++;
            }
        }

        $this->command->info("✅ Créneaux créés : {$nbCreneauxCrees}");
        $this->command->info("✅ Assignations amana_food/mektaba créées : {$nbAssignationsCrees}");
        $this->command->info("✅ Bilans créés/mis à jour : {$nbBilansCrees} nouveaux / " . count($dates) . ' total');
        $this->command->newLine();
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  Consultez /bilan/statistiques pour voir les données générées.');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /**
     * Retourne les dates (Vendredi/Samedi) des NB_SEMAINES dernières
     * semaines, jusqu'à aujourd'hui inclus, triées chronologiquement.
     *
     * @return list<string>
     */
    private function datesRotation(): array
    {
        $dates = [];
        $curseur = Carbon::today()->subWeeks(self::NB_SEMAINES)->startOfWeek();
        $fin = Carbon::today();

        while ($curseur->lte($fin)) {
            if (in_array($curseur->dayOfWeek, self::JOURS, true)) {
                $dates[] = $curseur->toDateString();
            }
            $curseur->addDay();
        }

        return $dates;
    }
}
