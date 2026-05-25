<?php
// bootstrap/app.php
// Point d'entrée de l'application Laravel 11

declare(strict_types=1);

use App\Http\Middleware\EnsureAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Enregistrer le middleware d'authentification personnalisé
        // sous l'alias 'auth' pour pouvoir écrire Route::middleware('auth')
        $middleware->alias([
            'auth' => EnsureAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Pas de configuration particulière des exceptions pour l'instant
    })->create();
