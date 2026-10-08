<?php
// app/Services/PlanningGenerationNotifier.php

declare(strict_types=1);

namespace App\Services;

use Amana\Shared\Models\Personne as PersonneBase;
use App\Models\Personne;
use App\Notifications\PlanningGenereNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prévient les admins et gestionnaires du planning qu'une génération vient d'avoir
 * lieu. Point d'appel unique pour les trois chemins qui écrivent le planning :
 *
 *   - génération manuelle     → PlanningController::generate()
 *   - régénération (absence)  → AbsenceRegenerationService
 *   - régénération (événement)→ EvenementRegenerationService
 *
 * Volontairement HORS de SchedulerMain::generateSchedule() : celle-ci sert aussi à la
 * prévisualisation (dry-run), qui ne doit rien envoyer, et regenerateFromImpactedDate()
 * l'appelle déjà — notifier là-dedans doublerait les emails.
 *
 * Aucune méthode ici ne lève : l'envoi est un effet de bord, jamais une raison de
 * faire échouer (ou d'annuler) une génération déjà validée en base. Chaque
 * destinataire est traité séparément — une adresse en échec n'empêche pas les autres.
 */
class PlanningGenerationNotifier
{
    /**
     * Génération manuelle depuis Planning > Générer.
     *
     * @param array{jours_generes: int, non_assignes: int} $resultat Retour de SchedulerMain::generateSchedule()
     */
    public function notifierGenerationManuelle(Carbon $premierVendredi, int $semaines, array $resultat): void
    {
        $this->securise(fn() => $this->envoyer(
            PlanningGenereNotification::DECLENCHEUR_MANUEL,
            null,
            $premierVendredi->copy(),
            $premierVendredi->copy()->addWeeks(max(1, $semaines) - 1)->addDay(),
            $resultat,
        ));
    }

    /**
     * Régénération automatique.
     *
     * @param string $declencheur PlanningGenereNotification::DECLENCHEUR_ABSENCE | DECLENCHEUR_EVENEMENT
     * @param string $detail      Complète « suite à … » (ex. « l'événement « Ramadan » »)
     * @param array{resultat: array, dateDebutRegen: string, semaines: int, regenererDepuis: Carbon} $regen
     *        Retour de SchedulerMain::regenerateFromImpactedDate()
     */
    public function notifierRegeneration(string $declencheur, string $detail, array $regen): void
    {
        $this->securise(fn() => $this->envoyer(
            $declencheur,
            $detail,
            $regen['regenererDepuis']->copy(),
            Carbon::parse($regen['dateDebutRegen'])->addWeeks(max(1, $regen['semaines']) - 1)->addDay(),
            $regen['resultat'],
        ));
    }

    /** Exécute `$action` en garantissant qu'aucune exception ne remonte à l'appelant. */
    private function securise(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $e) {
            Log::error('[PlanningGenerationNotifier] Échec de la notification de génération', [
                'erreur' => $e->getMessage(),
                'fichier' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    /**
     * @param array{jours_generes: int, non_assignes: int} $resultat
     */
    private function envoyer(string $declencheur, ?string $detail, Carbon $debut, Carbon $fin, array $resultat): void
    {
        $destinataires = Personne::adminsEtGestionnairesPlanning()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        $acteur = Auth::user();
        $contexte = [
            'declencheur' => $declencheur,
            'detail' => $detail,
            'acteur' => $acteur instanceof PersonneBase ? trim($acteur->prenom . ' ' . $acteur->nom) : null,
            'debut' => $debut,
            'fin' => $fin,
            'jours_generes' => (int) $resultat['jours_generes'],
            'non_assignes' => (int) $resultat['non_assignes'],
            'quand' => Carbon::now('Europe/Paris'),
        ];

        $envoyes = 0;
        foreach ($destinataires as $destinataire) {
            try {
                $destinataire->notify(new PlanningGenereNotification($contexte));
                $envoyes++;
            } catch (Throwable $e) {
                Log::error('[PlanningGenerationNotifier] Échec d\'envoi', [
                    'destinataire_id' => $destinataire->id,
                    'declencheur' => $declencheur,
                    'erreur' => $e->getMessage(),
                    'fichier' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }

        Log::info('[PlanningGenerationNotifier] Notification de génération envoyée', [
            'declencheur' => $declencheur,
            'envoyes' => $envoyes,
            'destinataires' => $destinataires->count(),
        ]);
    }
}
