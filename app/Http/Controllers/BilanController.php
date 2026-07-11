<?php
// app/Http/Controllers/BilanController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Bilan\StoreBilanAmanaFoodRequest;
use App\Http\Requests\Bilan\StoreBilanPresenceRequest;
use App\Models\Bilan;
use App\Models\Creneau;
use App\Models\CreneauTache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Contrôleur pour le bilan quotidien (Amana food + Présences).
 *
 * Un seul enregistrement partagé par date — tout utilisateur connecté peut
 * consulter et modifier n'importe quelle date, au même titre.
 *
 * Routes :
 *   GET  /bilan                             → shell Blade (point de montage BilanView.vue)
 *   GET  /bilan/data?date=                  → JSON : bilan existant pour une date (ou vide)
 *   POST /bilan/data/amana-food             → upsert du groupe Amana food pour une date
 *   POST /bilan/data/presence               → upsert du groupe Présences pour une date
 *   GET  /bilan/statistiques                → shell Blade (point de montage BilanStatistiques.vue)
 *   GET  /bilan/statistiques/data           → JSON : série + cartes de stats sur une période
 *
 * ── Deux groupes, deux boutons ──────────────────────────────────────────────
 * Amana food (montants) et Présences (effectifs) sont enregistrés
 * indépendamment. Deux personnes peuvent éditer chaque groupe en même temps
 * sans que l'une n'écrase les valeurs de l'autre avec une copie obsolète —
 * chaque upsert ne touche que les colonnes de son propre groupe.
 */
class BilanController extends Controller
{
    /** Tâches dont on affiche la personne responsable dans les tooltips des graphiques. */
    private const TACHES_RESPONSABLES = ['amana_food', 'mektaba'];

    public function index(): View
    {
        return view('bilan.index');
    }

    /**
     * Retourne le bilan enregistré pour une date donnée, ou des valeurs à
     * zéro si aucun bilan n'existe encore pour cette date.
     *
     * GET /bilan/data?date=YYYY-MM-DD
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date  = $request->query('date');
        $bilan = Bilan::with(['personneMajFood', 'personneMajPresence'])->whereDate('date', $date)->first();

        return response()->json($this->serialize($date, $bilan));
    }

    /**
     * Enregistre (crée ou met à jour) uniquement le groupe Amana food d'un
     * bilan — n'écrit que ses propres colonnes, sans toucher au groupe
     * Présences, même si celui-ci a été modifié entre-temps par quelqu'un
     * d'autre.
     *
     * POST /bilan/data/amana-food
     */
    public function storeAmanaFood(StoreBilanAmanaFoodRequest $request): JsonResponse
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $date   = $request->validated('date');
        $existant = Bilan::whereDate('date', $date)->first();
        $avant  = $existant?->only(['montant_carte', 'montant_espece']);

        $bilan = Bilan::updateOrCreate(
            ['date' => $date],
            [
                'montant_carte'        => $request->validated('montant_carte'),
                'montant_espece'       => $request->validated('montant_espece'),
                'id_personne_maj_food' => $user->id,
                'maj_food_at'          => now(),
            ]
        );
        $bilan->load(['personneMajFood', 'personneMajPresence']);

        audit(
            $existant ? 'update' : 'create',
            'bilan',
            $bilan->id,
            $avant,
            $bilan->only(['montant_carte', 'montant_espece'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Bilan Amana food enregistré.',
            'bilan'   => $this->serialize($date, $bilan),
        ]);
    }

    /**
     * Enregistre (crée ou met à jour) uniquement le groupe Présences d'un
     * bilan — n'écrit que ses propres colonnes, sans toucher au groupe
     * Amana food, même si celui-ci a été modifié entre-temps par quelqu'un
     * d'autre.
     *
     * POST /bilan/data/presence
     */
    public function storePresence(StoreBilanPresenceRequest $request): JsonResponse
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $date     = $request->validated('date');
        $existant = Bilan::whereDate('date', $date)->first();
        $avant    = $existant?->only(['nb_presents', 'nb_en_ligne']);

        $bilan = Bilan::updateOrCreate(
            ['date' => $date],
            [
                'nb_presents'              => $request->validated('nb_presents'),
                'nb_en_ligne'              => $request->validated('nb_en_ligne'),
                'id_personne_maj_presence' => $user->id,
                'maj_presence_at'          => now(),
            ]
        );
        $bilan->load(['personneMajFood', 'personneMajPresence']);

        audit(
            $existant ? 'update' : 'create',
            'bilan',
            $bilan->id,
            $avant,
            $bilan->only(['nb_presents', 'nb_en_ligne'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Bilan Présences enregistré.',
            'bilan'   => $this->serialize($date, $bilan),
        ]);
    }

    /**
     * Sérialise un bilan (ou son absence) pour le format attendu par BilanView.vue.
     * Chaque groupe (Amana food / Présences) porte sa propre méta de dernière
     * modification, puisqu'ils sont enregistrés indépendamment.
     */
    private function serialize(string $date, ?Bilan $bilan): array
    {
        return [
            'date'                  => $date,
            'montantCarte'          => $bilan ? (float) $bilan->montant_carte : 0.0,
            'montantEspece'         => $bilan ? (float) $bilan->montant_espece : 0.0,
            'nbPresents'            => $bilan?->nb_presents ?? 0,
            'nbEnLigne'             => $bilan?->nb_en_ligne ?? 0,
            'existe'                => $bilan !== null,
            'derniereMajFood'       => $bilan?->maj_food_at?->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm'),
            'derniereMajFoodPar'    => $bilan?->personneMajFood
                ? $bilan->personneMajFood->prenom . ' ' . $bilan->personneMajFood->nom
                : null,
            'derniereMajPresence'    => $bilan?->maj_presence_at?->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm'),
            'derniereMajPresencePar' => $bilan?->personneMajPresence
                ? $bilan->personneMajPresence->prenom . ' ' . $bilan->personneMajPresence->nom
                : null,
        ];
    }

    /**
     * Vue shell pour la page Statistiques (point de montage BilanStatistiques.vue).
     */
    public function statistiques(): View
    {
        return view('bilan.statistiques');
    }

    /**
     * Série de données + cartes de stats pour une période donnée.
     *
     * GET /bilan/statistiques/data?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * Pour chaque date où un bilan existe, on joint plan_creneaux →
     * plan_creneaux_taches → ref_taches (code amana_food / mektaba) →
     * ref_personnes pour retrouver qui était responsable de ces deux tâches
     * ce jour-là (affiché dans les tooltips des graphiques).
     */
    public function statistiquesData(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->query('from');
        $to   = $request->query('to');

        $bilans = Bilan::whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $responsables = $this->responsablesParDate($from, $to);

        $serie = $bilans->map(function (Bilan $bilan) use ($responsables) {
            $date = $bilan->date->toDateString();
            $r    = $responsables[$date] ?? [];

            return [
                'date'                 => $date,
                'totalPresence'        => $bilan->nb_presents + $bilan->nb_en_ligne,
                'totalMontant'         => (float) $bilan->montant_carte + (float) $bilan->montant_espece,
                'nbPresents'           => $bilan->nb_presents,
                'nbEnLigne'            => $bilan->nb_en_ligne,
                'montantCarte'         => (float) $bilan->montant_carte,
                'montantEspece'        => (float) $bilan->montant_espece,
                'responsableAmanaFood' => $r['amana_food'] ?? null,
                'responsableMektaba'   => $r['mektaba'] ?? null,
            ];
        })->values();

        // ── Nombre de créneaux existants sur la période (taux de remplissage) ──
        $nbCreneaux = Creneau::whereBetween('date', [$from, $to])->count();

        return response()->json([
            'serie' => $serie,
            'cartes' => [
                'totalMontant'        => (float) $bilans->sum(fn(Bilan $b) => $b->montant_carte + $b->montant_espece),
                'moyennePresence'     => $bilans->isNotEmpty()
                    ? round($bilans->avg(fn(Bilan $b) => $b->nb_presents + $b->nb_en_ligne), 1)
                    : 0,
                'meilleureDate' => $this->meilleureDate($bilans, fn(Bilan $b) => $b->nb_presents + $b->nb_en_ligne),
                'meilleureCollecte'   => $this->meilleureDate($bilans, fn(Bilan $b) => $b->montant_carte + $b->montant_espece),
                'tauxRemplissage'     => $nbCreneaux > 0
                    ? round(($bilans->count() / $nbCreneaux) * 100)
                    : null,
                'nbBilans'            => $bilans->count(),
                'nbCreneaux'          => $nbCreneaux,
            ],
        ]);
    }

    /**
     * Construit un index [date => [code_tache => "Prénom Nom"]] pour les
     * tâches définies dans TACHES_RESPONSABLES, sur la période donnée.
     */
    private function responsablesParDate(string $from, string $to): array
    {
        $creneauxTaches = CreneauTache::whereHas(
            'tache',
            fn($q) => $q->whereIn('code', self::TACHES_RESPONSABLES)
        )
            ->whereHas('creneau', fn($q) => $q->whereBetween('date', [$from, $to]))
            ->with(['creneau:id,date', 'tache:id,code', 'personne:id,nom,prenom'])
            ->get();

        $index = [];
        foreach ($creneauxTaches as $ct) {
            if (!$ct->personne) {
                continue;
            }
            $date = $ct->creneau->date->toDateString();
            $index[$date][$ct->tache->code] = $ct->personne->prenom . ' ' . $ct->personne->nom;
        }

        return $index;
    }

    /**
     * Retourne la date et la valeur du bilan maximisant $accessor, ou null
     * si la collection est vide.
     *
     * @param \Illuminate\Support\Collection<int, Bilan> $bilans
     * @param \Closure(Bilan): float $accessor
     */
    private function meilleureDate($bilans, \Closure $accessor): ?array
    {
        if ($bilans->isEmpty()) {
            return null;
        }

        $meilleur = $bilans->sortByDesc($accessor)->first();

        return [
            'date'  => $meilleur->date->toDateString(),
            'valeur' => $accessor($meilleur),
        ];
    }
}
