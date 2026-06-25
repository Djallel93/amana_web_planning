<?php
// app/Http/Controllers/EmergencyController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Outil d'urgence post-déploiement : génère un hash bcrypt à copier dans phpMyAdmin.
 *
 * IMPORTANT : cette route est entièrement désactivée si APP_EMERGENCY_KEY
 * n'est pas défini dans le .env. Elle ne modifie RIEN en base de données —
 * elle affiche uniquement le hash bcrypt que l'admin doit coller manuellement
 * dans phpMyAdmin sur la colonne `password` de `ref_personnes`.
 *
 * Désactiver après usage : retirer APP_EMERGENCY_KEY du .env puis
 * exécuter `php artisan config:cache`.
 *
 * Route : GET|POST /urgence-hash?key=APP_EMERGENCY_KEY
 */
class EmergencyController extends Controller
{
    /**
     * Vérifie que la clé d'urgence est configurée et valide.
     * Retourne false (et abort 404) si la fonctionnalité est désactivée.
     */
    private function verifierCle(string|null $key): bool
    {
        $configuredKey = config('app.emergency_key');

        // Désactivé si la clé n'est pas définie dans .env
        if (empty($configuredKey)) {
            abort(404);
        }

        return hash_equals($configuredKey, (string) $key);
    }

    /**
     * Affiche le formulaire de génération de hash.
     */
    public function show(Request $request): View
    {
        $key = $request->query('key', '');
        $this->verifierCle($key);

        return view('emergency.hash', [
            'key' => $key,
            'hash' => null,
        ]);
    }

    /**
     * Génère et affiche le hash bcrypt — ne touche pas la base de données.
     */
    public function generate(Request $request): View
    {
        $key = $request->input('key', '');
        $this->verifierCle($key);

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
        ]);

        $hash = Hash::make($request->input('password'));

        return view('emergency.hash', [
            'key' => $key,
            'hash' => $hash,
        ]);
    }
}