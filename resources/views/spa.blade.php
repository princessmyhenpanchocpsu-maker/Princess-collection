<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Princess Collections — Your Style, Your Crown. Women's clothing boutique.">

        <title>{{ config('app.name', 'Princess Collections') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600|playfair-display:500,600,700&display=swap" rel="stylesheet" />

        @viteReactRefresh
        @vite('resources/js/app.jsx')
    </head>
    <body class="bg-cream font-sans text-ink antialiased">
        <div id="root"></div>
    </body>
</html>
