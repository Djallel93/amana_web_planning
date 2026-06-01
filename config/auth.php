<?php
// config/auth.php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Guard par défaut : 'web' (session + cookie).
    | Broker de reset mot de passe : 'personnes'.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'personnes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Le guard 'web' utilise le provider 'personnes' qui pointe
    | vers App\Models\Personne au lieu du User Laravel par défaut.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'personnes',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Le provider 'personnes' utilise le driver Eloquent
    | et pointe vers App\Models\Personne.
    |
    | C'est ici que Laravel sait quelle table et quel modèle utiliser
    | pour récupérer l'utilisateur connecté (Auth::user()).
    |
    */

    'providers' => [
        'personnes' => [
            'driver' => 'eloquent',
            'model' => App\Models\Personne::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Configuration du système de reset de mot de passe.
    |
    | table    : table où Laravel stocke les tokens de reset.
    |            On utilise 'password_reset_tokens' (nom standard Laravel).
    |            Cette table sera créée dans la prochaine migration.
    |
    | expire   : durée de validité du lien de reset en minutes (60 = 1 heure).
    |
    | throttle : délai minimum en secondes entre deux demandes de reset
    |            pour éviter le spam (60 = 1 minute).
    |
    */

    'passwords' => [
        'personnes' => [
            'provider' => 'personnes',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Durée en secondes avant qu'une confirmation de mot de passe
    | expire et doive être ressaisie. Par défaut 3 heures.
    |
    */

    'password_timeout' => 10800,

];