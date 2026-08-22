<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Obtenha, leia e organize transcrições de vídeos do YouTube em uma interface clara e estruturada.">
        <meta name="color-scheme" content="light dark">
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

        <script>
            (() => {
                const storageKey = 'transcriptions-theme';
                const root = document.documentElement;
                let preference = 'system';

                try {
                    const storedPreference = localStorage.getItem(storageKey);

                    if (['light', 'dark', 'system'].includes(storedPreference)) {
                        preference = storedPreference;
                    }
                } catch {
                    // Storage can be unavailable in privacy-restricted contexts.
                }

                const systemIsDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = preference === 'dark' || (preference === 'system' && systemIsDark);

                root.classList.toggle('dark', isDark);
                root.dataset.theme = preference;
                root.style.colorScheme = isDark ? 'dark' : 'light';
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
