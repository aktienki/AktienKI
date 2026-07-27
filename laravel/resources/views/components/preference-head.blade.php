@props(['forceDark' => false])

    <script>
        (() => {
            const themeKey = 'aktienki-theme';
            const versionKey = 'aktienki-theme-version';
            const lightVersion = 'light-default-v1';
            const forceDark = @js((bool) $forceDark);

            if (! forceDark && localStorage.getItem(versionKey) !== lightVersion) {
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
