{{-- resources/views/layouts/partials/head.blade.php --}}
{{--
    Head du layout principal (layouts/app.blade.php).
    CSS compilé via Vite + Tailwind — public/build/assets/app-[hash].css
--}}

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AMANA Planning')</title>

    @vite(['resources/css/app.css'])

    @stack('styles')
</head>
