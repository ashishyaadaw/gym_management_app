<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#121212">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>

    <link rel="manifest" href="{{ route('manifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">

    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body>
    @include('partials.icons')
    @yield('body')

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script>
        window.App = {{ Illuminate\Support\Js::from([
            'baseUrl' => url('/'),
            'user' => auth()->check() ? ['id' => auth()->id(), 'name' => auth()->user()->name] : null,
            'role' => auth()->user()?->role,
            'brand' => config('app.name'),
            'currency' => config('gym.currency'),
            'countryCode' => config('gym.country_code'),
            'expiringDays' => config('gym.expiring_days'),
            'inactiveDays' => config('gym.inactive_days'),
        ]) }};
    </script>
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    @stack('scripts')
</body>
</html>
