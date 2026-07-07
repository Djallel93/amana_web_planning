<?php
// app/Http/Controllers/EvenementsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Evenements\StoreEvenementRequest;
use App\Http\Requests\Evenements\UpdateEvenementRequest;
use App\Jobs\EnvoyerWebhookMake;
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
 * Chaque opération create/update/delete déclenche un webhook Make.com
 * si l'événement a au moins un calendrier configuré, avec le verbe HTTP
 * correspondant (POST / PATCH / DELETE).
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
        $calendarNames = $data['calendar_names'] ?? [];
        unset($data['taches'], $data['calendar_names']);

        $evenement = Evenement::create($data);
        $evenement->tachesBloquees()->sync($tacheIds);
        $this->syncCalendriers($evenement, $calendarNames);

        audit('create', 'evenements', $evenement->id, null, array_merge(
            $evenement->toArray(),
            ['taches_bloquees' => $tacheIds, 'calendar_names' => $calendarNames]
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
        $calendarNames = $data['calendar_names'] ?? [];
        unset($data['taches'], $data['calendar_names']);

        $evenement->update($data);
        $evenement->tachesBloquees()->sync($tacheIds);
        $this->syncCalendriers($evenement, $calendarNames);

        audit('update', 'evenements', $evenement->id, $avant, array_merge(
            $evenement->fresh()->toArray(),
            ['taches_bloquees' => $tacheIds, 'calendar_names' => $calendarNames]
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
     * @param array<int, string> $calendarNames
     */
    private function syncCalendriers(Evenement $evenement, array $calendarNames): void
    {
        $evenement->calendriers()->delete();

        $calendarNames = array_values(array_unique(array_filter(
            array_map('trim', $calendarNames)
        )));

        foreach ($calendarNames as $nom) {
            $evenement->calendriers()->create(['calendar_name' => $nom]);
        }

        $evenement->unsetRelation('calendriers');
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
     * Dispatche un webhook upsert si au moins un calendrier est configuré.
     *
     * @param string $method 'post' (création) ou 'patch' (modification)
     */
    private function dispatchWebhookUpsert(Evenement $evenement, string $method): void
    {
        if (!$evenement->hasCalendarSync()) {
            return;
        }

        if (empty(config('services.make.webhook_url'))) {
            return;
        }

        try {
            $payload = $this->webhookBuilder->buildUpsert($evenement);
            EnvoyerWebhookMake::dispatch($payload, $method);
            Log::info('[EvenementsController] Webhook dispatché', [
                'id' => $evenement->id,
                'nom' => $evenement->nom,
                'method' => strtoupper($method),
            ]);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec dispatch webhook upsert', [
                'id' => $evenement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatche un webhook delete si au moins un calendrier est configuré.
     */
    private function dispatchWebhookDelete(Evenement $evenement): void
    {
        if (!$evenement->hasCalendarSync()) {
            return;
        }

        if (empty(config('services.make.webhook_url'))) {
            return;
        }

        try {
            $payload = $this->webhookBuilder->buildDelete($evenement);
            EnvoyerWebhookMake::dispatch($payload, 'delete');
            Log::info('[EvenementsController] Webhook DELETE dispatché', ['id' => $evenement->id, 'nom' => $evenement->nom]);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec dispatch webhook delete', [
                'id' => $evenement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatche le webhook DELETE de désassignation d'une tâche (réutilisé
     * depuis PlanningEditController — même payload que le bouton
     * "✕ Désassigner" manuel).
     */
    private function dispatchWebhookUnassignation(int $creneauId, int $tacheId): void
    {
        if (empty(config('services.make.webhook_url'))) {
            return;
        }

        try {
            $creneau = Creneau::findOrFail($creneauId);
            $tache = Tache::findOrFail($tacheId);
            $payload = $this->planningWebhookBuilder->buildForUnassignation($creneau, $tache);

            EnvoyerWebhookMake::dispatch($payload, 'delete');

            Log::info('[EvenementsController] Webhook DELETE dispatché (désassignation rétroactive)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec dispatch webhook DELETE (désassignation rétroactive)', [
                'creneau_id' => $creneauId,
                'tache_id' => $tacheId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}