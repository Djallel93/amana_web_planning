<?php
// app/Http/Controllers/EchangeController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Creneau;
use App\Models\CreneauTache;
use App\Models\Echange;
use App\Services\EchangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Contrôleur pour les échanges de créneaux.
 *
 * Routes membres :
 *   GET  /echanges                          → index (mes échanges)
 *   GET  /echanges/slots-disponibles        → JSON : slots échangeables pour un slot donné
 *   POST /echanges                          → créer une demande
 *   DELETE /echanges/{id}                   → annuler (demandeur uniquement)
 *
 * Routes publiques tokenisées (pas de middleware auth — lien par email) :
 *   GET  /echanges/{token}/accepter         → B accepte
 *   GET  /echanges/{token}/refuser          → B refuse
 *
 * Routes admin/gestionnaire :
 *   GET  /admin/echanges                    → liste des échanges en attente
 *   POST /admin/echanges/{id}/approuver     → approuver
 *   POST /admin/echanges/{id}/refuser       → refuser
 */
class EchangeController extends Controller
{
    public function __construct(
        private readonly EchangeService $service,
    ) {
    }

    // ── Vue membre : mes échanges ──────────────────────────────────────────

    /**
     * Liste les échanges impliquant le membre connecté.
     */
    public function index(): View
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $echanges = Echange::with([
            'demandeur', 'cible',
            'creneauDemandeur', 'creneauCible',
            'tacheDemandeur', 'tacheCible',
        ])
            ->impliquant($user->id)
            ->orderByDesc('created_at')
            ->get();

        return view('echanges.index', compact('echanges', 'user'));
    }

    // ── AJAX : slots échangeables ──────────────────────────────────────────

    /**
     * Retourne les slots futurs échangeables pour un créneau/tâche donnés.
     * Appelé en AJAX depuis la modale d'échange dans "Mon planning".
     *
     * GET /echanges/slots-disponibles?creneau_id=X&tache_id=Y
     */
    public function slotsDisponibles(Request $request): JsonResponse
    {
        $request->validate([
            'creneau_id' => ['required', 'integer', 'exists:plan_creneaux,id'],
            'tache_id'   => ['required', 'integer', 'exists:ref_taches,id'],
        ]);

        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $slots = $this->service->slotsEchangeables(
            (int) $request->creneau_id,
            (int) $request->tache_id,
            $user->id
        );

        return response()->json(
            $slots->map(fn($ct) => [
                'creneau_id'   => $ct->id_planning,
                'tache_id'     => $ct->id_tache,
                'personne_id'  => $ct->id_personne,
                'personne_nom' => $ct->personne
                    ? $ct->personne->prenom . ' ' . $ct->personne->nom
                    : '—',
                'date'         => $ct->creneau->date->toDateString(),
                'date_label'   => $ct->creneau->date->locale('fr')->isoFormat('dddd D MMMM YYYY'),
                'jour'         => $ct->creneau->jour,
                'tache_libelle' => $ct->tache?->libelle ?? '—',
            ])
        );
    }

    // ── Créer une demande ──────────────────────────────────────────────────

    /**
     * POST /echanges
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'creneau_demandeur_id' => ['required', 'integer', 'exists:plan_creneaux,id'],
            'tache_demandeur_id'   => ['required', 'integer', 'exists:ref_taches,id'],
            'creneau_cible_id'     => ['required', 'integer', 'exists:plan_creneaux,id'],
            'tache_cible_id'       => ['required', 'integer', 'exists:ref_taches,id'],
            'personne_cible_id'    => ['required', 'integer', 'exists:ref_personnes,id'],
        ]);

        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        try {
            $echange = $this->service->creerDemande(
                personneAId: $user->id,
                creneauAId:  (int) $request->creneau_demandeur_id,
                tacheAId:    (int) $request->tache_demandeur_id,
                personneBId: (int) $request->personne_cible_id,
                creneauBId:  (int) $request->creneau_cible_id,
                tacheBId:    (int) $request->tache_cible_id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Demande d\'échange envoyée. '
                    . $echange->cible->prenom . ' ' . $echange->cible->nom
                    . ' va recevoir un email pour accepter ou refuser.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ── Annuler (demandeur) ────────────────────────────────────────────────

    /**
     * DELETE /echanges/{id}
     */
    public function destroy(int $id): JsonResponse|RedirectResponse
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        try {
            $this->service->annulerParDemandeur($id, $user->id);

            if (request()->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Demande d\'échange annulée.']);
            }
            return redirect()->route('echanges.index')
                ->with('success', 'Demande d\'échange annulée.');
        } catch (\RuntimeException $e) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->route('echanges.index')->with('error', $e->getMessage());
        }
    }

    // ── Tokens publics (liens email, pas de middleware auth) ───────────────

    /**
     * GET /echanges/{token}/accepter
     * Accessible sans connexion — lien reçu par email.
     */
    public function accepter(string $token): View
    {
        try {
            $echange = $this->service->accepterParToken($token);
            return view('echanges.token-result', [
                'success'  => true,
                'action'   => 'accepte',
                'echange'  => $echange,
                'urlLogin' => route('login'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return view('echanges.token-result', [
                'success' => false,
                'action'  => 'invalide',
                'message' => 'Ce lien est invalide ou a déjà été utilisé.',
                'urlLogin' => route('login'),
            ]);
        } catch (\RuntimeException $e) {
            return view('echanges.token-result', [
                'success'  => false,
                'action'   => 'erreur',
                'message'  => $e->getMessage(),
                'urlLogin' => route('login'),
            ]);
        }
    }

    /**
     * GET /echanges/{token}/refuser
     * Accessible sans connexion — lien reçu par email.
     */
    public function refuser(string $token): View
    {
        try {
            $echange = $this->service->refuserParToken($token);
            return view('echanges.token-result', [
                'success'  => true,
                'action'   => 'refuse',
                'echange'  => $echange,
                'urlLogin' => route('login'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return view('echanges.token-result', [
                'success' => false,
                'action'  => 'invalide',
                'message' => 'Ce lien est invalide ou a déjà été utilisé.',
                'urlLogin' => route('login'),
            ]);
        } catch (\RuntimeException $e) {
            return view('echanges.token-result', [
                'success'  => false,
                'action'   => 'erreur',
                'message'  => $e->getMessage(),
                'urlLogin' => route('login'),
            ]);
        }
    }

    // ── Admin / Gestionnaire ───────────────────────────────────────────────

    /**
     * GET /admin/echanges
     */
    public function adminIndex(): View
    {
        $echanges = Echange::with([
            'demandeur', 'cible',
            'creneauDemandeur', 'creneauCible',
            'tacheDemandeur', 'tacheCible',
            'approbateur',
        ])
            ->orderByRaw("FIELD(statut, 'en_attente') DESC")
            ->orderByDesc('created_at')
            ->paginate(30);

        $nbEnAttente = Echange::enAttente()->count();

        return view('echanges.admin', compact('echanges', 'nbEnAttente'));
    }

    /**
     * POST /admin/echanges/{id}/approuver
     */
    public function adminApprouver(int $id): RedirectResponse
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        try {
            $this->service->approuverParAdmin($id, $user->id);
            return redirect()->route('admin.echanges.index')
                ->with('success', 'Échange approuvé et exécuté.');
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.echanges.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * POST /admin/echanges/{id}/refuser
     */
    public function adminRefuser(int $id): RedirectResponse
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        try {
            $this->service->refuserParAdmin($id, $user->id);
            return redirect()->route('admin.echanges.index')
                ->with('success', 'Échange refusé.');
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.echanges.index')
                ->with('error', $e->getMessage());
        }
    }
}
