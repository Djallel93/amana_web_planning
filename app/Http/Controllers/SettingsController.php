<?php
// app/Http/Controllers/SettingsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Contrôleur pour les paramètres de l'application planning.
 *
 * Accès route : gestionnaire + admin (middleware 'role:gestionnaire').
 *
 * Restriction interne :
 *   - Le paramètre `inscription_ouverte` est réservé aux admins.
 *     Un gestionnaire peut voir la section mais ne peut pas la modifier.
 *
 * Routes :
 *   GET  /parametres  → index()   Affiche tous les paramètres groupés
 *   POST /parametres  → update()  Sauvegarde un ou tous les paramètres
 */
class SettingsController extends Controller
{
    private const APP_CODE = 'planning';

    /**
     * Clés réservées aux administrateurs — ignorées si soumises par un gestionnaire.
     */
    private const ADMIN_ONLY_KEYS = ['inscription_ouverte'];

    /**
     * Affiche tous les paramètres planning groupés par catégorie.
     */
    public function index(): View
    {
        $settings = Setting::allForApp(self::APP_CODE);

        $horaires = $settings->only(['heure_cours', 'lieu']);
        $decalages = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'offset_'));
        $decalagesGroupes = $this->grouperDecalages($decalages);
        $inscription = $settings->only(['inscription_ouverte']);
        $calendriers = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'calendar_'));

        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        return view('settings.index', compact(
            'horaires',
            'decalages',
            'decalagesGroupes',
            'settings',
            'inscription',
            'calendriers',
            'user',
        ));
    }

    /**
     * Sauvegarde les paramètres soumis via le formulaire.
     *
     * Les clés dans ADMIN_ONLY_KEYS ne sont mises à jour que si l'utilisateur
     * connecté est un administrateur. Un gestionnaire peut soumettre le
     * formulaire complet : ces clés seront silencieusement ignorées.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var \App\Models\Personne $user */
        $user = Auth::user();
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
            // Clé réservée aux admins : ignorer si l'utilisateur n'est pas admin
            if (in_array($cle, self::ADMIN_ONLY_KEYS, true) && !$user->isAdmin()) {
                continue;
            }

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