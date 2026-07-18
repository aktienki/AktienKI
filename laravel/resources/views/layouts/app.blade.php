<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme="{{ $activeUiTheme ?? auth()->user()?->ui_theme ?? 'purple' }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name', 'aktienKI.com') }}</title>

    @vite([
    'resources/css/app.css',
    'resources/js/app.js',
    ])

    @livewireStyles
</head>
<body class="ak-body">
<div class="ak-app-shell">
    @include('partials.topbar')

    <main class="ak-container" style="padding-top:32px;padding-bottom:72px;">
        {{ $slot ?? '' }}
        @yield('content')
    </main>
</div>

@livewireScripts
</body>
</html>
