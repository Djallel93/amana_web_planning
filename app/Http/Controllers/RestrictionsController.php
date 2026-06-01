<?php
// app/Http/Controllers/RestrictionsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Personne;
use App\Models\Restriction;
use App\Models\Tache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Contrôleur pour les restrictions de disponibilité.
 *
 * Règles d'accès :
 *   - Admin  : voit et modifie toutes les restrictions (grille complète)
 *   - Membre : voit toutes les restrictions en lecture seule,
 *              ne peut modifier que les siennes via un formulaire dédié
 */
class RestrictionsController extends Controller
{
    const JOURS = ['Vendredi', 'Samedi'];

    /**
     * Affiche la grille de restrictions.
     *
     * Admin  : grille complète modifiable
     * Membre : grille complète en lecture + formulaire pour ses propres restrictions
     */
    public function index(): View
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $personnes = Personne::actifAuPlanning()
            ->with(['restrictions.tache'])
            ->orderBy('nom')
            ->get();

        $taches = Tache::actif()->orderBy('id')->get();

        // Construire la map de restrictions [id_personne][id_tache][jour] = bool
        $restrictionsMap = [];
        foreach ($personnes as $personne) {
            foreach ($taches as $tache) {
                foreach (self::JOURS as $jour) {
                    $restrictionsMap[$personne->id][$tache->id][$jour] = true;
                }
            }
            foreach ($personne->restrictions as $restriction) {
                $restrictionsMap[$personne->id][$restriction->id_tache][$restriction->jour]
                    = $restriction->autorise;
            }
        }

        return view('restrictions.index', compact(
            'personnes',
            'taches',
            'restrictionsMap',
            'user'
        ));
    }

    /**
     * Sauvegarde les restrictions.
     *
     * Admin  : peut modifier toutes les restrictions de toutes les personnes.
     * Membre : ne peut modifier que ses propres restrictions.
     *          On ignore les données envoyées pour d'autres personnes.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var \App\Models\Personne $user */
        $user   = Auth::user();
        $taches = Tache::actif()->get();

        $request->validate([
            'checkboxes' => ['nullable', 'array'],
        ]);

        $checkboxes = $request->input('checkboxes', []);

        if ($user->isAdmin()) {
            // ── Admin : mise à jour de toutes les personnes ────────────────
            $personnes = Personne::actifAuPlanning()->get();

            DB::transaction(function () use ($personnes, $taches, $checkboxes) {
                foreach ($personnes as $personne) {
                    $this->saveRestrictionsForPersonne(
                        $personne->id,
                        $taches,
                        $checkboxes
                    );
                }
            });

            audit('update', 'restrictions', null, null, [
                'message' => 'Grille complète mise à jour par admin',
            ]);

            return redirect()->route('restrictions.index')
                ->with('success', 'Restrictions mises à jour avec succès.');

        } else {
            // ── Membre : mise à jour de ses propres restrictions uniquement ─
            // Sécurité : on force l'ID de la personne connectée,
            // quelle que soit la valeur envoyée dans le formulaire.
            DB::transaction(function () use ($user, $taches, $checkboxes) {
                $this->saveRestrictionsForPersonne(
                    $user->id,
                    $taches,
                    $checkboxes
                );
            });

            audit('update', 'restrictions', $user->id, null, [
                'message' => 'Restrictions personnelles mises à jour',
            ]);

            return redirect()->route('restrictions.index')
                ->with('success', 'Vos disponibilités ont été mises à jour.');
        }
    }

    /**
     * Enregistre les restrictions d'une personne donnée.
     * Méthode privée partagée entre admin et membre.
     *
     * @param int        $personneId
     * @param \Illuminate\Support\Collection $taches
     * @param array      $checkboxes  [id_personne][id_tache][jour] = "1"
     */
    private function saveRestrictionsForPersonne(
        int $personneId,
        $taches,
        array $checkboxes
    ): void {
        foreach ($taches as $tache) {
            foreach (self::JOURS as $jour) {
                $autorise = isset($checkboxes[$personneId][$tache->id][$jour]);

                Restriction::updateOrCreate(
                    [
                        'id_personne' => $personneId,
                        'id_tache'    => $tache->id,
                        'jour'        => $jour,
                    ],
                    ['autorise' => $autorise]
                );
            }
        }
    }
}
