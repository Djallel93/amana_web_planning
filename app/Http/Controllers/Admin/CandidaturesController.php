<?php
// app/Http/Controllers/Admin/CandidaturesController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Personne;
use App\Models\Role;
use App\Notifications\CandidatureValideeNotification;
use App\Notifications\CandidatureValideeDejaInscritNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class CandidaturesController extends Controller
{
    /**
     * Liste toutes les candidatures en attente.
     */
    public function index(): View
    {
        $candidatures = Personne::enAttente()
            ->with(['vehicule', 'restrictions.tache'])
            ->orderBy('derniere_maj', 'desc')
            ->get();

        // Pass available planning roles to the view for the role selector
        $planningApp = Application::where('code', 'planning')->first();
        $roles = $planningApp
            ? Role::where('id_application', $planningApp->id)
                ->whereIn('code', ['admin', 'gestionnaire', 'membre'])
                ->orderByRaw("FIELD(code, 'admin', 'gestionnaire', 'membre')")
                ->get()
            : collect();

        return view('admin.candidatures.index', compact('candidatures', 'roles'));
    }

    /**
     * Valide une candidature avec le rôle choisi par l'admin.
     */
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

        // ── 1. Valider le statut ───────────────────────────────────────────
        $personne->statut = 'Validé';
        $personne->date_debut_planning = now()->toDateString();
        $personne->save();

        // ── 2. Attribuer le rôle choisi ────────────────────────────────────
        $this->attribuerRole($personne, $roleCode);

        // ── 3. Email selon présence d'un mot de passe ──────────────────────
        $dejaMotDePasse = !empty($personne->password);

        if ($dejaMotDePasse) {
            $personne->notify(
                new CandidatureValideeDejaInscritNotification(route('login'))
            );
            $messageFlash = "Candidature de {$personne->prenom} {$personne->nom} validée (rôle : {$roleCode}). "
                . "Email de connexion directe envoyé (compte déjà existant).";
        } else {
            $token = Password::broker('personnes')->createToken($personne);
            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $personne->email,
            ]);
            $personne->notify(new CandidatureValideeNotification($resetUrl));
            $messageFlash = "Candidature de {$personne->prenom} {$personne->nom} validée (rôle : {$roleCode}). "
                . "Email d'invitation envoyé.";
        }

        audit('update', 'candidatures', $personne->id, $avant, [
            'statut' => 'Validé',
            'action' => 'validation',
            'role' => $roleCode,
            'deja_mot_de_passe' => $dejaMotDePasse,
        ]);

        return redirect()->route('admin.candidatures.index')
            ->with('success', $messageFlash);
    }

    /**
     * Refuse une candidature et archive la personne.
     */
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

    /**
     * Renvoie l'email d'invitation à un membre validé.
     */
    public function renvoyerInvitation(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'Validé') {
            return redirect()->route('admin.candidatures.index')
                ->with('error', 'Impossible de renvoyer une invitation à un compte non validé.');
        }

        $dejaMotDePasse = !empty($personne->password);

        if ($dejaMotDePasse) {
            $personne->notify(
                new CandidatureValideeDejaInscritNotification(route('login'))
            );
            $messageFlash = "Email de connexion renvoyé à {$personne->prenom} {$personne->nom} "
                . "(compte déjà existant, connexion directe).";
        } else {
            $token = Password::broker('personnes')->createToken($personne);
            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $personne->email,
            ]);
            $personne->notify(new CandidatureValideeNotification($resetUrl));
            $messageFlash = "Invitation renvoyée à {$personne->prenom} {$personne->nom}.";
        }

        audit('update', 'candidatures', $personne->id, null, [
            'action' => 'invitation renvoyée',
            'deja_mot_de_passe' => $dejaMotDePasse,
        ]);

        return redirect()->route('admin.candidatures.index')
            ->with('success', $messageFlash);
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * Attribue un rôle planning à une personne.
     * Supprime d'abord tous ses rôles planning existants (un seul rôle par app).
     */
    private function attribuerRole(Personne $personne, string $roleCode): void
    {
        $planningApp = Application::where('code', 'planning')->first();
        if (!$planningApp) {
            return;
        }

        // Supprimer tous les rôles planning existants
        $planningRoleIds = Role::where('id_application', $planningApp->id)->pluck('id')->toArray();
        if (!empty($planningRoleIds)) {
            DB::table('ref_personnes_roles')
                ->where('id_personne', $personne->id)
                ->whereIn('id_role', $planningRoleIds)
                ->delete();
        }

        // Attribuer le nouveau rôle
        $role = Role::where('code', $roleCode)
            ->where('id_application', $planningApp->id)
            ->first();

        if ($role) {
            DB::table('ref_personnes_roles')->insert([
                'id_personne' => $personne->id,
                'id_role' => $role->id,
                'date_attribution' => now()->toDateString(),
            ]);
        }
    }
}