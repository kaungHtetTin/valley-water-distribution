<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0B74D1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-base-path" content="{{ parse_url(config('app.url'), PHP_URL_PATH) ?: '/' }}">
        <title>{{ config('app.name') }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body>
        <div id="app"></div>
        <noscript>This application requires JavaScript.</noscript>
    </body>
</html>
