<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Make.com Webhook
    |--------------------------------------------------------------------------
    */

    'make' => [
        'webhook_url' => env('MAKE_WEBHOOK_URL'),
        'webhook_url_evenements' => env('MAKE_WEBHOOK_URL_EVENEMENTS'),
        'api_key' => env('MAKE_WEBHOOK_APIKEY'),
    ],

];