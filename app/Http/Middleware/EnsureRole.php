<?php
// app/Http/Middleware/EnsureRole.php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de contrôle des rôles.
 *
 * Vérifie que l'utilisateur connecté possède le rôle requis
 * dans l'application 'planning'.
 *
 * Toutes les routes utilisant ce middleware sont déjà protégées par le
 * middleware 'auth' (EnsureAuthenticated) — Auth::check() est donc
 * garanti ici et n'est pas revérifié.
 *
 * Hiérarchie des rôles :
 *   admin        → accès complet (peut tout faire)
 *   gestionnaire → accès planning + événements + absences + restrictions,
 *                  mais pas la gestion des utilisateurs
 *   membre       → accès lecture + gestion de ses propres données
 *
 * Un admin a automatiquement accès aux routes réservées aux gestionnaires et membres.
 * Un gestionnaire a automatiquement accès aux routes réservées aux membres.
 *
 * Usage dans routes/web.php :
 *   Route::middleware('role:admin')        → réservé aux admins uniquement
 *   Route::middleware('role:gestionnaire') → admins + gestionnaires
 *   Route::middleware('role:membre')       → admins + gestionnaires + membres
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var \App\Models\Personne $personne */
        $personne = Auth::user();

        $autorise = match ($role) {
            'admin' => $personne->isAdmin(),
            'gestionnaire' => $personne->isAdmin() || $personne->isGestionnaire(),
            'membre' => $personne->isMembre(),
            default => false,
        };

        if (!$autorise) {
            return redirect()->route('planning.index')
                ->with('error', 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.');
        }

        return $next($request);
    }
}