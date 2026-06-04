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
 *
 * Usage dans les contrôleurs :
 *   if (Auth::user()->isAdmin()) { ... }
 *   if (Auth::user()->isGestionnaire()) { ... }
 *   if (Auth::user()->isMembre()) { ... }
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Vérifier que l'utilisateur est connecté
        if (! Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Vous devez être connecté pour accéder à cette page.');
        }

        /** @var \App\Models\Personne $personne */
        $personne = Auth::user();

        // Vérifier le rôle requis avec hiérarchie
        $autorise = match ($role) {
            // Route admin uniquement
            'admin' => $personne->isAdmin(),

            // Route gestionnaire : admins ET gestionnaires y ont accès
            'gestionnaire' => $personne->isAdmin() || $personne->isGestionnaire(),

            // Route membre : admins, gestionnaires ET membres y ont accès
            'membre' => $personne->isMembre(),

            // Rôle inconnu : refus par défaut
            default => false,
        };

        if (! $autorise) {
            return redirect()->route('planning.index')
                ->with('error', 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.');
        }

        return $next($request);
    }
}
