<?php
// app/Http/Controllers/Admin/CandidaturesController.php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Personne;
use App\Models\Role;
use App\Notifications\CandidatureValideeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Contrôleur admin — gestion des candidatures en attente.
 *
 * Routes :
 *   GET  /admin/candidatures         → liste des candidatures En attente
 *   POST /admin/candidatures/{id}/valider  → valider + envoyer invitation
 *   POST /admin/candidatures/{id}/refuser  → refuser + archiver
 */
class CandidaturesController extends Controller
{
    /**
     * Liste toutes les candidatures en attente de validation.
     */
    public function index(): View
    {
        $candidatures = Personne::enAttente()
            ->with(['vehicule', 'restrictions.tache'])
            ->orderBy('derniere_maj', 'desc')
            ->get();

        return view('admin.candidatures.index', compact('candidatures'));
    }

    /**
     * Valide une candidature :
     *   1. Passe le statut à 'Validé'
     *   2. Attribue le rôle 'membre' dans planning
     *   3. Génère un token de reset password
     *   4. Envoie l'email d'invitation avec le lien de création de mot de passe
     */
    public function valider(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'En attente') {
            return redirect()->route('admin.candidatures.index')
                ->with('error', 'Cette candidature n\'est plus en attente.');
        }

        $avant = $personne->toArray();

        // ── 1. Valider le statut ───────────────────────────────────────────
        $personne->statut               = 'Validé';
        $personne->date_debut_planning  = now()->toDateString();
        $personne->save();

        // ── 2. Attribuer le rôle membre ────────────────────────────────────
        $planningApp = Application::where('code', 'planning')->first();
        $roleMembre  = Role::where('code', 'membre')
            ->where('id_application', $planningApp->id)
            ->first();

        if ($roleMembre) {
            $dejaAttribue = DB::table('ref_personnes_roles')
                ->where('id_personne', $personne->id)
                ->where('id_role', $roleMembre->id)
                ->exists();

            if (! $dejaAttribue) {
                DB::table('ref_personnes_roles')->insert([
                    'id_personne'      => $personne->id,
                    'id_role'          => $roleMembre->id,
                    'date_attribution' => now()->toDateString(),
                ]);
            }
        }

        // ── 3. Générer le lien d'invitation (token de reset password) ──────
        //
        // On réutilise le système de reset password de Laravel.
        // Le token est stocké dans password_reset_tokens et expire en 60 min.
        // C'est exactement le même mécanisme que "mot de passe oublié",
        // mais présenté comme une "création de mot de passe" dans l'email.
        //
        $token    = Password::broker('personnes')->createToken($personne);
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $personne->email,
        ]);

        // ── 4. Envoyer l'email d'invitation ────────────────────────────────
        $personne->notify(new CandidatureValideeNotification($resetUrl));

        audit('update', 'candidatures', $personne->id, $avant, [
            'statut' => 'Validé',
            'action' => 'validation + invitation envoyée',
        ]);

        return redirect()->route('admin.candidatures.index')
            ->with('success', "Candidature de {$personne->prenom} {$personne->nom} validée. Un email d'invitation a été envoyé.");
    }

    /**
     * Refuse une candidature et archive la personne.
     * La personne reste dans la base avec statut 'Archivé'
     * pour éviter une réinscription immédiate.
     */
    public function refuser(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'En attente') {
            return redirect()->route('admin.candidatures.index')
                ->with('error', 'Cette candidature n\'est plus en attente.');
        }

        $avant           = $personne->toArray();
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
     * Renvoie l'email d'invitation à un membre validé
     * qui n'aurait pas reçu ou cliqué sur son lien.
     */
    public function renvoyerInvitation(int $id): RedirectResponse
    {
        $personne = Personne::findOrFail($id);

        if ($personne->statut !== 'Validé') {
            return redirect()->route('admin.candidatures.index')
                ->with('error', 'Impossible de renvoyer une invitation à un compte non validé.');
        }

        if (! empty($personne->password)) {
            return redirect()->route('admin.candidatures.index')
                ->with('error', "{$personne->prenom} {$personne->nom} a déjà créé son mot de passe.");
        }

        $token    = Password::broker('personnes')->createToken($personne);
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $personne->email,
        ]);

        $personne->notify(new CandidatureValideeNotification($resetUrl));

        audit('update', 'candidatures', $personne->id, null, [
            'action' => 'invitation renvoyée',
        ]);

        return redirect()->route('admin.candidatures.index')
            ->with('success', "Invitation renvoyée à {$personne->prenom} {$personne->nom}.");
    }
}
