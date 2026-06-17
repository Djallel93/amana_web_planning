{{-- resources/views/layouts/partials/head.blade.php --}}

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AMANA Planning')</title>

    {{-- ── Normalize.css ── --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    {{-- ── Styles partagés + layout principal ── --}}
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @stack('styles')
</head>