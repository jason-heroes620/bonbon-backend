<html>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="{{ asset('BonBon-Logo-Red.ico') }}" type="image/x-icon">
        <title inertia>{{ config('app.name', 'BonBon') }}</title>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx'])
        @inertiaHead
        <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places"
        async
        defer
    ></script>
    </head>
    <body>
        @inertia
    </body>
</html>