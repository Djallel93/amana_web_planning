{{-- resources/views/partials/head.blade.php --}}
{{--
    Head partagé (alias de layouts/partials/head.blade.php).
    CSS compilé via Vite + Tailwind.
--}}

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AMANA Planning')</title>

    @include('partials.favicon')

    @vite(['resources/css/app.css'])

    @stack('styles')
</head>
