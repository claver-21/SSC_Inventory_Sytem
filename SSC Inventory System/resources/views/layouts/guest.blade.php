<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PCB-SSC Inventory Management System')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/pcb-ssc-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-surface-bg text-charcoal antialiased">
    @yield('content')
</body>
</html>
