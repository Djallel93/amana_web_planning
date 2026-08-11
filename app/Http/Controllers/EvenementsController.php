<?php
// app/Http/Controllers/EvenementsController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\GoogleCalendarColors;
use App\Http\Requests\Evenements\ImportEvenementsManuelRequest;
use App\Http\Requests\Evenements\ImportEvenementsRequest;
use App\Http\Requests\Evenements\StoreEvenementRequest;
use App\Http\Requests\Evenements\UpdateEvenementRequest;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\CalendrierGoogle;
use App\Models\Evenement;
use App\Models\Tache;
use App\Services\EvenementCsvImporter;
use App\Services\EvenementRegenerationService;
use App\Services\WebhookEvenementPayloadBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur CRUD (+ import CSV en masse) pour les événements
 * organisationnels.
 *
 * Chaque opération create/update/delete synchronise directement Google
 * Calendar (SynchroniserGoogleCalendar) si l'événement a au moins un
 * calendrier configuré : POST/PATCH en queue (upsert), DELETE en
 * synchrone — voir le docblock de SynchroniserGoogleCalendar pour le détail
 * de ce choix (lié à l'onDelete('cascade') sur ref_evenements_calendriers).
 *
 * Si l'événement couvre des dates pour lesquelles un planning a déjà été
 * généré, EvenementRegenerationService régénère automatiquement le planning
 * depuis la première date impactée — voir son docblock pour le détail de
 * pourquoi une régénération complète a remplacé l'ancien patch ciblé
 * créneau par créneau (syncCreneauLinks, retiré).
 */
class EvenementsController extends Controller
{
    public function __construct(
        private readonly WebhookEvenementPayloadBuilder $webhookBuilder,
        private readonly EvenementRegenerationService $regenerationService,
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

        $message = "Événement « {$evenement->nom} » créé.";
        $regeneration = $this->regenerationService->regenererSiNecessaire($evenement);
        if ($regeneration !== null) {
            $message .= ' ' . $regeneration['message'];
        }

        return redirect()->route('evenements.index')->with('success', $message);
    }

    /**
     * Affiche le formulaire d'import en masse — deux méthodes indépendantes
     * sur la même page : upload CSV (voir EvenementCsvImporter) ou saisie
     * manuelle multi-lignes (BulkEvenementImport.vue, voir storeManualImport()).
     */
    public function import(): View
    {
        $taches = Tache::actif()->orderBy('id')->get(['id', 'code', 'libelle']);
        $couleurs = collect(GoogleCalendarColors::PALETTE)
            ->map(fn(array $c, string $id) => ['id' => $id, 'nom' => $c['nom']])
            ->values();

        return view('evenements.import', compact('taches', 'couleurs'));
    }

    /**
     * Traite le fichier CSV envoyé — import "tout ou rien" (voir docblock
     * de EvenementCsvImporter) : si une seule ligne est invalide, RIEN
     * n'est créé et le détail des erreurs est renvoyé au formulaire.
     *
     * Une fois les événements créés (transaction unique), la synchronisation
     * Google Calendar par événement et la régénération du planning (si des
     * créneaux futurs sont impactés) sont déclenchées comme pour une
     * création manuelle — voir finalizeImport(), commun avec
     * storeManualImport().
     */
    public function storeImport(ImportEvenementsRequest $request, EvenementCsvImporter $importer): RedirectResponse
    {
        $parsed = $importer->validate($request->file('csv'));

        if (!empty($parsed['errors'])) {
            return redirect()->route('evenements.import')
                ->with('import_errors', $parsed['errors'])
                ->with('error', count($parsed['errors']) . " ligne(s) invalide(s) — aucun événement n'a été importé. Corrigez le fichier et réessayez.");
        }

        if (empty($parsed['rows'])) {
            return redirect()->route('evenements.import')
                ->with('error', 'Le fichier ne contient aucune ligne de données.');
        }

        try {
            $evenements = $importer->import($parsed['rows']);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec de l\'import CSV', ['error' => $e->getMessage()]);

            return redirect()->route('evenements.import')
                ->with('error', "Échec de l'import : " . $e->getMessage());
        }

        return $this->finalizeImport($evenements);
    }

    /**
     * Traite la saisie manuelle de plusieurs événements (section "Saisie
     * manuelle" de evenements/import.blade.php, formulaire dynamique
     * BulkEvenementImport.vue) — alternative à l'upload CSV pour les mêmes
     * résultats. "Tout ou rien" garanti par ImportEvenementsManuelRequest :
     * la validation de TOUTES les lignes se fait avant que cette méthode ne
     * s'exécute — si une seule ligne est invalide, Laravel redirige
     * automatiquement vers le formulaire avec les erreurs et les valeurs
     * saisies (old('rows')), sans qu'aucun événement n'ait été créé.
     *
     * Réutilise EvenementCsvImporter::import() (transaction unique + audit
     * + pivots) : cette méthode ne contient rien de spécifique au CSV, elle
     * attend déjà des lignes pré-résolues (tache_ids en entiers,
     * calendar_ids en identifiants Google Calendar bruts) — exactement ce
     * que produisent les checkboxes et le SearchableSelect du formulaire
     * manuel, sans résolution de codes/noms nécessaire ici.
     */
    public function storeManualImport(ImportEvenementsManuelRequest $request, EvenementCsvImporter $importer): RedirectResponse
    {
        $rows = collect($request->validated('rows'))
            ->map(function (array $row) {
                $calendarIds = array_values(array_unique(array_filter($row['calendar_ids'] ?? [])));

                return [
                    'nom' => trim($row['nom']),
                    'date_debut' => $row['date_debut'],
                    'date_fin' => $row['date_fin'],
                    'description' => trim((string) ($row['description'] ?? '')) ?: null,
                    'couleur' => $row['couleur'] ?? null ?: null,
                    'tache_ids' => array_values(array_unique($row['taches'] ?? [])),
                    'calendar_ids' => $calendarIds,
                    'calendar_noms' => $this->resolveCalendarNames($calendarIds),
                ];
            })
            ->all();

        try {
            $evenements = $importer->import($rows);
        } catch (\Throwable $e) {
            Log::error('[EvenementsController] Échec de la saisie manuelle en masse', ['error' => $e->getMessage()]);

            return redirect()->route('evenements.import')
                ->withInput()
                ->with('error', "Échec de l'import : " . $e->getMessage());
        }

        return $this->finalizeImport($evenements);
    }

    /**
     * Sert un fichier CSV modèle (en-tête + une ligne d'exemple) pour
     * l'import en masse.
     */
    public function downloadTemplate(): Response
    {
        $csv = "nom;date_debut;date_fin;description;couleur;taches;calendriers\n"
            . "Ramadan;2026-03-01;2026-03-30;Fermeture pendant le mois sacré;Tomate;entree|mektaba;Calendrier Général\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-evenements.csv"',
        ]);
    }

    /**
     * Effets de bord communs à storeImport() (CSV) et storeManualImport()
     * (saisie manuelle) une fois les événements effectivement créés :
     * synchronisation Google Calendar par événement (queue) puis
     * régénération du planning si nécessaire (EvenementRegenerationService,
     * un seul appel pour tout le lot).
     *
     * @param array<int, Evenement> $evenements
     */
    private function finalizeImport(array $evenements): RedirectResponse
    {
        foreach ($evenements as $evenement) {
            $this->dispatchWebhookUpsert($evenement, 'post');
        }

        $message = count($evenements) . ' événement(s) importé(s) avec succès.';
        $regeneration = $this->regenerationService->regenererSiNecessaire($evenements);
        if ($regeneration !== null) {
            $message .= ' ' . $regeneration['message'];
        }

        return redirect()->route('evenements.index')->with('success', $message);
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

        $message = "Événement « {$evenement->nom} » mis à jour.";
        $regeneration = $this->regenerationService->regenererSiNecessaire($evenement);
        if ($regeneration !== null) {
            $message .= ' ' . $regeneration['message'];
        }

        return redirect()->route('evenements.index')->with('success', $message);
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
}