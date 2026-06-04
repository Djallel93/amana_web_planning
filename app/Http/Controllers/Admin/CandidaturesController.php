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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Contrôleur admin — gestion des candidatures en attente.
 *
 * Routes :
 *   GET  /admin/candidatures                        → liste des candidatures En attente
 *   POST /admin/candidatures/{id}/valider           → valider + envoyer invitation
 *   POST /admin/candidatures/{id}/refuser           → refuser + archiver
 *   POST /admin/candidatures/{id}/renvoyer-invitation → renvoyer l'email d'invitation
 *
 * Logique email lors de la validation :
 *   - Si la personne n'a PAS encore de mot de passe
 *     → CandidatureValideeNotification       (lien de création de mot de passe)
 *   - Si la personne a DÉJÀ un mot de passe (compte existant sur autre app AMANA)
 *     → CandidatureValideeDejaInscritNotification  (lien direct vers la connexion)
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
     * Valide une candidature.
     *
     * Étapes :
     *   1. Passe le statut à 'Validé' et définit date_debut_planning
     *   2. Attribue le rôle 'membre' dans planning
     *   3. Vérifie si la personne a déjà un mot de passe
     *      → Oui : envoie CandidatureValideeDejaInscritNotification (lien login direct)
     *      → Non : génère un token de reset et envoie CandidatureValideeNotification
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
        $personne->statut = 'Validé';
        $personne->date_debut_planning = now()->toDateString();
        $personne->save();

        // ── 2. Attribuer le rôle membre ────────────────────────────────────
        $this->attribuerRoleMembre($personne);

        // ── 3. Email selon présence d'un mot de passe ──────────────────────
        $dejaMotDePasse = !empty($personne->password);

        if ($dejaMotDePasse) {
            // Compte déjà actif sur une autre app AMANA — connexion directe
            $personne->notify(
                new CandidatureValideeDejaInscritNotification(
                    route('login')
                )
            );

            $messageFlash = "Candidature de {$personne->prenom} {$personne->nom} validée. "
                . "Un email l'informant de se connecter directement a été envoyé "
                . "(compte déjà existant).";
        } else {
            // Nouveau compte — lien de création de mot de passe
            $token = Password::broker('personnes')->createToken($personne);
            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $personne->email,
            ]);

            $personne->notify(new CandidatureValideeNotification($resetUrl));

            $messageFlash = "Candidature de {$personne->prenom} {$personne->nom} validée. "
                . "Un email d'invitation a été envoyé.";
        }

        audit('update', 'candidatures', $personne->id, $avant, [
            'statut' => 'Validé',
            'action' => 'validation',
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
     *
     * Même logique que valider() :
     *   - Déjà un mot de passe → email "connexion directe"
     *   - Pas de mot de passe  → nouveau lien de reset
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
            // A déjà un mot de passe — inutile de renvoyer un lien de reset
            $personne->notify(
                new CandidatureValideeDejaInscritNotification(
                    route('login')
                )
            );

            $messageFlash = "Email de connexion renvoyé à {$personne->prenom} {$personne->nom} "
                . "(compte déjà existant, connexion directe).";
        } else {
            // Pas encore de mot de passe — renvoyer le lien de création
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

    // ──────────────────────────────────────────────────────────────────────
    // MÉTHODES PRIVÉES
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Attribue le rôle 'membre' dans l'application planning si pas déjà attribué.
     */
    private function attribuerRoleMembre(Personne $personne): void
    {
        $planningApp = Application::where('code', 'planning')->first();

        if (!$planningApp) {
            return;
        }

        $roleMembre = Role::where('code', 'membre')
            ->where('id_application', $planningApp->id)
            ->first();

        if (!$roleMembre) {
            return;
        }

        $dejaAttribue = DB::table('ref_personnes_roles')
            ->where('id_personne', $personne->id)
            ->where('id_role', $roleMembre->id)
            ->exists();

        if (!$dejaAttribue) {
            DB::table('ref_personnes_roles')->insert([
                'id_personne' => $personne->id,
                'id_role' => $roleMembre->id,
                'date_attribution' => now()->toDateString(),
            ]);
        }
    }
}