<?php
// app/Http/Middleware/EnsureAuthenticated.php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de base : vérifie que l'utilisateur est connecté.
 *
 * Si non connecté → redirige vers /login avec un message flash.
 *
 * Ce middleware ne vérifie PAS les rôles — il délègue ça à EnsureRole.
 * Il est appliqué sur toutes les routes protégées via l'alias 'auth'
 * enregistré dans bootstrap/app.php.
 *
 * Utilisé seul pour les routes accessibles à tout utilisateur connecté
 * (admin ou membre), comme la page planning ou l'export PDF.
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Vous devez être connecté pour accéder à cette page.');
        }

        return $next($request);
    }
}
