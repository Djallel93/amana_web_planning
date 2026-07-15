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
 *   POST /bilan/data/amana-food/reset       → remet le groupe Amana food à NULL (gestionnaire/admin)
 *   POST /bilan/data/presence/reset         → remet le groupe Présences à NULL (gestionnaire/admin)
 *   GET  /bilan/statistiques                → shell Blade (point de montage BilanStatistiques.vue)
 *   GET  /bilan/statistiques/data           → JSON : série + cartes de stats sur une période
 *
 * ── Deux groupes, deux boutons ──────────────────────────────────────────────
 * Amana food (montants) et Présences (effectifs) sont enregistrés
 * indépendamment. Deux personnes peuvent éditer chaque groupe en même temps
 * sans que l'une n'écrase les valeurs de l'autre avec une copie obsolète —
 * chaque upsert ne touche que les colonnes de son propre groupe.
 *
 * ── NULL vs 0 ────────────────────────────────────────────────────────────────
 * NULL = pas de cours ce jour-là (jamais saisi, ou explicitement réinitialisé).
 * 0    = un cours a eu lieu, et la valeur réelle est zéro (ex. aucune rentrée
 *        en espèces, personne en ligne). Voir Bilan et la migration
 *        2026_07_14_000002 pour le détail. Le reset ne fait jamais de
 *        soft-delete de la ligne plan_bilans_quotidiens — il upsert le groupe
 *        concerné à NULL, en conservant qui/quand pour l'audit.
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
     * NULL si aucun bilan n'existe encore pour cette date (voir serialize()).
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
     * Réinitialise (remet à NULL) uniquement le groupe Amana food d'un
     * bilan pour une date — signifie "pas de cours ce jour-là", distinct
     * de 0 qui est une vraie valeur saisie. N'écrit que ses propres
     * colonnes, sans toucher au groupe Présences.
     *
     * Réservé aux gestionnaires/admins (voir route: middleware role:gestionnaire).
     *
     * POST /bilan/data/amana-food/reset
     */
    public function resetAmanaFood(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        /** @var \App\Models\Personne $user */
        $user = Auth::user();
        $date = $request->input('date');

        $existant = Bilan::whereDate('date', $date)->first();
        $avant    = $existant?->only(['montant_carte', 'montant_espece']);

        $bilan = Bilan::updateOrCreate(
            ['date' => $date],
            [
                'montant_carte'        => null,
                'montant_espece'       => null,
                'id_personne_maj_food' => $user->id,
                'maj_food_at'          => now(),
            ]
        );
        $bilan->load(['personneMajFood', 'personneMajPresence']);

        audit(
            'reset',
            'bilan',
            $bilan->id,
            $avant,
            ['montant_carte' => null, 'montant_espece' => null]
        );

        return response()->json([
            'success' => true,
            'message' => 'Bilan Amana food réinitialisé pour cette date (pas de cours).',
            'bilan'   => $this->serialize($date, $bilan),
        ]);
    }

    /**
     * Réinitialise (remet à NULL) uniquement le groupe Présences d'un
     * bilan pour une date — signifie "pas de cours ce jour-là", distinct
     * de 0 qui est une vraie valeur saisie. N'écrit que ses propres
     * colonnes, sans toucher au groupe Amana food.
     *
     * Réservé aux gestionnaires/admins (voir route: middleware role:gestionnaire).
     *
     * POST /bilan/data/presence/reset
     */
    public function resetPresence(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        /** @var \App\Models\Personne $user */
        $user = Auth::user();
        $date = $request->input('date');

        $existant = Bilan::whereDate('date', $date)->first();
        $avant    = $existant?->only(['nb_presents', 'nb_en_ligne']);

        $bilan = Bilan::updateOrCreate(
            ['date' => $date],
            [
                'nb_presents'              => null,
                'nb_en_ligne'              => null,
                'id_personne_maj_presence' => $user->id,
                'maj_presence_at'          => now(),
            ]
        );
        $bilan->load(['personneMajFood', 'personneMajPresence']);

        audit(
            'reset',
            'bilan',
            $bilan->id,
            $avant,
            ['nb_presents' => null, 'nb_en_ligne' => null]
        );

        return response()->json([
            'success' => true,
            'message' => 'Bilan Présences réinitialisé pour cette date (pas de cours).',
            'bilan'   => $this->serialize($date, $bilan),
        ]);
    }

    /**
     * Sérialise un bilan (ou son absence) pour le format attendu par BilanView.vue.
     * Chaque groupe (Amana food / Présences) porte sa propre méta de dernière
     * modification, puisqu'ils sont enregistrés indépendamment.
     *
     * NULL vs 0 : montantCarte/montantEspece/nbPresents/nbEnLigne restent à
     * `null` quand la colonne l'est en base (pas de `?? 0` ici) — c'est au
     * frontend d'afficher un champ vide plutôt qu'un 0 trompeur.
     */
    private function serialize(string $date, ?Bilan $bilan): array
    {
        /** @var \App\Models\Personne|null $user */
        $user = Auth::user();

        return [
            'date'                  => $date,
            'montantCarte'          => $bilan?->montant_carte  !== null ? (float) $bilan->montant_carte  : null,
            'montantEspece'         => $bilan?->montant_espece !== null ? (float) $bilan->montant_espece : null,
            'nbPresents'            => $bilan?->nb_presents,
            'nbEnLigne'             => $bilan?->nb_en_ligne,
            'existe'                => $bilan !== null,
            'derniereMajFood'       => $bilan?->maj_food_at?->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm'),
            'derniereMajFoodPar'    => $bilan?->personneMajFood
                ? $bilan->personneMajFood->prenom . ' ' . $bilan->personneMajFood->nom
                : null,
            'derniereMajPresence'    => $bilan?->maj_presence_at?->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm'),
            'derniereMajPresencePar' => $bilan?->personneMajPresence
                ? $bilan->personneMajPresence->prenom . ' ' . $bilan->personneMajPresence->nom
                : null,
            'peutReinitialiser' => (bool) ($user?->isAdmin() || $user?->isGestionnaire()),
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

        // NULL vs 0 : un groupe (Amana food / Présences) est "sans cours" si
        // ses colonnes sont NULL — on propage le null dans la série pour que
        // le graphique affiche un trou plutôt qu'un 0 trompeur ce jour-là.
        $serie = $bilans->map(function (Bilan $bilan) use ($responsables) {
            $date = $bilan->date->toDateString();
            $r    = $responsables[$date] ?? [];

            $montantCarte  = $bilan->montant_carte  !== null ? (float) $bilan->montant_carte  : null;
            $montantEspece = $bilan->montant_espece !== null ? (float) $bilan->montant_espece : null;

            return [
                'date'                 => $date,
                'totalPresence'        => $bilan->nb_presents !== null ? $bilan->nb_presents + ($bilan->nb_en_ligne ?? 0) : null,
                'totalMontant'         => $montantCarte !== null ? $montantCarte + ($montantEspece ?? 0) : null,
                'nbPresents'           => $bilan->nb_presents,
                'nbEnLigne'            => $bilan->nb_en_ligne,
                'montantCarte'         => $montantCarte,
                'montantEspece'        => $montantEspece,
                'responsableAmanaFood' => $r['amana_food'] ?? null,
                'responsableMektaba'   => $r['mektaba'] ?? null,
            ];
        })->values();

        // ── Nombre de créneaux existants sur la période (taux de remplissage) ──
        $nbCreneaux = Creneau::whereBetween('date', [$from, $to])->count();

        // Sous-ensembles utilisés pour les cartes : on exclut les bilans dont
        // le groupe concerné est à NULL ("pas de cours") des moyennes/records,
        // pour ne pas les faire compter comme des 0 qui tireraient les
        // statistiques vers le bas.
        $bilansAvecPresence = $bilans->filter(fn(Bilan $b) => $b->nb_presents !== null);
        $bilansAvecMontant  = $bilans->filter(fn(Bilan $b) => $b->montant_carte !== null);
        // "Rempli" = au moins un des deux groupes a une vraie valeur — un
        // bilan dont les DEUX groupes sont NULL est juste un jour marqué
        // "pas de cours", pas un bilan réellement saisi.
        $bilansRemplis = $bilans->filter(
            fn(Bilan $b) => $b->montant_carte !== null || $b->nb_presents !== null
        );

        return response()->json([
            'serie' => $serie,
            'cartes' => [
                'totalMontant'        => (float) $bilansAvecMontant->sum(fn(Bilan $b) => $b->montant_carte + $b->montant_espece),
                'moyennePresence'     => $bilansAvecPresence->isNotEmpty()
                    ? round($bilansAvecPresence->avg(fn(Bilan $b) => $b->nb_presents + $b->nb_en_ligne), 1)
                    : 0,
                'meilleureDate'       => $this->meilleureDate($bilansAvecPresence, fn(Bilan $b) => $b->nb_presents + $b->nb_en_ligne),
                'meilleureCollecte'   => $this->meilleureDate($bilansAvecMontant, fn(Bilan $b) => $b->montant_carte + $b->montant_espece),
                'tauxRemplissage'     => $nbCreneaux > 0
                    ? round(($bilansRemplis->count() / $nbCreneaux) * 100)
                    : null,
                'nbBilans'            => $bilansRemplis->count(),
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
