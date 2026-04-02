@php
    $settings = \Illuminate\Support\Facades\Schema::hasTable('settings')
        ? rescue(fn () => app(\App\Settings\GeneralSettings::class), null)
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ $settings?->site_name ?: config('app.name', 'Visadorm P2P') }}</title>
    @if($settings?->favicon_path)
        <link rel="icon" href="{{ asset('storage/' . $settings->favicon_path) }}">
    @else
        <link rel="icon" href="/favicon.ico">
    @endif
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
