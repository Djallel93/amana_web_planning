<?php
// app/Http/Middleware/EnsureAuthenticated.php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware : vérifie que l'utilisateur est connecté.
 * Si non connecté → redirige vers /login avec un message flash.
 *
 * Appliqué sur toutes les routes sauf /login.
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
