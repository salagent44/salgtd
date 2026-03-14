<!DOCTYPE html>
<html lang="en"
  class="{{ ($page['props']['theme'] ?? 'default') !== 'default' ? $page['props']['theme'] : '' }}"
  style="--note-font: {{ $page['props']['note_font_css'] ?? '-apple-system, BlinkMacSystemFont, sans-serif' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover" />
        <!-- PWA -->
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#5b5294" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="GTD" />
        <link rel="apple-touch-icon" href="/icon-192.png" />
        <title>GTD</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js');
            }
        </script>
    </body>
</html>
