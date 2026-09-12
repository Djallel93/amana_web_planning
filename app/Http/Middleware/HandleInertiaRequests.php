<?php
// app/Http/Middleware/HandleInertiaRequests.php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

/**
 * Middleware Inertia — généré via `php artisan inertia:middleware` puis
 * adapté aux conventions de cette app.
 *
 * Partage l'utilisateur connecté à toutes les pages Inertia, sous la même
 * forme que celle déjà utilisée côté Blade (`$user->isAdmin()`,
 * `$user->isGestionnaire()` — voir RestrictionsController, GuideController) :
 * les pages Vue lisent `usePage().props.auth.user` plutôt que de recevoir
 * `user` en prop dédiée à chaque contrôleur, pour éviter de le dupliquer
 * (voir Task 2, GuideController::index()).
 *
 * Uniquement des booléens exposés pour isAdmin()/isGestionnaire()/isMembre()
 * (pas les méthodes elles-mêmes, inutilisables telles quelles côté JS).
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Vue racine chargée lors de la toute première visite (non-Inertia).
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Détermine la version des assets actuelle, utilisée pour forcer un
     * rechargement complet côté client après un déploiement.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props partagées avec toutes les pages Inertia.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = Auth::user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'email' => $user->email,
                    'isAdmin' => $user->isAdmin(),
                    'isGestionnaire' => $user->isGestionnaire(),
                    'isMembre' => $user->isMembre(),
                ] : null,
            ],
        ];
    }
}
