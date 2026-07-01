<?php
// app/Http/Controllers/BilanController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Bilan\StoreBilanRequest;
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
 *   GET  /bilan                       → shell Blade (point de montage BilanView.vue)
 *   GET  /bilan/data?date=            → JSON : bilan existant pour une date (ou vide)
 *   POST /bilan/data                  → upsert du bilan pour une date
 *   GET  /bilan/statistiques          → shell Blade (point de montage BilanStatistiques.vue)
 *   GET  /bilan/statistiques/data     → JSON : série + cartes de stats sur une période
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
        $bilan = Bilan::with('personneMaj')->whereDate('date', $date)->first();

        return response()->json($this->serialize($date, $bilan));
    }

    /**
     * Enregistre (crée ou met à jour) le bilan d'une date.
     *
     * POST /bilan/data
     */
    public function store(StoreBilanRequest $request): JsonResponse
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $date  = $request->validated('date');
        $avant = Bilan::whereDate('date', $date)->first()?->toArray();

        $bilan = Bilan::updateOrCreate(
            ['date' => $date],
            [
                'montant_carte'   => $request->validated('montant_carte'),
                'montant_espece'  => $request->validated('montant_espece'),
                'nb_presents'     => $request->validated('nb_presents'),
                'nb_en_ligne'     => $request->validated('nb_en_ligne'),
                'id_personne_maj' => $user->id,
            ]
        );
        $bilan->load('personneMaj');

        audit($avant ? 'update' : 'create', 'bilan', $bilan->id, $avant, $bilan->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Bilan enregistré.',
            'bilan'   => $this->serialize($date, $bilan),
        ]);
    }

    /**
     * Sérialise un bilan (ou son absence) pour le format attendu par BilanView.vue.
     */
    private function serialize(string $date, ?Bilan $bilan): array
    {
        return [
            'date'           => $date,
            'montantCarte'   => $bilan ? (float) $bilan->montant_carte : 0.0,
            'montantEspece'  => $bilan ? (float) $bilan->montant_espece : 0.0,
            'nbPresents'     => $bilan?->nb_presents ?? 0,
            'nbEnLigne'      => $bilan?->nb_en_ligne ?? 0,
            'existe'         => $bilan !== null,
            'derniereMaj'    => $bilan?->updated_at?->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm'),
            'derniereMajPar' => $bilan?->personneMaj
                ? $bilan->personneMaj->prenom . ' ' . $bilan->personneMaj->nom
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
