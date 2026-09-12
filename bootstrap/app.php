<?php
// bootstrap/app.php
// Point d'entrée de l'application Laravel 11

declare(strict_types=1);

use Amana\Shared\Http\Middleware\EnsureAuthenticated;
use Amana\Shared\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ── Middlewares d'authentification (amana/shared) ──────────────────
        //
        // 'auth'      : vérifie que l'utilisateur est connecté.
        //               Redirige vers /login si ce n'est pas le cas.
        //
        // 'role'      : vérifie qu'un utilisateur connecté possède le rôle
        //               requis dans l'application courante (voir
        //               config('amana-shared.app_code') = 'planning').
        //               Usage dans routes/web.php :
        //                 Route::middleware('role:admin')
        //                 Route::middleware('role:membre')
        //               Note : 'admin' a automatiquement accès aux routes 'membre'.
        //
        // 'verified'  : vérifie que l'email de l'utilisateur a été confirmé.
        //               Utilisé après l'inscription pour forcer la vérification.
        //
        $middleware->alias([
            'auth' => EnsureAuthenticated::class,
            'role' => EnsureRole::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);

        // ── Inertia (spike route-par-route — voir resources/js/Pages/) ──────
        //
        // Partage les props communes (utilisateur connecté, cf.
        // HandleInertiaRequests::share()) à toute page Inertia. N'affecte
        // aucune route Blade classique : ce middleware ne fait qu'ajouter
        // des en-têtes/props Inertia à la requête, il ne change rien pour
        // les réponses `view(...)` habituelles.
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Pas de configuration particulière des exceptions pour l'instant
    })->create();