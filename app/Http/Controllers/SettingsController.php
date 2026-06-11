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
    private const APP_CODE = 'planning';

    /**
     * Affiche tous les paramètres planning groupés par catégorie.
     */
    public function index(): View
    {
        $settings = Setting::allForApp(self::APP_CODE);

        // ── Groupes ────────────────────────────────────────────────────────
        $horaires = $settings->only(['heure_cours', 'lieu']);
        $decalages = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'offset_'));

        $decalagesGroupes = $this->grouperDecalages($decalages);

        // ── Inscription ────────────────────────────────────────────────────
        $inscription = $settings->only(['inscription_ouverte']);

        // ── Calendriers ────────────────────────────────────────────────────
        $calendriers = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'calendar_'));

        return view('settings.index', compact(
            'horaires',
            'decalages',
            'decalagesGroupes',
            'settings',
            'inscription',
            'calendriers',
        ));
    }

    /**
     * Sauvegarde les paramètres soumis via le formulaire.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        $settingsInput = $request->input('settings', []);

        $avant = Setting::allForApp(self::APP_CODE)
            ->map(fn($s) => $s['valeur_raw'])
            ->toArray();

        $idApp = DB::table('ref_applications')
            ->where('code', self::APP_CODE)
            ->value('id');

        if (!$idApp) {
            return redirect()->route('settings.index')
                ->with('error', 'Application planning introuvable.');
        }

        $apres = [];
        foreach ($settingsInput as $cle => $valeur) {
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

        audit('update', 'settings', null, $avant, $apres);

        return redirect()->route('settings.index')
            ->with('success', 'Paramètres enregistrés avec succès.');
    }

    // ── Helpers privés ─────────────────────────────────────────────────────

    private function grouperDecalages(\Illuminate\Support\Collection $decalages): array
    {
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
            if (preg_match('/^offset_(.+)_(debut|fin)$/', $cle, $m)) {
                $codeTache = $m[1];
                $sens = $m[2];

                if (!isset($groupes[$codeTache])) {
                    $groupes[$codeTache] = [
                        'libelle' => $libelles[$codeTache] ?? $codeTache,
                        'debut' => null,
                        'fin' => null,
                    ];
                }

                $groupes[$codeTache][$sens] = array_merge(['cle' => $cle], $data);
            }
        }

        return $groupes;
    }
}