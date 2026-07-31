<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="authenticated-user" content="{{ auth()->id() }}">
    @endauth
    <title>{{ config('app.name', 'AktienKI') }}</title>
    <x-preference-head />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="font-sans antialiased">
    <div class="ak-background"></div>
    <div class="ak-page-shell">
        <x-app-topbar />

        <main class="ak-container py-2">
            @yield('content')
        </main>
    </div>

    @livewireScripts
</body>
</html>
