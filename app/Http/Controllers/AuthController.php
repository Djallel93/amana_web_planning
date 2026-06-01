<?php
// app/Http/Controllers/AuthController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Personne;
use App\Notifications\NouveauMembreNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Contrôleur d'authentification.
 *
 * Gère l'ensemble du cycle d'authentification :
 *   - Connexion / déconnexion
 *   - Mot de passe oublié (envoi du lien de reset)
 *   - Réinitialisation du mot de passe (via le lien reçu par email)
 *   - Premier login (création du mot de passe via invitation)
 *
 * Le "premier login" et le "reset de mot de passe" utilisent le même
 * mécanisme Laravel (password broker) — la seule différence est le
 * texte affiché à l'utilisateur.
 */
class AuthController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────
    // CONNEXION
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Affiche le formulaire de connexion.
     * Redirige vers le planning si déjà connecté.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('planning.index');
        }

        return view('auth.login');
    }

    /**
     * Traite la soumission du formulaire de connexion.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'email.required'    => 'L\'adresse email est obligatoire.',
            'email.email'       => 'Format d\'email invalide.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        // Vérifier que le compte existe et est validé avant de tenter l'auth
        $personne = Personne::where('email', $credentials['email'])->first();

        if ($personne && $personne->statut === 'En attente') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Votre candidature est en attente de validation par un administrateur.']);
        }

        if ($personne && $personne->statut === 'Suspendu') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Votre compte a été suspendu. Contactez un administrateur.']);
        }

        if ($personne && $personne->statut === 'Archivé') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Ce compte est archivé.']);
        }

        // Vérifier que le compte a un mot de passe défini
        // (un membre invité qui n'a pas encore cliqué sur son lien)
        if ($personne && empty($personne->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Vous n\'avez pas encore créé votre mot de passe. Vérifiez vos emails ou contactez un administrateur.']);
        }

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            audit('login', 'auth');

            session()->flash('success', 'Bienvenue ' . Auth::user()->prenom . ' !');

            return redirect()->intended(route('planning.index'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Email ou mot de passe incorrect.']);
    }

    /**
     * Déconnexion.
     */
    public function logout(Request $request): RedirectResponse
    {
        audit('logout', 'auth');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Vous avez été déconnecté.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // MOT DE PASSE OUBLIÉ
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Affiche le formulaire "mot de passe oublié".
     */
    public function showForgotPassword(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('planning.index');
        }

        return view('auth.forgot-password');
    }

    /**
     * Envoie le lien de réinitialisation par email.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email'    => 'Format d\'email invalide.',
        ]);

        // Laravel vérifie que l'email existe dans le provider 'personnes'
        // et envoie le lien si c'est le cas.
        // On retourne toujours le même message pour ne pas révéler
        // si l'email existe ou non dans la base (sécurité).
        $status = Password::broker('personnes')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Un lien de réinitialisation a été envoyé à votre adresse email.');
        }

        // Throttle : l'utilisateur a déjà demandé un lien récemment
        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'Veuillez patienter avant de demander un nouveau lien.']);
        }

        // Email non trouvé — on retourne le même message générique
        // pour ne pas révéler si l'email existe dans la base
        return back()->with('success', 'Si cette adresse est connue, un lien vous a été envoyé.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // RÉINITIALISATION DU MOT DE PASSE
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Affiche le formulaire de création/réinitialisation du mot de passe.
     * Utilisé pour le reset classique ET pour le premier login via invitation.
     */
    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Traite la création/réinitialisation du mot de passe.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ], [
            'password.required'              => 'Le mot de passe est obligatoire.',
            'password.min'                   => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'             => 'Les mots de passe ne correspondent pas.',
            'password_confirmation.required' => 'Veuillez confirmer votre mot de passe.',
        ]);

        $status = Password::broker('personnes')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Personne $personne, string $password) {
                $personne->password       = Hash::make($password);
                $personne->remember_token = Str::random(60);

                // Marquer l'email comme vérifié lors de la première création
                // de mot de passe via invitation
                if (! $personne->email_verified_at) {
                    $personne->email_verified_at = now();
                }

                $personne->save();

                event(new PasswordReset($personne));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Votre mot de passe a été créé avec succès. Vous pouvez maintenant vous connecter.');
        }

        // Token expiré ou invalide
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Ce lien est invalide ou a expiré. Veuillez en demander un nouveau.']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // INSCRIPTION
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Affiche le formulaire d'inscription public.
     */
    public function showInscription(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('planning.index');
        }

        $vehicules = \App\Models\Vehicule::orderBy('type')->get();
        $taches    = \App\Models\Tache::actif()->orderBy('id')->get();
        $jours     = ['Vendredi', 'Samedi'];

        return view('auth.inscription', compact('vehicules', 'taches', 'jours'));
    }

    /**
     * Traite la soumission du formulaire d'inscription.
     *
     * Crée la personne avec statut 'En attente', enregistre ses restrictions,
     * puis notifie tous les admins planning par email.
     */
    public function inscription(Request $request): RedirectResponse
    {
        $request->validate([
            'nom'                       => ['required', 'string', 'max:100'],
            'prenom'                    => ['required', 'string', 'max:100'],
            'email'                     => ['required', 'email', 'max:255', 'unique:ref_personnes,email'],
            'telephone'                 => ['nullable', 'string', 'max:20'],
            'id_vehicule'               => ['nullable', 'integer', 'exists:ref_vehicules,id'],
            'date_inscription_benevole' => ['nullable', 'date'],
            // Restrictions : tableau optionnel de cases cochées
            'restrictions'              => ['nullable', 'array'],
        ], [
            'nom.required'    => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'email.required'  => 'L\'adresse email est obligatoire.',
            'email.unique'    => 'Cette adresse email est déjà utilisée.',
            'email.email'     => 'Format d\'email invalide.',
        ]);

        // ── Créer la personne ──────────────────────────────────────────────
        $personne = Personne::create([
            'nom'                       => $request->nom,
            'prenom'                    => $request->prenom,
            'email'                     => $request->email,
            'telephone'                 => $request->telephone,
            'id_vehicule'               => $request->id_vehicule,
            'date_inscription_benevole' => $request->date_inscription_benevole ?? now()->toDateString(),
            'statut'                    => 'En attente',
            'tirelire'                  => false,
            // Pas de password — sera créé via lien d'invitation après validation
        ]);

        // ── Enregistrer les restrictions ───────────────────────────────────
        // Le membre a coché les cases pour ce qu'il NE peut PAS faire.
        // Par défaut tout est autorisé — on n'enregistre que les refus.
        $taches = \App\Models\Tache::actif()->get();
        $jours  = ['Vendredi', 'Samedi'];
        $restrictionsPost = $request->input('restrictions', []);

        foreach ($taches as $tache) {
            foreach ($jours as $jour) {
                // La case est cochée = autorisé, absente = refus
                $autorise = isset($restrictionsPost[$tache->id][$jour]);

                \App\Models\Restriction::updateOrCreate(
                    [
                        'id_personne' => $personne->id,
                        'id_tache'    => $tache->id,
                        'jour'        => $jour,
                    ],
                    ['autorise' => $autorise]
                );
            }
        }

        // ── Notifier tous les admins planning ─────────────────────────────
        $admins = Personne::adminsPlanning()->get();

        if ($admins->isNotEmpty()) {
            Notification::send(
                $admins,
                new NouveauMembreNotification($personne)
            );
        }

        audit('create', 'inscription', $personne->id, null, [
            'email'  => $personne->email,
            'statut' => 'En attente',
        ]);

        return redirect()->route('login')
            ->with('success', 'Votre candidature a bien été enregistrée. Un administrateur va l\'examiner et vous recevrez un email une fois votre compte activé.');
    }
}
