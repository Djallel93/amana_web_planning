<?php
// app/Http/Controllers/SettingsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Amana\Shared\Http\Controllers\SettingsControllerBase;
use Amana\Shared\Models\Setting;
use App\Models\CalendrierGoogle;
use App\Models\Personne;
use App\Services\SessionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
 * `inscription_ouverte` et `session_lifetime` restent réservés aux admins (adminOnlyKeys()).
 */
class SettingsController extends SettingsControllerBase
{
    protected function appCode(): string
    {
        return 'planning';
    }

    protected function adminOnlyKeys(): array
    {
        return ['inscription_ouverte', SessionPolicy::CLE_PARAMETRE];
    }

    /**
     * La durée de session est bornée (SessionPolicy::MIN_MINUTES–MAX_MINUTES) : la
     * validation générique de la base ne contrôle que « entier ». Validée ici avant
     * de déléguer (seul un admin peut réellement l'enregistrer — adminOnlyKeys()).
     */
    public function update(Request $request): RedirectResponse
    {
        if ($request->has('settings.' . SessionPolicy::CLE_PARAMETRE)) {
            $request->validate([
                'settings.' . SessionPolicy::CLE_PARAMETRE => [
                    'required', 'integer',
                    'min:' . SessionPolicy::MIN_MINUTES,
                    'max:' . SessionPolicy::MAX_MINUTES,
                ],
            ], [
                'settings.' . SessionPolicy::CLE_PARAMETRE . '.required' => 'La durée de session est obligatoire.',
                'settings.' . SessionPolicy::CLE_PARAMETRE . '.integer' => 'La durée de session doit être un nombre entier de minutes.',
                'settings.' . SessionPolicy::CLE_PARAMETRE . '.min' => 'La durée de session doit être d\'au moins ' . SessionPolicy::MIN_MINUTES . ' minutes.',
                'settings.' . SessionPolicy::CLE_PARAMETRE . '.max' => 'La durée de session ne peut pas dépasser ' . SessionPolicy::MAX_MINUTES . ' minutes (24 h).',
            ]);
        }

        return parent::update($request);
    }

    public function index(): View
    {
        $settings = Setting::allForApp($this->appCode());

        $horaires = $settings->only(['heure_cours', 'lieu']);
        $decalages = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'offset_'));
        $decalagesGroupes = $this->grouperDecalages($decalages);
        $inscription = $settings->only(['inscription_ouverte']);
        $sessionParametre = $settings->get(SessionPolicy::CLE_PARAMETRE);
        $calendriers = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'calendar_'));
        $couleurs = $settings->filter(fn($_, $cle) => str_starts_with($cle, 'couleur_'));
        $calendriersGoogle = CalendrierGoogle::orderBy('nom')->get();

        /** @var Personne $user */
        $user = Auth::user();

        return view('settings.index', compact(
            'horaires',
            'decalages',
            'decalagesGroupes',
            'settings',
            'inscription',
            'sessionParametre',
            'calendriers',
            'couleurs',
            'calendriersGoogle',
            'user',
        ));
    }

    // ── Helpers privés ─────────────────────────────────────────────────────

    private function grouperDecalages(Collection $decalages): array
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
