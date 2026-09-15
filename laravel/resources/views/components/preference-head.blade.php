@props(['forceDark' => false])

@php
    // The user's saved preference (profile settings) is the source of truth
    // on a fresh page load - authoritative across devices/browsers, unlike
    // localStorage which is per-browser only. A user who set dark mode on
    // their phone previously still saw light mode on a new laptop, because
    // nothing here ever read the saved value back. Sync it into localStorage
    // before anything reads it, so the rest of this script and
    // preferences.js's later logic (which only knows about localStorage)
    // both pick it up without any further changes.
    $serverTheme = auth()->check() ? data_get(auth()->user()->preferences, 'theme') : null;
    $serverTheme = in_array($serverTheme, ['light', 'dark'], true) ? $serverTheme : null;
@endphp
    <script>
        (() => {
            const themeKey = 'aktienki-theme';
            const versionKey = 'aktienki-theme-version';
            const lightVersion = 'light-default-v1';
            const forceDark = @js((bool) $forceDark);
            const serverTheme = @js($serverTheme);

            if (serverTheme) {
                localStorage.setItem(themeKey, serverTheme);
                localStorage.setItem(versionKey, lightVersion);
            } else if (! forceDark && localStorage.getItem(versionKey) !== lightVersion) {
                localStorage.setItem(themeKey, 'light');
                localStorage.setItem(versionKey, lightVersion);
            }

            const saved = localStorage.getItem(themeKey) || 'light';
            const dark = forceDark || saved === 'dark' || (saved === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.themeLocked = forceDark ? 'dark' : '';
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    })();
</script>

<x-global-light-theme />
