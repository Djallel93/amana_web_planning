<?php
// config/amana-shared.php
//
// Publié depuis amana/shared (php artisan vendor:publish --tag=amana-shared-config)
// puis adapté aux besoins de amana_web_planning.

declare(strict_types=1);

return [

    'app_code' => env('AMANA_APP_CODE', 'planning'),

    'connection' => env('AMANA_COMMUN_CONNECTION', 'commun'),

    'home_route' => env('AMANA_HOME_ROUTE', 'planning.index'),

    'email_theme' => [
        'header_bg' => '#0c1e2e',
        'accent' => '#0369a1',
        'accent_dark' => '#0284c7',
        'accent_light' => '#0ea5e9',
        'accent_rgb' => '3, 105, 161',
        'accent_light_rgb' => '14, 165, 233',
        'accent_light_text' => '#7dd3fc',
        'accent_pale_text' => '#bae6fd',
        'accent_darker' => '#0c4a6e',
        'hadith_french_text' => '#1e4a6e',
        'accent_pale_bg' => '#f0f6fb',
        'accent_pale_border' => '#c7dff0',
    ],

    'branding' => [
        'app_name' => 'AMANA Planning',
        'tagline' => 'Planification des permanences et rotation équitable des tâches',
        'tagline_short' => 'Planning',
        'email_footer_text' => "Vous recevez cet email suite à une action d'un administrateur sur AMANA Familles.",
        'features' => [
            ['🔄', 'Rotation automatique équitable des tâches'],
            ['📊', "Statistiques et score d'équité"],
            ['📄', 'Export PDF du planning'],
            ['↩️', 'Rollback et gestion des absences'],
        ],
        'signup_route_name' => 'inscription',
        'signup_label' => 'Soumettre une candidature',
    ],

    'audit' => [
        'modules' => [
            'settings',
            'inscription',
            'personnes',
            'planning',
            'echanges',
            'absences',
            'restrictions',
            'evenements',
            'bilan',
            'auth',
        ],
        'actions' => ['create', 'update', 'delete', 'generate', 'login', 'logout', 'webhook'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation de la sidebar
    |--------------------------------------------------------------------------
    |
    | LIMITATION CONNUE : contrairement à l'ancienne sidebar codée en dur,
    | ce tableau ne porte pas de compteurs dynamiques (badges "3 échanges en
    | attente", "2 candidatures"...) — un config/*.php ne peut pas contenir
    | de closures sans casser `php artisan config:cache`. Les items
    | 'Mes échanges', 'Échanges' (admin) et 'Candidatures' ont donc perdu
    | leur badge nav-badge par rapport à l'ancienne sidebar. Deux options
    | pour les récupérer :
    |   1. Publier la vue (php artisan vendor:publish --tag=amana-shared-views)
    |      et ajouter les @php $nb = ...::count(); @endphp + badge à la main
    |      dans resources/views/vendor/amana-shared/layouts/partials/sidebar.blade.php
    |      (l'app reprend alors la main sur CE fichier uniquement).
    |   2. Ne rien faire — c'est un compromis accepté de la centralisation.
    | Décision à prendre par AMANA, pas encore tranchée à ce stade.
    */
    'nav' => [
        ['section' => 'Planning'],
        ['route' => 'planning.index', 'label' => 'Planning', 'icon' => '📅'],
        ['route' => 'mon-planning', 'label' => 'Mon planning', 'icon' => '🙋'],
        ['route' => 'echanges.index', 'label' => 'Mes échanges', 'icon' => '🔄'],
        ['route' => 'planning.statistics', 'label' => 'Statistiques', 'icon' => '📊'],
        ['route' => 'planning.export.form', 'label' => 'Export PDF', 'icon' => '📄', 'route_pattern' => 'planning.export*'],

        ['section' => 'Mes données'],
        ['route' => 'absences.index', 'label' => 'Absences', 'icon' => '🏖️', 'route_pattern' => 'absences.*'],
        ['route' => 'restrictions.index', 'label' => 'Disponibilités', 'icon' => '🔒', 'route_pattern' => 'restrictions.*'],

        ['section' => 'Bilan'],
        ['route' => 'bilan.index', 'label' => 'Saisie', 'icon' => '🧾'],
        ['route' => 'bilan.statistiques', 'label' => 'Statistiques', 'icon' => '📊'],

        ['section' => 'Gestion'],
        ['route' => 'planning.generate.form', 'label' => 'Générer', 'icon' => '✨', 'role' => 'gestionnaire', 'route_pattern' => 'planning.generate*'],
        ['route' => 'evenements.index', 'label' => 'Événements', 'icon' => '🎉', 'role' => 'gestionnaire', 'route_pattern' => 'evenements.*'],
        ['route' => 'admin.echanges.index', 'label' => 'Échanges', 'icon' => '🔄', 'role' => 'gestionnaire', 'route_pattern' => 'admin.echanges.*'],
        ['route' => 'settings.index', 'label' => 'Paramètres', 'icon' => '⚙️', 'role' => 'gestionnaire', 'route_pattern' => 'settings.*'],

        ['section' => 'Administration'],
        ['route' => 'personnes.index', 'label' => 'Personnes', 'icon' => '👥', 'role' => 'admin', 'route_pattern' => 'personnes.*'],
        ['route' => 'admin.candidatures.index', 'label' => 'Candidatures', 'icon' => '📥', 'role' => 'admin', 'route_pattern' => 'admin.candidatures*'],
        ['route' => 'diagnostic.mail.index', 'label' => 'Diagnostic SMTP', 'icon' => '🔧', 'role' => 'admin', 'route_pattern' => 'diagnostic.mail.*'],
        ['route' => 'admin.activite.index', 'label' => "Statistiques d'activité", 'icon' => '📈', 'role' => 'admin', 'route_pattern' => 'admin.activite.*'],
        ['route' => 'admin.journal.index', 'label' => "Journal d'audit", 'icon' => '📜', 'role' => 'admin', 'route_pattern' => 'admin.journal.*'],

        ['section' => 'Aide'],
        ['route' => 'guide.index', 'label' => "Guide d'utilisation", 'icon' => '❓', 'route_pattern' => 'guide.*'],
    ],
];
