<?php
// app/Http/Controllers/RestrictionsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Personne;
use App\Models\Restriction;
use App\Models\Tache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Contrôleur pour la grille de restrictions (personne × tâche × jour).
 */
class RestrictionsController extends Controller
{
    // Jours affichés dans la grille (uniquement vendredi et samedi pour AMANA)
    const JOURS = ['Vendredi', 'Samedi'];

    /**
     * Affiche la grille de cases à cocher.
     */
    public function index(): View
    {
        $personnes = Personne::actifAuPlanning()
            ->with(['restrictions.tache'])
            ->orderBy('nom')
            ->get();

        $taches = Tache::actif()->orderBy('id')->get();

        // Construire une map indexée pour la vue : [id_personne][id_tache][jour] = bool
        $restrictionsMap = [];
        foreach ($personnes as $personne) {
            foreach ($taches as $tache) {
                foreach (self::JOURS as $jour) {
                    // Par défaut : autorisé (si aucune ligne en base)
                    $restrictionsMap[$personne->id][$tache->id][$jour] = true;
                }
            }
            foreach ($personne->restrictions as $restriction) {
                $restrictionsMap[$personne->id][$restriction->id_tache][$restriction->jour]
                    = $restriction->autorise;
            }
        }

        return view('restrictions.index', compact('personnes', 'taches', 'restrictionsMap'));
    }

    /**
     * Sauvegarde toute la grille de restrictions.
     * La vue envoie un tableau checkboxes[id_personne][id_tache][jour] = "1".
     * Si la case n'est pas cochée, la clé est absente du POST → autorise = false.
     */
    public function update(Request $request): RedirectResponse
    {
        $personnes = Personne::actifAuPlanning()->get();
        $taches    = Tache::actif()->get();

        // Validation basique : s'assurer que checkboxes est un tableau si présent
        $request->validate([
            'checkboxes' => ['nullable', 'array'],
        ]);

        $checkboxes = $request->input('checkboxes', []);

        DB::transaction(function () use ($personnes, $taches, $checkboxes) {
            foreach ($personnes as $personne) {
                foreach ($taches as $tache) {
                    foreach (self::JOURS as $jour) {
                        $autorise = isset($checkboxes[$personne->id][$tache->id][$jour]);

                        Restriction::updateOrCreate(
                            [
                                'id_personne' => $personne->id,
                                'id_tache'    => $tache->id,
                                'jour'        => $jour,
                            ],
                            ['autorise' => $autorise]
                        );
                    }
                }
            }
        });

        audit('update', 'restrictions', null, null, ['message' => 'Grille de restrictions mise à jour']);

        return redirect()->route('restrictions.index')
            ->with('success', 'Restrictions mises à jour avec succès.');
    }
}
