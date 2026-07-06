{{-- resources/views/layouts/partials/head.blade.php --}}
{{--
    Head du layout principal (layouts/app.blade.php).
    CSS compilé via Vite + Tailwind — public/build/assets/app-[hash].css
    JS Vue compilé via Vite          — public/build/assets/app-[hash].js
--}}

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AMANA Planning')</title>

    {{--
        Applique le thème (clair/sombre) avant le premier rendu, pour éviter
        un flash de thème clair au chargement si l'utilisateur a choisi le
        mode sombre. Doit être un <script> inline synchrone placé avant le
        CSS Vite — un module différé arriverait trop tard, après la première
        peinture. La logique de bascule (bouton, persistance) vit dans
        resources/js/lib/theme.ts, chargé plus tard via @vite.
    --}}
    <script>
        (function () {
            var stored = localStorage.getItem('amana-theme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    {{-- @vite accepte un tableau : il génère un <link> pour le CSS
         et un <script type="module"> pour le JS. --}}
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    @stack('styles')
</head>
