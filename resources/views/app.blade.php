<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Valley Water Distribution</title>
        <script>
            window.ValleyRuntime = {
                routes: {
                    office: @json(url('/office')),
                    client: @json(url('/client')),
                    sales: @json(url('/sales')),
                    driver: @json(url('/driver')),
                },
                auth: {
                    user: @json(url('/api/auth/user')),
                    login: @json(url('/api/auth/login')),
                    logout: @json(url('/api/auth/logout')),
                },
            };
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="root"></div>
    </body>
</html>
