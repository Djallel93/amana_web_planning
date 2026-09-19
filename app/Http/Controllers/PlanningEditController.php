<?php
// app/Http/Controllers/PlanningEditController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Http\Resources\Personnes\PersonneResource;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\Absence;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Evenement;
use App\Models\Personne;
use App\Models\Tache;
use App\Services\WebhookPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur pour les modifications manuelles du planning généré.
 *
 * Chaque modification synchronise directement Google Calendar (API v3, via
 * SynchroniserGoogleCalendar) avec le mode adapté à la nature de l'action :
 *   - réassignation (patchAssignation)      → PATCH  (upsert, en queue)
 *   - désassignation (unassignTache)        → DELETE (synchrone)
 *   - suppression d'un créneau entier       → DELETE (synchrone)
 *   - création manuelle d'un créneau        → POST   (upsert, en queue)
 *   - annulation d'un cours (annulerCours)  → DELETE (nettoyage calendrier,
 *                                              synchrone) puis POST (annonce
 *                                              annulation, en queue)
 *
 * Les DELETE sont dispatchés en SYNCHRONE (dispatchSync) et non en queue,
 * car ils sont toujours suivis d'une suppression en cascade des lignes
 * plan_calendrier_evenements — voir le docblock de SynchroniserGoogleCalendar
 * pour le détail de ce choix.
 *
 * Routes :
 *   PATCH  /planning/creneau/{creneauId}/tache/{tacheId}  → modifier l'assignation
 *   DELETE /planning/creneau/{creneauId}/tache/{tacheId}  → désassigner une tâche
 *   DELETE /planning/creneau/{id}                         → supprimer un créneau entier
 *   POST   /planning/creneau                              → créer un créneau manuellement
 *   POST   /planning/annulation-cours                     → annuler le cours d'une date
 *   GET    /planning/personnes-actives                    → liste des personnes pour la modale
 */
class PlanningEditController extends Controller
{
    public function __construct(
        private readonly WebhookPayloadBuilder $webhookBuilder,
    ) {
    }

    /**
     * Retourne la liste des personnes actives pour peupler la modale.
     */
    public function personnes(): JsonResponse
    {
        $personnes = Personne::actifAuPlanning()
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom']);

        return response()->json(PersonneResource::collection($personnes));
    }

    /**
     * Met à jour l'assignation via creneau_id + tache_id.
     * PATCH /planning/creneau/{creneauId}/tache/{tacheId}
     * Body JSON : { "id_personne": 42 } ou { "id_personne": null }
     */
    public function patchAssignation(Request $request, int $creneauId, int $tacheId): JsonResponse
    {
        $request->validate([
            // ref_personnes vit dans amana_commun — voir la même correction
            // dans StoreAbsenceRequest/UpdateAbsenceRequest.
            'id_personne' => ['nullable', 'integer', 'exists:' . config('amana-shared.connection', 'commun') . '.ref_personnes,id'],
        ]);

        // firstOrCreate plutôt que firstOrFail : si la ligne CreneauTache n'existe
        // pas encore (tâche jamais assignée sur ce créneau), on la crée à la volée
        // plutôt que de retourner une 404. Cela rend le PATCH idempotent pour les
        // deux cas (assignation initiale et réassignation).
        $ct = CreneauTache::firstOrCreate(
            [
                'id_planning' => $creneauId,
                'id_tache' => $tacheId,
            ],
            ['id_personne' => null]
        );

        $avant = $ct->toArray();

        // ⚠️ CreneauTache a une clé primaire composite (id_planning, id_tache)
        // et déclare `$primaryKey = null` pour désactiver l'auto-increment
        // — de ce fait, Eloquent ne peut pas construire de clause WHERE pour
        // un ->save() sur une instance déjà chargée (aucune colonne "id" à
        // utiliser). ->save() ici générerait un UPDATE SANS CLAUSE WHERE, qui
        // mettrait à jour TOUTES les lignes de la table. On passe donc
        // systématiquement par le query builder statique, scopé explicitement
        // par (id_planning, id_tache) — jamais ->save()/->update() sur une
        // instance de ce modèle.
        CreneauTache::where('id_planning', $creneauId)
            ->where('id_tache', $tacheId)
            ->update(['id_personne' => $request->input('id_personne')]);

        $ct = $ct->fresh();

        $newPersonne = null;
        if ($ct->id_personne) {
            $p = Personne::find($ct->id_personne);
            $newPersonne = $p ? ['id' => $p->id, 'label' => $p->prenom . ' ' . $p->nom] : null;
        }

        audit('update', 'planning', $creneauId, $avant, $ct->toArray());

        // ── Déclencher le webhook PATCH pour refléter la réassignation ─────
        $this->dispatchWebhookReassignation($creneauId, $tacheId);

        return response()->json([
            'success' => true,
            'personne' => $newPersonne,
            'message' => $newPersonne
                ? "Assigné à {$newPersonne['label']}"
                : 'Tâche désassignée',
        ]);
    }

    /**
     * Désassigne complètement une tâche (id_personne → null).
     * DELETE /planning/creneau/{creneauId}/tache/{tacheId}
     */
    public function unassignTache(int $creneauId, int $tacheId): JsonResponse
    {
        $ct = CreneauTache::where('id_planning', $creneauId)
            ->where('id_tache', $tacheId)
            ->firstOrFail();

        $avant = $ct->toArray();

        // Voir avertissement dans patchAssignation() — jamais ->save() sur une
        // instance de CreneauTache (clé primaire composite, $primaryKey = null).
        CreneauTache::where('id_planning', $creneauId)
            ->where('id_tache', $tacheId)
            ->update(['id_personne' => null]);

        $ct = $ct->fresh();

        audit('update', 'planning', $creneauId, $avant, $ct->toArray());

        // ── Déclencher le webhook DELETE pour refléter la désassignation ───
        $this->dispatchWebhookUnassignation($creneauId, $tacheId);

        return response()->json([
            'success' => true,
            'message' => 'Assignation supprimée',
        ]);
    }

    /**
     * Supprime un créneau entier avec toutes ses tâches.
     * DELETE /planning/creneau/{id}
     */
    public function deleteCreneau(int $id): JsonResponse
    {
        $creneau = Creneau::with(['taches', 'evenements.tachesBloquees'])->findOrFail($id);
        $avant = [
            'date' => $creneau->date->toDateString(),
            'jour' => $creneau->jour,
            'taches' => $creneau->taches->count(),
        ];

        // ── Construire et envoyer le webhook DELETE AVANT la suppression ──
        // (on a encore besoin des tâches bloquées par événement pour savoir
        // quels événements calendrier ont potentiellement été créés)
        $this->dispatchWebhookDeleteCreneau($creneau);

        $creneau->delete();

        audit('delete', 'planning', $id, $avant, null);

        return response()->json([
            'success' => true,
            'message' => "Créneau du {$avant['jour']} {$avant['date']} supprimé",
        ]);
    }

    /**
     * Crée un créneau manuellement pour une date donnée.
     * POST /planning/creneau
     * Body JSON : { "date": "2025-06-06" }
     *           ou (créneau passé, admin) :
     *             { "date": "2026-09-11", "assignations": { "entree": 12, "salle": 7 } }
     *
     * Crée le créneau + une CreneauTache par tâche active, lie les événements
     * organisationnels qui couvrent cette date (comme SchedulerMain::generateDay()
     * — c'est ce lien qui marque les tâches comme bloquées dans la grille et
     * dans le payload Google Calendar), puis déclenche la synchronisation
     * Google Calendar (POST) comme pour n'importe quelle création manuelle.
     *
     * ── Date passée (action corrective, admin uniquement) ───────────────────
     * Créer un créneau dont la date est strictement antérieure à aujourd'hui
     * (fuseau Europe/Paris, voir DateHelper::estPasse()) sert à rattraper un
     * week-end jamais généré. C'est réservé aux administrateurs (403 pour un
     * gestionnaire, même si la route elle-même reste role:gestionnaire).
     *
     * Le moteur de rotation n'invente pas de bénévoles pour un jour déjà
     * écoulé : c'est l'admin qui choisit, tâche par tâche, qui était de
     * permanence, via `assignations` (code de tâche → id_personne). Une tâche
     * absente de `assignations` reste non assignée. `assignations` est refusé
     * (422) pour une date non passée — hors correction, on passe par la
     * génération ou par la modale d'assignation.
     *
     * Événements bloquants : une tâche bloquée par un événement couvrant la date
     * ne peut pas être assignée (422) — même règle que la génération.
     * Absences : volontairement NON bloquantes — l'admin choisit, et une
     * personne déclarée absente a pu venir malgré tout ; la modale affiche un
     * avertissement (voir contexteCreneauPasse()).
     */
    public function createCreneau(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'unique:plan_creneaux,date'],
            // ref_personnes vit dans amana_commun — même règle que patchAssignation().
            'assignations' => ['nullable', 'array'],
            'assignations.*' => ['nullable', 'integer', 'exists:' . config('amana-shared.connection', 'commun') . '.ref_personnes,id'],
        ], [
            'date.required' => 'La date est obligatoire.',
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
            'date.unique' => 'Un créneau existe déjà pour cette date.',
            'assignations.*.exists' => "Une des personnes choisies n'existe pas.",
        ]);

        $date = $request->input('date');
        $estPasse = DateHelper::estPasse($date);

        if ($estPasse && !$request->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un administrateur peut créer un créneau dans le passé.',
            ], 403);
        }

        // Code de tâche → id_personne, sans les entrées vides (« non assigné »).
        $assignations = array_filter(
            (array) $request->input('assignations', []),
            fn($idPersonne) => $idPersonne !== null
        );

        if ($assignations !== [] && !$estPasse) {
            return response()->json([
                'success' => false,
                'message' => "L'assignation à la création n'est possible que pour un créneau passé.",
            ], 422);
        }

        $taches = Tache::actif()->orderBy('id')->get();

        $codesInconnus = array_diff(array_keys($assignations), $taches->pluck('code')->all());
        if ($codesInconnus !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Tâche inconnue ou inactive : ' . implode(', ', $codesInconnus) . '.',
            ], 422);
        }

        // ── Événements couvrant la date (passés compris) ────────────────────
        $evenements = Evenement::with('tachesBloquees')->couvrantDate($date)->get();

        $conflit = $this->premiereAssignationBloquee($assignations, $evenements, $taches);
        if ($conflit !== null) {
            return response()->json(['success' => false, 'message' => $conflit], 422);
        }

        // Créneau + lignes de tâches dans une même transaction : un échec en
        // cours de route ne doit pas laisser un créneau orphelin (la date est
        // unique, il bloquerait toute nouvelle tentative).
        $creneau = DB::transaction(function () use ($date, $taches, $assignations, $evenements) {
            $creneau = Creneau::create(['date' => $date]);

            foreach ($taches as $tache) {
                CreneauTache::create([
                    'id_planning' => $creneau->id,
                    'id_tache' => $tache->id,
                    'id_personne' => $assignations[$tache->code] ?? null,
                ]);
            }

            // Même lien que SchedulerMain::generateDay() : les événements
            // (bloquants ou informatifs) couvrant la date sont rattachés au
            // créneau. À faire AVANT la synchronisation Google Calendar
            // ci-dessous : buildForCreation() en déduit les tâches bloquées.
            if ($evenements->isNotEmpty()) {
                $creneau->evenements()->syncWithoutDetaching($evenements->pluck('id')->all());
            }

            return $creneau;
        });

        $carbonDate = \Carbon\Carbon::parse($date);

        $apres = [
            'date' => $carbonDate->toDateString(),
            'jour' => $creneau->jour,
            'taches' => $taches->count(),
        ];
        if ($evenements->isNotEmpty()) {
            $apres['evenements'] = $evenements->pluck('id')->all();
        }
        if ($estPasse) {
            $apres['passe'] = true;
            $apres['assignations'] = $assignations;
        }

        audit('create', 'planning', $creneau->id, null, $apres);

        // ── Déclencher le webhook POST pour le nouveau créneau ─────────────
        // Aussi pour un créneau passé : les événements Google Calendar sont
        // créés (avec les personnes assignées) comme pour n'importe quel
        // autre créneau.
        $this->dispatchWebhookCreation($creneau);

        $libelleDate = $carbonDate->locale('fr')->isoFormat('D MMM YYYY');
        $nbAssignes = count($assignations);

        return response()->json([
            'success' => true,
            'message' => $estPasse
                ? "Créneau passé du {$creneau->jour} {$libelleDate} créé ({$nbAssignes} tâche(s) assignée(s))."
                : "Créneau du {$creneau->jour} {$libelleDate} créé.",
            'date' => $date,
            'passe' => $estPasse,
        ]);
    }

    /**
     * Contexte d'une date pour la modale « Créneau passé » (admin) : événements
     * qui la couvrent, tâches qu'ils bloquent, personnes déclarées absentes, et
     * si un créneau y existe déjà. La modale s'en sert pour griser les tâches
     * bloquées et signaler les absents — l'application des règles reste côté
     * serveur (createCreneau()).
     *
     * GET /planning/creneau-passe/contexte?date=YYYY-MM-DD  (role:admin)
     */
    public function contexteCreneauPasse(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = $request->query('date');

        $evenements = Evenement::with('tachesBloquees')->couvrantDate($date)->orderBy('date_debut')->get();

        // code de tâche → noms des événements qui la bloquent
        $tachesBloquees = [];
        foreach ($evenements as $evenement) {
            foreach ($evenement->tachesBloquees as $tache) {
                $tachesBloquees[$tache->code][] = $evenement->nom;
            }
        }

        $absents = Absence::where('date_debut', '<=', $date)
            ->where('date_fin', '>=', $date)
            ->pluck('id_personne')
            ->unique()
            ->values();

        return response()->json([
            'date' => $date,
            'dejaExistant' => Creneau::where('date', $date)->exists(),
            'evenements' => $evenements->map(fn($e) => [
                'nom' => $e->nom,
                'bloquant' => $e->tachesBloquees->isNotEmpty(),
            ])->values(),
            'tachesBloquees' => (object) array_map(fn($noms) => implode(', ', $noms), $tachesBloquees),
            'absents' => $absents,
        ]);
    }

    /**
     * Retourne un message d'erreur pour la première assignation visant une
     * tâche bloquée par un événement couvrant la date, ou null s'il n'y en a pas.
     *
     * @param array<string,int>                        $assignations code de tâche → id_personne
     * @param \Illuminate\Support\Collection<int,Evenement> $evenements  Événements couvrant la date (tachesBloquees chargées)
     * @param \Illuminate\Support\Collection<int,Tache>     $taches      Tâches actives
     */
    private function premiereAssignationBloquee(array $assignations, $evenements, $taches): ?string
    {
        foreach (array_keys($assignations) as $code) {
            $bloquants = $evenements->filter(
                fn($e) => $e->tachesBloquees->contains('code', $code)
            );

            if ($bloquants->isNotEmpty()) {
                $libelle = $taches->firstWhere('code', $code)?->libelle ?? $code;

                return "La tâche « {$libelle} » est bloquée ce jour-là par l'événement « "
                    . $bloquants->pluck('nom')->implode(', ') . " » — elle ne peut pas être assignée.";
            }
        }

        return null;
    }

    /**
     * Annule le cours d'une date déjà générée : désassigne toutes les tâches,
     * bloque la date via un événement organisationnel ("Cours annulé — …",
     * bloquant toutes les tâches actives — visible dans la liste des
     * Événements), supprime tous les événements calendrier existants sur
     * cette date, puis annonce l'annulation comme n'importe quel autre
     * événement social (calendar_annulation_cours).
     *
     * POST /planning/annulation-cours
     * Body JSON : { "date": "2026-07-10" }
     *
     * Si aucun créneau n'existe pour cette date, rien n'est modifié : on
     * retourne un simple avertissement (la date ne peut pas être "bloquée"
     * a priori, seulement annulée une fois le planning généré).
     */
    public function annulerCours(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'after:today'],
        ], [
            'date.required' => 'La date est obligatoire.',
            'date.after' => 'La date doit être future.',
        ]);

        $date = $request->input('date');
        $carbonDate = \Carbon\Carbon::parse($date);

        $creneau = Creneau::with('taches.tache')->where('date', $date)->first();

        if (!$creneau) {
            return response()->json([
                'success' => false,
                'warning' => true,
                'message' => "Aucun planning n'a encore été généré pour le "
                    . $carbonDate->locale('fr')->isoFormat('D MMMM YYYY')
                    . ". Cette date ne peut donc pas être annulée pour le moment.",
            ], 422);
        }

        // ── 1. Nettoyer les événements calendrier existants sur cette date ──
        // À faire AVANT de créer/attacher l'événement bloquant ci-dessous :
        // buildForDeleteCreneau() ignore les tâches déjà bloquées par un
        // événement lié au créneau (puisqu'aucun événement calendrier
        // n'existerait pour elles) — il faut donc capturer l'état "avant
        // annulation" pendant que le créneau n'est pas encore bloqué.
        $this->dispatchWebhookDeleteCreneau($creneau);

        // ── 2. Désassigner toutes les tâches du créneau ─────────────────────
        $avant = $creneau->taches->map(fn($ct) => [
            'id_tache' => $ct->id_tache,
            'id_personne' => $ct->id_personne,
        ])->all();

        foreach ($creneau->taches as $ct) {
            if ($ct->id_personne !== null) {
                // Voir avertissement dans patchAssignation() — jamais ->save() sur
                // une instance de CreneauTache (clé primaire composite, $primaryKey = null),
                // sous peine d'un UPDATE sans clause WHERE affectant TOUTE la table.
                CreneauTache::where('id_planning', $ct->id_planning)
                    ->where('id_tache', $ct->id_tache)
                    ->update(['id_personne' => null]);
            }
        }

        // ── 3. Bloquer la date via un événement organisationnel ─────────────
        // Réutilise le mécanisme existant "événement bloquant toutes les
        // tâches" — apparaît dans la liste des Événements (voulu), et la
        // grille/génération/export respectent immédiatement le blocage.
        $nomEvenement = 'Cours annulé — ' . $carbonDate->locale('fr')->isoFormat('D MMMM YYYY');

        $evenement = Evenement::create([
            'nom' => $nomEvenement,
            'date_debut' => $date,
            'date_fin' => $date,
            'description' => 'Cours annulé via le bouton "Annulation cours" du planning.',
        ]);

        $tacheIds = Tache::actif()->pluck('id');
        $evenement->tachesBloquees()->sync($tacheIds);
        $creneau->evenements()->syncWithoutDetaching([$evenement->id]);

        audit('update', 'planning', $creneau->id, ['taches' => $avant], [
            'annule' => true,
            'evenement_id' => $evenement->id,
        ]);
        audit('create', 'evenements', $evenement->id, null, array_merge(
            $evenement->toArray(),
            ['taches_bloquees' => $tacheIds->all()]
        ));

        // ── 4. Annoncer l'annulation (POST, comme n'importe quel événement) ─
        // Ici, à l'inverse, le créneau DOIT refléter l'état "après annulation"
        // (toutes les tâches bloquées) — buildForAnnulationCours() recharge
        // le créneau et son événement bloquant.
        $this->dispatchWebhookAnnulationCours($creneau);

        return response()->json([
            'success' => true,
            'message' => 'Cours annulé pour le ' . $carbonDate->locale('fr')->isoFormat('D MMMM YYYY') . '.',
            'date' => $date,
        ]);
    }

    // ── Private : dispatch des webhooks ──────────────────────────────────
    // Chacun est silencieux en cas d'erreur (ne doit pas faire échouer la
    // réponse JSON de l'action principale).

    private function dispatchWebhookReassignation(int $creneauId, int $tacheId): void
    {
        try {
            $creneau = Creneau::findOrFail($creneauId);
            $tache = Tache::findOrFail($tacheId);
            $payload = $this->webhookBuilder->buildForReassignation($creneau, $tache);

            SynchroniserGoogleCalendar::dispatch($payload, 'patch');

            Log::info('[PlanningEditController] Synchronisation Google Calendar dispatchée (réassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch synchronisation (réassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookUnassignation(int $creneauId, int $tacheId): void
    {
        try {
            $creneau = Creneau::findOrFail($creneauId);
            $tache = Tache::findOrFail($tacheId);
            $payload = $this->webhookBuilder->buildForUnassignation($creneau, $tache);

            SynchroniserGoogleCalendar::dispatchSync($payload, 'delete');

            Log::info('[PlanningEditController] Synchronisation Google Calendar (délete, désassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec synchronisation Google Calendar (désassignation)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookDeleteCreneau(Creneau $creneau): void
    {
        try {
            $payload = $this->webhookBuilder->buildForDeleteCreneau($creneau);

            SynchroniserGoogleCalendar::dispatchSync($payload, 'delete');

            Log::info('[PlanningEditController] Synchronisation Google Calendar (delete, créneau supprimé)', [
                'creneau_id' => $creneau->id,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec synchronisation Google Calendar (delete créneau)', [
                'creneau_id' => $creneau->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookCreation(Creneau $creneau): void
    {
        try {
            $payload = $this->webhookBuilder->buildForCreation($creneau);

            SynchroniserGoogleCalendar::dispatch($payload, 'post');

            Log::info('[PlanningEditController] Synchronisation Google Calendar dispatchée (créneau créé)', [
                'creneau_id' => $creneau->id,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch synchronisation (créneau)', [
                'creneau_id' => $creneau->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWebhookAnnulationCours(Creneau $creneau): void
    {
        try {
            $payload = $this->webhookBuilder->buildForAnnulationCours($creneau);

            SynchroniserGoogleCalendar::dispatch($payload, 'post');

            Log::info('[PlanningEditController] Synchronisation Google Calendar dispatchée (annulation cours)', [
                'creneau_id' => $creneau->id,
                'date' => $creneau->date->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PlanningEditController] Échec dispatch synchronisation (annulation cours)', [
                'creneau_id' => $creneau->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}