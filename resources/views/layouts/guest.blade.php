<!doctype html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'GSM MIX') }} - @yield('title','Auth')</title>

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-light">
    <div class="container py-5">
        @yield('content')
    </div>
</body>
</html>
