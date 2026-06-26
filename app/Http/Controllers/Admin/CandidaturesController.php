<?php
// app/Http/Controllers/Admin/CandidaturesController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personne;
use App\Models\Role;
use App\Notifications\CandidatureValideeNotification;
use App\Notifications\CandidatureValideeDejaInscritNotification;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class CandidaturesController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {
    }

    public function index(): View
    {
        $candidatures = Personne::enAttente()
            ->with(['restrictions.tache'])
            ->orderBy('derniere_maj', 'desc')
            ->get();

        $roles = $this->roleService->planningRoles()
            ->whereIn('code', ['admin', 'gestionnaire', 'membre']);

        return view('admin.candidatures.index', compact('candidatures', 'roles'));
    }

    public function valider(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'in:admin,gestionnaire,membre'],
        ], [
            'role.required' => 'Veuillez sélectionner un rôle.',
            'role.in' => 'Rôle invalide.',
        ]);

        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'En attente') {
            return redirect()->route('admin.candidatures.index')
                ->with('error', 'Cette candidature n\'est plus en attente.');
        }

        $avant = $personne->toArray();
        $roleCode = $request->input('role', 'membre');

        $personne->statut = 'Validé';
        $personne->date_debut_planning = now()->toDateString();
        $personne->save();

        $this->roleService->syncRolePlanning($personne, $roleCode);

        $dejaMotDePasse = !empty($personne->password);

        if ($dejaMotDePasse) {
            try {
                $personne->notify(new CandidatureValideeDejaInscritNotification(route('login')));
                Log::info('[CandidaturesController] Email connexion directe envoyé', ['id' => $personne->id]);
            } catch (\Throwable $e) {
                Log::error('[CandidaturesController] Échec email connexion directe', [
                    'id' => $personne->id,
                    'erreur' => $e->getMessage(),
                    'fichier' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
            $messageFlash = "Candidature de {$personne->prenom} {$personne->nom} validée (rôle : {$roleCode}). Email de connexion directe envoyé.";
        } else {
            $token = Password::broker('personnes')->createToken($personne);
            $resetUrl = route('password.reset', ['token' => $token, 'email' => $personne->email]);
            try {
                $personne->notify(new CandidatureValideeNotification($resetUrl));
                Log::info('[CandidaturesController] Email invitation envoyé', ['id' => $personne->id]);
            } catch (\Throwable $e) {
                Log::error('[CandidaturesController] Échec email invitation', [
                    'id' => $personne->id,
                    'erreur' => $e->getMessage(),
                    'fichier' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
            $messageFlash = "Candidature de {$personne->prenom} {$personne->nom} validée (rôle : {$roleCode}). Email d'invitation envoyé.";
        }

        audit('update', 'candidatures', $personne->id, $avant, [
            'statut' => 'Validé',
            'action' => 'validation',
            'role' => $roleCode,
            'deja_mot_de_passe' => $dejaMotDePasse,
        ]);

        return redirect()->route('admin.candidatures.index')->with('success', $messageFlash);
    }

    public function refuser(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'En attente') {
            return redirect()->route('admin.candidatures.index')
                ->with('error', 'Cette candidature n\'est plus en attente.');
        }

        $avant = $personne->toArray();
        $personne->statut = 'Archivé';
        $personne->save();

        audit('update', 'candidatures', $personne->id, $avant, [
            'statut' => 'Archivé',
            'action' => 'candidature refusée',
        ]);

        return redirect()->route('admin.candidatures.index')
            ->with('success', "Candidature de {$personne->prenom} {$personne->nom} refusée.");
    }

    public function renvoyerInvitation(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'Validé') {
            return redirect()->route('admin.candidatures.index')
                ->with('error', 'Impossible de renvoyer une invitation à un compte non validé.');
        }

        $dejaMotDePasse = !empty($personne->password);

        if ($dejaMotDePasse) {
            try {
                $personne->notify(new CandidatureValideeDejaInscritNotification(route('login')));
                Log::info('[CandidaturesController] Email renvoi connexion directe', ['id' => $personne->id]);
            } catch (\Throwable $e) {
                Log::error('[CandidaturesController] Échec email renvoi connexion directe', [
                    'id' => $personne->id,
                    'erreur' => $e->getMessage(),
                    'fichier' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
            $messageFlash = "Email de connexion renvoyé à {$personne->prenom} {$personne->nom}.";
        } else {
            $token = Password::broker('personnes')->createToken($personne);
            $resetUrl = route('password.reset', ['token' => $token, 'email' => $personne->email]);
            try {
                $personne->notify(new CandidatureValideeNotification($resetUrl));
                Log::info('[CandidaturesController] Email renvoi invitation', ['id' => $personne->id]);
            } catch (\Throwable $e) {
                Log::error('[CandidaturesController] Échec email renvoi invitation', [
                    'id' => $personne->id,
                    'erreur' => $e->getMessage(),
                    'fichier' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
            $messageFlash = "Invitation renvoyée à {$personne->prenom} {$personne->nom}.";
        }

        audit('update', 'candidatures', $personne->id, null, [
            'action' => 'invitation renvoyée',
            'deja_mot_de_passe' => $dejaMotDePasse,
        ]);

        return redirect()->route('admin.candidatures.index')->with('success', $messageFlash);
    }
}