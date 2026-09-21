<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php($meta = $meta ?? null)

        @if ($meta)
            <title inertia>{{ $meta['title'] }} - {{ config('app.name') }}</title>
            <meta name="description" content="{{ $meta['description'] }}">
            <link rel="canonical" href="{{ $meta['canonical'] }}">

            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ config('app.name') }}">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta property="og:url" content="{{ $meta['canonical'] }}">
            <meta property="og:image" content="{{ $meta['image'] }}">

            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $meta['title'] }}">
            <meta name="twitter:description" content="{{ $meta['description'] }}">
            <meta name="twitter:image" content="{{ $meta['image'] }}">

            <script type="application/ld+json">@json($meta['jsonLd'])</script>
        @else
            <title inertia>{{ config('app.name', 'Laravel') }}</title>
            <meta name="robots" content="noindex, nofollow">
        @endif

        <!-- Scripts -->
        @routes
        <script>
            Ziggy.url = @js(config('app.url'));
            Ziggy.port = null;
        </script>

        @if (config('services.recaptcha.site_key'))
            <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>
        @endif

        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
