<?php
// app/Http/Controllers/CalendrierGoogleController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CalendrierGoogle;
use App\Services\GoogleCalendarService;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gère le registre des calendriers Google Calendar (ref_calendriers_google),
 * affiché et modifiable depuis la page Paramètres (`/parametres`).
 *
 * Existe parce qu'un compte de service ne peut pas découvrir automatiquement
 * les calendriers qui lui sont partagés (`calendarList.list()` renvoie
 * toujours une liste vide pour un compte de service — voir le docblock de
 * GoogleCalendarService::getCalendar() et docs/google_service_account.md).
 * Un gestionnaire/admin doit donc enregistrer chaque calendrier manuellement
 * (ID copié depuis Google Calendar → Paramètres → Intégrer l'agenda) ; cette
 * classe valide l'accès via `calendars.get()` à l'ajout et à la demande.
 *
 * Accès : gestionnaire + admin (middleware 'role:gestionnaire', voir
 * routes/web.php — même groupe que /parametres).
 *
 * Routes :
 *   POST   /parametres/calendriers-google              → store()
 *   PATCH  /parametres/calendriers-google/{id}          → update()
 *   DELETE /parametres/calendriers-google/{id}          → destroy()
 *   POST   /parametres/calendriers-google/{id}/verifier → verifier()
 */
class CalendrierGoogleController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarService $google,
    ) {
    }

    /**
     * Ajoute un calendrier au registre, après validation de l'accès via
     * `calendars.get()`. Le nom d'affichage soumis par l'utilisateur sert de
     * repli si Google ne renvoie pas de `summary` exploitable, mais le nom
     * renvoyé par Google est privilégié quand disponible (source de vérité).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'calendar_id' => ['required', 'string', 'max:200', 'unique:ref_calendriers_google,calendar_id'],
            'nom' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!$this->google->isConfigured()) {
            return back()->with('error', "GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 n'est pas configurée — impossible de vérifier l'accès à ce calendrier.");
        }

        try {
            $infos = $this->google->getCalendar($data['calendar_id']);
        } catch (GoogleServiceException $e) {
            return back()->withInput()->with('error', $this->messageErreurAcces($data['calendar_id'], $e));
        } catch (\Throwable $e) {
            Log::error('[CalendrierGoogleController] Échec vérification calendrier à l\'ajout', [
                'calendar_id' => $data['calendar_id'],
                'error' => $e->getMessage(),
            ]);
            return back()->withInput()->with('error', 'Erreur inattendue lors de la vérification : ' . $e->getMessage());
        }

        $calendrier = CalendrierGoogle::create([
            'calendar_id' => $data['calendar_id'],
            'nom' => $data['nom'] ?: $infos['name'],
            'description' => $data['description'] ?? null,
            'actif' => true,
            'derniere_verification_at' => now(),
        ]);

        audit('create', 'calendriers_google', $calendrier->id, null, $calendrier->toArray());

        return back()->with('success', "Calendrier « {$calendrier->nom} » ajouté et vérifié avec succès.");
    }

    /**
     * Modifie le nom/description/statut actif d'un calendrier déjà
     * enregistré. Le `calendar_id` lui-même n'est jamais modifiable une fois
     * créé — supprimer puis ré-ajouter si l'ID doit changer, pour éviter de
     * casser silencieusement le suivi des événements déjà synchronisés sur
     * l'ancien ID (plan_calendrier_evenements / ref_evenements_calendriers
     * référencent le calendar_id directement, pas cette ligne de registre).
     */
    public function update(Request $request, CalendrierGoogle $calendrierGoogle): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $avant = $calendrierGoogle->toArray();

        $calendrierGoogle->update([
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'actif' => $request->boolean('actif', $calendrierGoogle->actif),
        ]);

        audit('update', 'calendriers_google', $calendrierGoogle->id, $avant, $calendrierGoogle->fresh()->toArray());

        return back()->with('success', "Calendrier « {$calendrierGoogle->nom} » mis à jour.");
    }

    /**
     * Retire un calendrier du registre. N'a AUCUN effet sur les événements
     * déjà créés sur Google Calendar, ni sur les lignes
     * plan_calendrier_evenements / ref_evenements_calendriers existantes
     * (elles référencent le calendar_id, pas cette ligne de registre) — ce
     * calendrier disparaît seulement des dropdowns de sélection pour les
     * NOUVELLES assignations.
     */
    public function destroy(CalendrierGoogle $calendrierGoogle): RedirectResponse
    {
        $avant = $calendrierGoogle->toArray();
        $nom = $calendrierGoogle->nom;
        $calendrierGoogle->delete();

        audit('delete', 'calendriers_google', $avant['id'] ?? null, $avant, null);

        return back()->with('success', "Calendrier « {$nom} » retiré du registre.");
    }

    /**
     * Revérifie l'accès à un calendrier déjà enregistré (utile après une
     * modification des droits de partage côté Google Calendar, ou pour
     * confirmer qu'un accès n'a pas été révoqué).
     */
    public function verifier(CalendrierGoogle $calendrierGoogle): RedirectResponse
    {
        if (!$this->google->isConfigured()) {
            return back()->with('error', "GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 n'est pas configurée.");
        }

        try {
            $infos = $this->google->getCalendar($calendrierGoogle->calendar_id);
        } catch (GoogleServiceException $e) {
            return back()->with('error', $this->messageErreurAcces($calendrierGoogle->calendar_id, $e));
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur inattendue lors de la vérification : ' . $e->getMessage());
        }

        $calendrierGoogle->update(['derniere_verification_at' => now()]);

        return back()->with('success', "Accès confirmé à « {$infos['name']} » (dernière vérification : à l'instant).");
    }

    private function messageErreurAcces(string $calendarId, GoogleServiceException $e): string
    {
        return match ($e->getCode()) {
            404 => "Calendrier « {$calendarId} » introuvable ou non partagé avec le compte de service. Vérifiez l'ID et le partage (voir docs/google_service_account.md).",
            403 => "Accès refusé au calendrier « {$calendarId} » — partagé mais avec des droits insuffisants, ou API non activée.",
            default => "Échec de la vérification (HTTP {$e->getCode()}) : " . $e->getMessage(),
        };
    }
}
