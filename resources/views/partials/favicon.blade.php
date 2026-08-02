{{-- resources/views/partials/favicon.blade.php --}}
{{--
    Partiel favicon partagé — inclus par layouts/partials/head.blade.php
    (app principale), partials/head.blade.php (auth/login/reset-password)
    et echanges/token-result.blade.php.

    Package généré par realfavicongenerator.net à partir de
    public/images/amana-logo.png, extrait directement dans public/. Les
    chemins ci-dessous n'ont PAS le préfixe /public/ fourni par le
    générateur : dans Laravel, public/ EST déjà la racine web servie
    (public_html sur IONOS pointe dessus), donc /public/favicon.ico
    donnerait un 404 — les fichiers sont servis à la racine.
    asset() respecte APP_URL si l'app n'est pas montée à la racine du domaine.
--}}
<link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-title" content="AMANA">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
