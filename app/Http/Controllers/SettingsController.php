<?php
// app/Http/Controllers/SettingsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Amana\Shared\Http\Controllers\SettingsControllerBase;
use Amana\Shared\Models\Setting;
use App\Models\CalendrierGoogle;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Paramètres du planning — étend SettingsControllerBase (amana/shared) pour
 * son regroupement d'affichage propre au métier planning (horaires,
 * décalages par tâche, calendriers Google, couleurs). update() est hérité
 * tel quel de la base — la seule chose propre à planning y était déjà
 * paramétrable via appCode()/adminOnlyKeys().
 *
 * Accès route : gestionnaire + admin (middleware 'role:gestionnaire').
 * `inscription_ouverte` reste réservé aux admins (adminOnlyKeys()).
 */
class SettingsController extends SettingsControllerBase
{
    protected function appCode(): string
    {
        return 'planning';
    }

    protected function adminOnlyKeys(): array
    {
        return ['inscription_ouverte'];
    }

    public function index(): View
    {
        $settings = Setting::allForApp($this->appCode());

        $horaires = $settings->only(['heure_cours', 'lieu']);
        $decalages = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'offset_'));
        $decalagesGroupes = $this->grouperDecalages($decalages);
        $inscription = $settings->only(['inscription_ouverte']);
        $calendriers = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'calendar_'));
        $couleurs = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'couleur_'));
        $calendriersGoogle = CalendrierGoogle::orderBy('nom')->get();

        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        return view('settings.index', compact(
            'horaires',
            'decalages',
            'decalagesGroupes',
            'settings',
            'inscription',
            'calendriers',
            'couleurs',
            'calendriersGoogle',
            'user',
        ));
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
            'annulation_cours' => 'Annulation Cours',
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
