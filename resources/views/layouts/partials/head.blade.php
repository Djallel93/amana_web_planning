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

    {{-- @vite accepte un tableau : il génère un <link> pour le CSS
         et un <script type="module"> pour le JS. --}}
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    @stack('styles')
</head>
