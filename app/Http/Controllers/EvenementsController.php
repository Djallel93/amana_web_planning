<?php
// app/Http/Controllers/EvenementsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Evenements\StoreEvenementRequest;
use App\Http\Requests\Evenements\UpdateEvenementRequest;
use App\Jobs\EnvoyerWebhookMake;
use App\Models\Evenement;
use App\Models\Tache;
use App\Services\WebhookEvenementPayloadBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Contrôleur CRUD pour les événements organisationnels.
 *
 * Chaque opération create/update/delete déclenche un webhook Make.com
 * si l'événement a un calendar_name configuré.
 */
class EvenementsController extends Controller
{
    public function __construct(
        private readonly WebhookEvenementPayloadBuilder $webhookBuilder,
    ) {
    }

    public function index(): View
    {
        $evenements = Evenement::with('tachesBloquees')
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
        $data    = $request->validated();
        $tacheIds = $data['taches'] ?? [];
        unset($data['taches']);

        $evenement = Evenement::create($data);
        $evenement->tachesBloquees()->sync($tacheIds);

        audit('create', 'evenements', $evenement->id, null, array_merge(
            $evenement->toArray(),
            ['taches_bloquees' => $tacheIds]
        ));

        $this->dispatchWebhookUpsert($evenement);

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$evenement->nom} » créé.");
    }

    public function edit(int $id): View
    {
        $evenement = Evenement::with('tachesBloquees')->findOrFail($id);
        $taches    = Tache::actif()->orderBy('id')->get();
        return view('evenements.form', compact('evenement', 'taches'));
    }

    public function update(UpdateEvenementRequest $request, int $id): RedirectResponse
    {
        $evenement = Evenement::findOrFail($id);
        $avant     = $evenement->toArray();

        $data     = $request->validated();
        $tacheIds = $data['taches'] ?? [];
        unset($data['taches']);

        $evenement->update($data);
        $evenement->tachesBloquees()->sync($tacheIds);

        audit('update', 'evenements', $evenement->id, $avant, array_merge(
            $evenement->fresh()->toArray(),
            ['taches_bloquees' => $tacheIds]
        ));

        $this->dispatchWebhookUpsert($evenement->fresh()->load('tachesBloquees'));

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$evenement->nom} » mis à jour.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $evenement = Evenement::findOrFail($id);
        $avant     = $evenement->toArray();
        $nom       = $evenement->nom;

        // Construire le payload delete AVANT la suppression (on a encore les données)
        $this->dispatchWebhookDelete($evenement);

        $evenement->delete();

        audit('delete', 'evenements', $id, $avant, null);

        return redirect()->route('evenements.index')
            ->with('success', "Événement « {$nom} » supprimé.");
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Dispatche un webhook upsert si le calendar_name est configuré.
     */
    private function dispatchWebhookUpsert(Evenement $evenement): void
    {
        if (!$evenement->hasCalendarSync()) {
            return;
        }

        if (empty(config('services.make.webhook_url'))) {
            return;
        }

        try {
            $payload = $this->webhookBuilder->buildUpsert($evenement);
            EnvoyerWebhookMake::dispatch($payload);
            Log::info('[EvenementsController] Webhook upsert dispatché', ['id' => $evenement->id, 'nom' => $evenement->nom]);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec dispatch webhook upsert', [
                'id'    => $evenement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatche un webhook delete si le calendar_name est configuré.
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
            EnvoyerWebhookMake::dispatch($payload);
            Log::info('[EvenementsController] Webhook delete dispatché', ['id' => $evenement->id, 'nom' => $evenement->nom]);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec dispatch webhook delete', [
                'id'    => $evenement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
