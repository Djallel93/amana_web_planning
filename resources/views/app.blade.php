{{-- resources/views/app.blade.php --}}
{{--
Vue racine Inertia — chargée uniquement lors d'une visite complète (URL
tapée, rechargement, lien externe) d'une page migrée vers Inertia. Les
navigations Inertia suivantes (via <Link>) ne rechargent pas ce fichier :
seul le contenu de la région @inertia est remplacé côté client.

Ne PAS confondre avec resources/views/layouts/app.blade.php (qui reste
strictement inchangé, utilisé par les ~23 autres pages Blade classiques).

Reprend le même shell que layouts/app.blade.php — tête, sidebar, points de
montage des îlots Vue partagés (@amana/shared-ui) — en incluant directement
les partials de amana/shared plutôt qu'en les portant dans un composant Vue
(voir la note d'architecture du ticket : dupliquer ce shell impacterait
TOUTES les apps AMANA, largement hors du périmètre d'un spike une page).

Limitation connue et acceptée pour ce spike : le lien actif de la sidebar
(amana-shared::layouts.partials.sidebar) est calculé côté serveur via
request()->routeIs(...). Comme la sidebar est en dehors de la région
re-rendue par Inertia, ce surlignage ne sera à jour qu'après un rechargement
complet, pas après une navigation Inertia cliente depuis/vers cette page.
Avec une seule page Inertia dans toute l'app, ce cas n'est pas atteignable
en pratique pour l'instant — corriger proprement (rendre la sidebar
« Inertia-aware » via usePage()) est laissé à une itération future, une
fois plusieurs pages migrées.
--}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Favicons / icônes PWA — identiques à amana-shared::layouts.partials.head --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="apple-mobile-web-app-title"
        content="{{ config('amana-shared.branding.tagline_short', config('amana-shared.branding.app_name')) }}">

    {{-- Applique le thème avant le premier rendu — voir amana-shared::layouts.partials.head --}}
    <script>
        (function () {
            var stored = localStorage.getItem('amana-theme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    {{--
    @inertiaHead : toujours pris en charge en v3 (pas de fallback SSR, mais
    cette app ne fait pas de SSR — voir docs Inertia, "Server-Side Setup").
    Remplace @yield('title', ...) : le titre de la page migrée est défini
    via le composant <Head title="..."> (@inertiajs/vue3) dans
    Guide/Index.vue plutôt que via @section('title', ...).
    --}}
    @inertiaHead
</head>

<body class="bg-surface-2 font-body text-ink antialiased flex min-h-screen">

    @include('amana-shared::layouts.partials.sidebar')

    <div id="mainWrapper"
        class="flex-1 flex flex-col min-w-0 ml-sidebar transition-all duration-300 max-sm:ml-0 max-sm:pt-topbar">
        <main class="flex-1 p-8 max-w-screen-xl w-full mx-auto max-lg:p-7 max-sm:px-4 max-sm:py-5">

            @include('amana-shared::layouts.partials.flash')

            {{--
            id personnalisé : doit correspondre exactement à l'id passé à
            createInertiaApp({ id: "inertia-app", ... }) dans
            resources/js/app.ts — jamais "app" (déjà pris par la
            convention Inertia par défaut, mais surtout : ce projet n'a
            aucun mount-point #app existant à réutiliser, on en choisit un
            explicitement nommé pour éviter toute confusion avec les
            points de montage #vue-* des îlots).
            --}}
            @inertia('inertia-app')

        </main>
    </div>

    <div id="vue-mobile-sidebar"></div>

    <div id="vue-toast"></div>
    <div id="vue-confirm-dialog"></div>
    <div id="vue-offline-banner"></div>
    <div id="vue-urgent-alert-bar"></div>
    <div id="vue-notification-bell"></div>

</body>

</html>
