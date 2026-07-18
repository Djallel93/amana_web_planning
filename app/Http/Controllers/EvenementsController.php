<?php
// app/Http/Controllers/EvenementsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Evenements\StoreEvenementRequest;
use App\Http\Requests\Evenements\UpdateEvenementRequest;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\CalendrierGoogle;
use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Evenement;
use App\Models\Tache;
use App\Services\WebhookEvenementPayloadBuilder;
use App\Services\WebhookPayloadBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Contrôleur CRUD pour les événements organisationnels.
 *
 * Chaque opération create/update/delete synchronise directement Google
 * Calendar (SynchroniserGoogleCalendar) si l'événement a au moins un
 * calendrier configuré : POST/PATCH en queue (upsert), DELETE en
 * synchrone — voir le docblock de SynchroniserGoogleCalendar pour le détail
 * de ce choix (lié à l'onDelete('cascade') sur ref_evenements_calendriers).
 *
 * Si l'événement couvre des dates pour lesquelles un planning a déjà été
 * généré, les créneaux existants (futurs uniquement — voir syncCreneauLinks)
 * sont mis à jour pour refléter le nouvel événement : bannière informative
 * dans tous les cas, et désassignation réelle des tâches nouvellement
 * bloquées si l'événement est bloquant.
 */
class EvenementsController extends Controller
{
    public function __construct(
        private readonly WebhookEvenementPayloadBuilder $webhookBuilder,
        private readonly WebhookPayloadBuilder $planningWebhookBuilder,
    ) {
    }

    public function index(): View
    {
        $evenements = Evenement::with('tachesBloquees', 'calendriers')
            ->orderBy('date_debut', 'desc')
            ->get();

        return view('evenements.index', compact('evenements'));
    }

    public function create(): View
    {
        $taches = Tache::actif()->orderBy('id')->get();
        return view('evenements.form', compact('taches'));
    }

    public function store(StoreEvenementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tacheIds = $data['taches'] ?? [];
        $calendarIds = $data['calendar_ids'] ?? [];
        unset($data['taches'], $data['calendar_ids']);

        $evenement = Evenement::create($data);
        $evenement->tachesBloquees()->sync($tacheIds);
        $this->syncCalendriers($evenement, $calendarIds);

        audit('create', 'evenements', $evenement->id, null, array_merge(
            $evenement->toArray(),
            ['taches_bloquees' => $tacheIds, 'calendar_ids' => $calendarIds]
        ));

        $evenement->load('tachesBloquees');
        $this->dispatchWebhookUpsert($evenement, 'post');

        $resultat = $this->syncCreneauLinks($evenement);

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$evenement->nom} » créé.")
            ->with('warning', $this->buildPastDatesWarning($resultat, $evenement));
    }

    public function edit(int $id): View
    {
        $evenement = Evenement::with('tachesBloquees', 'calendriers')->findOrFail($id);
        $taches = Tache::actif()->orderBy('id')->get();
        return view('evenements.form', compact('evenement', 'taches'));
    }

    public function update(UpdateEvenementRequest $request, int $id): RedirectResponse
    {
        $evenement = Evenement::findOrFail($id);
        $avant = $evenement->toArray();

        $data = $request->validated();
        $tacheIds = $data['taches'] ?? [];
        $calendarIds = $data['calendar_ids'] ?? [];
        unset($data['taches'], $data['calendar_ids']);

        $evenement->update($data);
        $evenement->tachesBloquees()->sync($tacheIds);
        $this->syncCalendriers($evenement, $calendarIds);

        audit('update', 'evenements', $evenement->id, $avant, array_merge(
            $evenement->fresh()->toArray(),
            ['taches_bloquees' => $tacheIds, 'calendar_ids' => $calendarIds]
        ));

        $evenement = $evenement->fresh()->load('tachesBloquees');
        $this->dispatchWebhookUpsert($evenement, 'patch');

        $resultat = $this->syncCreneauLinks($evenement);

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$evenement->nom} » mis à jour.")
            ->with('warning', $this->buildPastDatesWarning($resultat, $evenement));
    }

    public function destroy(int $id): RedirectResponse
    {
        $evenement = Evenement::findOrFail($id);
        $avant = $evenement->toArray();
        $nom = $evenement->nom;

        // Construire le payload delete AVANT la suppression (on a encore les données)
        $this->dispatchWebhookDelete($evenement);

        $evenement->delete();

        audit('delete', 'evenements', $id, $avant, null);

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$nom} » supprimé.");
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Remplace les calendriers liés à un événement.
     *
     * @param array<int, string> $calendarIds Identifiants Google Calendar
     *        (calendarId) sélectionnés dans le formulaire — le libellé
     *        d'affichage (calendar_name) est résolu ici depuis la même
     *        liste que celle utilisée par le dropdown, pour rester
     *        cohérent sans dépendre d'un aller-retour API supplémentaire.
     */
    private function syncCalendriers(Evenement $evenement, array $calendarIds): void
    {
        $calendarIds = array_values(array_unique(array_filter(array_map('trim', $calendarIds))));

        $anciennes = $evenement->calendriers()->get()->keyBy('google_calendar_id');
        $noms = $this->resolveCalendarNames($calendarIds);

        $evenement->calendriers()->whereNotIn('google_calendar_id', $calendarIds)->delete();

        foreach ($calendarIds as $id) {
            $evenement->calendriers()->updateOrCreate(
                ['google_calendar_id' => $id],
                [
                    'calendar_name' => $noms[$id] ?? $anciennes->get($id)?->calendar_name ?? $id,
                ]
            );
        }

        $evenement->unsetRelation('calendriers');
    }

    /**
     * Résout id → nom d'affichage depuis le registre `ref_calendriers_google`
     * (voir CalendrierGoogleController) — pas d'appel à l'API Google Calendar
     * ici : `calendars.get()` en boucle pour chaque ID serait lent et
     * superflu puisque le registre contient déjà le nom validé à
     * l'enregistrement de chaque calendrier.
     *
     * @param array<int, string> $calendarIds
     * @return array<string, string>
     */
    private function resolveCalendarNames(array $calendarIds): array
    {
        if (empty($calendarIds)) {
            return [];
        }

        return CalendrierGoogle::whereIn('calendar_id', $calendarIds)
            ->pluck('nom', 'calendar_id')
            ->all();
    }

    /**
     * Met à jour les créneaux déjà générés qui chevauchent la plage de dates
     * de cet événement :
     *   - relie l'événement au créneau (bannière/label informatif) ;
     *   - si l'événement bloque des tâches, désassigne réellement les tâches
     *     nouvellement bloquées (impacte les statistiques et la répartition
     *     future — c'est voulu, contrairement à un simple label visuel).
     *
     * IMPORTANT : les créneaux PASSÉS (date < aujourd'hui) ne sont jamais
     * modifiés, quelle que soit la nature de l'événement — un planning déjà
     * exécuté ne doit pas être réécrit rétroactivement (fiabilité des
     * statistiques et de l'équité de répartition déjà constatée).
     *
     * @return array{pastCount: int, unassignedCount: int}
     */
    private function syncCreneauLinks(Evenement $evenement): array
    {
        DB::table('plan_creneaux_evenements')->where('id_evenement', $evenement->id)->delete();

        $creneaux = Creneau::whereBetween('date', [$evenement->date_debut, $evenement->date_fin])
            ->with('taches.tache')
            ->get();

        $today = now()->toDateString();
        $bloquant = $evenement->tachesBloquees->isNotEmpty();

        $pastCount = 0;
        $unassignedCount = 0;

        foreach ($creneaux as $creneau) {
            if ($creneau->date->toDateString() < $today) {
                // Créneau déjà passé : on ne touche à rien, ni le lien
                // informatif ni les assignations — seulement compté pour
                // pouvoir avertir l'utilisateur.
                $pastCount++;
                continue;
            }

            $creneau->evenements()->syncWithoutDetaching([$evenement->id]);

            if (!$bloquant) {
                continue;
            }

            $creneau->load('evenements.tachesBloquees');
            $tachesBloqueesCodes = $creneau->tachesBloqueesCodes();

            foreach ($creneau->taches as $ct) {
                $code = $ct->tache?->code;
                if (!$code || !$tachesBloqueesCodes->contains($code) || $ct->id_personne === null) {
                    continue;
                }

                $ancienId = $ct->id_personne;
                // Voir avertissement dans PlanningEditController::patchAssignation()
                // — jamais ->save() sur une instance de CreneauTache (clé primaire
                // composite, $primaryKey = null), sous peine d'un UPDATE sans
                // clause WHERE affectant TOUTE la table.
                CreneauTache::where('id_planning', $ct->id_planning)
                    ->where('id_tache', $ct->id_tache)
                    ->update(['id_personne' => null]);
                $unassignedCount++;

                audit(
                    'update',
                    'planning',
                    $creneau->id,
                    ['id_tache' => $ct->id_tache, 'id_personne' => $ancienId],
                    ['id_tache' => $ct->id_tache, 'id_personne' => null]
                );

                $this->dispatchWebhookUnassignation($creneau->id, $ct->id_tache);
            }
        }

        return ['pastCount' => $pastCount, 'unassignedCount' => $unassignedCount];
    }

    /**
     * Construit (si nécessaire) un message d'avertissement expliquant que
     * des créneaux passés chevauchant cet événement n'ont volontairement
     * pas été modifiés.
     */
    private function buildPastDatesWarning(array $resultat, Evenement $evenement): ?string
    {
        if ($resultat['pastCount'] === 0) {
            return null;
        }

        $n = $resultat['pastCount'];
        $pluriel = $n > 1 ? 's' : '';
        $pluralVerbe = $n > 1 ? 'nt' : '';

        if ($evenement->tachesBloquees->isEmpty()) {
            return "{$n} créneau{$pluriel} déjà passé{$pluriel} chevauche{$pluralVerbe} la période de cet événement "
                . "et n'a{$pluriel} pas été mis à jour (le planning déjà exécuté n'est jamais modifié rétroactivement).";
        }

        return "Impossible de bloquer {$n} créneau{$pluriel} déjà passé{$pluriel} avec cet événement : "
            . "modifier un planning déjà exécuté fausserait l'équité de répartition et les statistiques déjà "
            . "constatées. Seuls les créneaux à venir ont été mis à jour.";
    }

    /**
     * Dispatche une synchronisation Google Calendar (upsert) si au moins un
     * calendrier est configuré.
     *
     * @param string $method 'post' (création) ou 'patch' (modification)
     */
    private function dispatchWebhookUpsert(Evenement $evenement, string $method): void
    {
        if (!$evenement->hasCalendarSync()) {
            return;
        }

        try {
            $payload = $this->webhookBuilder->buildUpsert($evenement);
            SynchroniserGoogleCalendar::dispatch($payload, $method, 'evenement');
            Log::info('[EvenementsController] Synchronisation Google Calendar dispatchée', [
                'id' => $evenement->id,
                'nom' => $evenement->nom,
                'method' => strtoupper($method),
            ]);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec dispatch synchronisation upsert', [
                'id' => $evenement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatche une suppression Google Calendar (synchrone) si au moins un
     * calendrier est configuré — voir docblock de SynchroniserGoogleCalendar.
     */
    private function dispatchWebhookDelete(Evenement $evenement): void
    {
        if (!$evenement->hasCalendarSync()) {
            return;
        }

        try {
            $payload = $this->webhookBuilder->buildDelete($evenement);
            SynchroniserGoogleCalendar::dispatchSync($payload, 'delete', 'evenement');
            Log::info('[EvenementsController] Synchronisation Google Calendar (delete)', ['id' => $evenement->id, 'nom' => $evenement->nom]);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec synchronisation Google Calendar (delete)', [
                'id' => $evenement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatche la synchronisation DELETE de désassignation d'une tâche
     * (réutilisée depuis PlanningEditController — même payload que le
     * bouton "✕ Désassigner" manuel). Synchrone — voir docblock de
     * SynchroniserGoogleCalendar.
     */
    private function dispatchWebhookUnassignation(int $creneauId, int $tacheId): void
    {
        try {
            $creneau = Creneau::findOrFail($creneauId);
            $tache = Tache::findOrFail($tacheId);
            $payload = $this->planningWebhookBuilder->buildForUnassignation($creneau, $tache);

            SynchroniserGoogleCalendar::dispatchSync($payload, 'delete');

            Log::info('[EvenementsController] Synchronisation Google Calendar (delete, désassignation rétroactive)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec synchronisation Google Calendar (désassignation rétroactive)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}