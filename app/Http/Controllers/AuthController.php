<?php
// app/Http/Controllers/AuthController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Contrôleur d'authentification.
 * Gère la connexion et la déconnexion de l'administrateur unique.
 *
 * Pas de gestion multi-utilisateurs : un seul compte admin
 * défini dans le fichier .env (ADMIN_EMAIL / ADMIN_PASSWORD).
 */
class AuthController extends Controller
{
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
        // Validation des champs
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

        // Tentative d'authentification via Auth guard (table users)
        if (Auth::attempt($credentials, $remember)) {
            // Régénérer la session pour éviter les attaques de fixation de session
            $request->session()->regenerate();

            // Journaliser la connexion
            audit('login', 'auth');

            session()->flash('success', 'Connexion réussie. Bienvenue !');
            return redirect()->intended(route('planning.index'));
        }

        // Échec : retourner avec erreur (sans révéler lequel des champs est incorrect)
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Email ou mot de passe incorrect.']);
    }

    /**
     * Déconnexion de l'administrateur.
     */
    public function logout(Request $request): RedirectResponse
    {
        audit('logout', 'auth');

        Auth::logout();

        // Invalider la session et régénérer le token CSRF
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Vous avez été déconnecté.');
    }
}
