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
 *   admin  → accès complet (peut tout faire)
 *   membre → accès lecture + gestion de ses propres données
 *
 * Un admin a automatiquement accès aux routes réservées aux membres.
 *
 * Usage dans routes/web.php :
 *   Route::middleware('role:admin')   → réservé aux admins
 *   Route::middleware('role:membre')  → admins + membres
 *
 * Usage dans les contrôleurs :
 *   if (Auth::user()->isAdmin()) { ... }
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
            // Route admin : seuls les admins y ont accès
            'admin'  => $personne->isAdmin(),

            // Route membre : admins ET membres y ont accès
            'membre' => $personne->isMembre(),

            // Rôle inconnu : refus par défaut
            default  => false,
        };

        if (! $autorise) {
            // Si connecté mais pas le bon rôle → 403
            // On redirige vers le planning avec un message d'erreur
            // plutôt que d'afficher une page 403 brute
            return redirect()->route('planning.index')
                ->with('error', 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.');
        }

        return $next($request);
    }
}
