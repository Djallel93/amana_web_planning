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

class AuthController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────
    // CONNEXION
    // ──────────────────────────────────────────────────────────────────────

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('planning.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Format d\'email invalide.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        $personne = Personne::where('email', $credentials['email'])->first();

        if ($personne && $personne->statut === 'En attente') {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Votre candidature est en attente de validation par un administrateur.']);
        }

        if ($personne && $personne->statut === 'Suspendu') {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Votre compte a été suspendu. Contactez un administrateur.']);
        }

        if ($personne && $personne->statut === 'Archivé') {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Ce compte est archivé.']);
        }

        if ($personne && empty($personne->password)) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Vous n\'avez pas encore créé votre mot de passe. Vérifiez vos emails ou contactez un administrateur.']);
        }

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            audit('login', 'auth');
            session()->flash('success', 'Bienvenue ' . Auth::user()->prenom . ' !');
            return redirect()->intended(route('planning.index'));
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => 'Email ou mot de passe incorrect.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        audit('logout', 'auth');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Vous avez été déconnecté.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // MOT DE PASSE OUBLIÉ
    // ──────────────────────────────────────────────────────────────────────

    public function showForgotPassword(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('planning.index');
        }

        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Format d\'email invalide.',
        ]);

        $status = Password::broker('personnes')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Un lien de réinitialisation a été envoyé à votre adresse email.');
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()
                ->withErrors(['email' => 'Veuillez patienter avant de demander un nouveau lien.']);
        }

        return back()->with('success', 'Si cette adresse est connue, un lien vous a été envoyé.');
    }

    // ──────────────────────────────────────────────────────────────────────
    // RÉINITIALISATION DU MOT DE PASSE
    // ──────────────────────────────────────────────────────────────────────

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ], [
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'password_confirmation.required' => 'Veuillez confirmer votre mot de passe.',
        ]);

        $status = Password::broker('personnes')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Personne $personne, string $password) {
                $personne->password = Hash::make($password);
                $personne->remember_token = Str::random(60);

                if (!$personne->email_verified_at) {
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

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => 'Ce lien est invalide ou a expiré. Veuillez en demander un nouveau.']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // INSCRIPTION
    // ──────────────────────────────────────────────────────────────────────

    public function showInscription(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('planning.index');
        }

        $taches = \App\Models\Tache::actif()->orderBy('id')->get();
        $jours = ['Vendredi', 'Samedi'];

        return view('auth.inscription', compact('taches', 'jours'));
    }

    public function inscription(Request $request): RedirectResponse
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:ref_personnes,email'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'restrictions' => ['nullable', 'array'],
        ], [
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'email.email' => 'Format d\'email invalide.',
        ]);

        // ── Créer la personne ──────────────────────────────────────────────
        $personne = Personne::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'statut' => 'En attente',
        ]);

        // ── Enregistrer les restrictions ───────────────────────────────────
        $taches = \App\Models\Tache::actif()->get();
        $jours = ['Vendredi', 'Samedi'];
        $restrictionsPost = $request->input('restrictions', []);

        foreach ($taches as $tache) {
            foreach ($jours as $jour) {
                $autorise = isset($restrictionsPost[$tache->id][$jour]);

                \App\Models\Restriction::updateOrCreate(
                    ['id_personne' => $personne->id, 'id_tache' => $tache->id, 'jour' => $jour],
                    ['autorise' => $autorise]
                );
            }
        }

        // ── Recharger avec les relations nécessaires au template email ─────
        $personne->load(['restrictions.tache']);

        // ── Notifier tous les admins planning ─────────────────────────────
        $admins = Personne::adminsPlanning()->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NouveauMembreNotification($personne));
        }

        audit('create', 'inscription', $personne->id, null, [
            'email' => $personne->email,
            'statut' => 'En attente',
        ]);

        return redirect()->route('login')
            ->with('success', 'Votre candidature a bien été enregistrée. Un administrateur va l\'examiner et vous recevrez un email une fois votre compte activé.');
    }
}