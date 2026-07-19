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
        ],
    ],

];