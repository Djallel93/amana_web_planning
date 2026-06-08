<?php
// app/Http/Controllers/SettingsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Contrôleur pour les paramètres de l'application planning.
 *
 * Accès : gestionnaire + admin uniquement (middleware 'role:gestionnaire').
 *
 * Routes :
 *   GET  /parametres  → index()   Affiche tous les paramètres groupés
 *   POST /parametres  → update()  Sauvegarde un ou tous les paramètres
 */
class SettingsController extends Controller
{
    /** Code de l'application ciblée */
    private const APP_CODE = 'planning';

    /**
     * Affiche tous les paramètres planning groupés par catégorie.
     */
    public function index(): View
    {
        $settings = Setting::allForApp(self::APP_CODE);

        // ── Groupement par catégorie pour l'affichage ──────────────────────
        //
        // horaires     → heure_cours, lieu
        // decalages    → tous les offset_*
        //
        $horaires = $settings->only(['heure_cours', 'lieu']);
        $decalages = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'offset_'));

        // Libellés lisibles pour chaque groupe de décalages
        // Regroupés par tâche : debut + fin sur la même ligne
        $decalagesGroupes = $this->grouperDecalages($decalages);

        return view('settings.index', compact('horaires', 'decalages', 'decalagesGroupes', 'settings'));
    }

    /**
     * Sauvegarde les paramètres soumis via le formulaire.
     *
     * Accepte un tableau 'settings' avec les clés comme noms de champs :
     *   settings[heure_cours] = '20:30'
     *   settings[offset_entree_debut] = '-30'
     *   etc.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        $settingsInput = $request->input('settings', []);

        // ── Charger l'état avant modification pour l'audit ─────────────────
        $avant = Setting::allForApp(self::APP_CODE)
            ->map(fn($s) => $s['valeur_raw'])
            ->toArray();

        // ── Récupérer l'id_application une seule fois ──────────────────────
        $idApp = DB::table('ref_applications')
            ->where('code', self::APP_CODE)
            ->value('id');

        if (!$idApp) {
            return redirect()->route('settings.index')
                ->with('error', 'Application planning introuvable.');
        }

        // ── Mise à jour de chaque paramètre soumis ─────────────────────────
        $apres = [];
        foreach ($settingsInput as $cle => $valeur) {
            // Sécurité : ne mettre à jour que des clés existantes
            $existe = DB::table('ref_settings')
                ->where('id_application', $idApp)
                ->where('cle', $cle)
                ->exists();

            if (!$existe) {
                continue;
            }

            $valeur = trim((string) $valeur);

            DB::table('ref_settings')
                ->where('id_application', $idApp)
                ->where('cle', $cle)
                ->update(['valeur' => $valeur]);

            $apres[$cle] = $valeur;
        }

        // ── Audit ──────────────────────────────────────────────────────────
        audit('update', 'settings', null, $avant, $apres);

        return redirect()->route('settings.index')
            ->with('success', 'Paramètres enregistrés avec succès.');
    }

    // ── Helpers privés ─────────────────────────────────────────────────────

    /**
     * Regroupe les décalages par tâche pour affichage sur une seule ligne.
     *
     * Retourne une collection indexée par nom de tâche :
     * [
     *   'entree' => [
     *     'libelle' => 'Entrée',
     *     'debut'   => ['cle' => 'offset_entree_debut', 'valeur_raw' => '-30', ...],
     *     'fin'     => ['cle' => 'offset_entree_fin',   'valeur_raw' => '30', ...],
     *   ],
     *   ...
     * ]
     */
    private function grouperDecalages(\Illuminate\Support\Collection $decalages): array
    {
        // Mapping code → libelle lisible
        $libelles = [
            'entree' => 'Entrée',
            'mektaba' => 'Mektaba',
            'salle' => 'Salle',
            'amana_food' => 'Amana Food',
            'cours' => 'Cours',
            'rappel_sandwich' => 'Rappel Sandwich',
            'assistance_amana_food' => 'Assistance Amana Food',
            'annonce_cours' => 'Annonce Cours',
            'message_bot' => 'Message Bot',
        ];

        $groupes = [];

        foreach ($decalages as $cle => $data) {
            // Extraire le code de tâche depuis la clé
            // ex: offset_entree_debut → entree + debut
            //     offset_amana_food_debut → amana_food + debut
            //     offset_assistance_amana_food_debut → assistance_amana_food + debut
            if (preg_match('/^offset_(.+)_(debut|fin)$/', $cle, $m)) {
                $codeTache = $m[1]; // ex: 'entree', 'amana_food', 'annonce_cours'
                $sens = $m[2]; // 'debut' ou 'fin'

                if (!isset($groupes[$codeTache])) {
                    $groupes[$codeTache] = [
                        'libelle' => $libelles[$codeTache] ?? $codeTache,
                        'debut' => null,
                        'fin' => null,
                    ];
                }

                $groupes[$codeTache][$sens] = array_merge(
                    ['cle' => $cle],
                    $data
                );
            }
        }

        return $groupes;
    }
}