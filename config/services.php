<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Calendar (Service Account)
    |--------------------------------------------------------------------------
    |
    | Synchronisation directe avec l'API Google Calendar v3 via un compte de
    | service (pas de flux OAuth consentement — outil interne mono-organisation).
    | Chaque calendrier
    | Google Calendar utilisé (AMANA - Planning, AMANA - Communications,
    | AMANA - Événements…) doit être partagé individuellement avec l'email du
    | compte de service, avec droit de modification.
    |
    | GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 : le contenu intégral du fichier JSON
    | de clé du compte de service, encodé en base64 (même logique que les
    | autres secrets du projet : injecté via .env / secrets GitHub Actions,
    | jamais committé). Décodé au runtime par GoogleCalendarService.
    |
    */

    'google' => [
        'calendar' => [
            'service_account_json_base64' => env('GOOGLE_SERVICE_ACCOUNT_JSON_BASE64'),
            // Calendriers pré-enregistrés au déploiement — voir
            // database/seeders/CalendriersGoogleSeeder.php. Lu via config()
            // plutôt que env() directement dans le seeder : un cache de
            // config laissé par un déploiement précédent (php artisan
            // optimize / config:cache) rendrait un env() direct silencieux
            // (toujours null), alors que config() reste fiable car ce
            // fichier est justement ce qui est mis en cache.
            'preseed' => [
                ['id' => env('GOOGLE_CALENDAR_ID_1'), 'nom' => env('GOOGLE_CALENDAR_NOM_1', 'AMANA - Planning')],
                ['id' => env('GOOGLE_CALENDAR_ID_2'), 'nom' => env('GOOGLE_CALENDAR_NOM_2', 'AMANA - Communications')],
            ],
        ],
    ],

];