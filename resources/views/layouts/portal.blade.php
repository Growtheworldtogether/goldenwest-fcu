<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Online Banking') | Golden West Federal Credit Union</title>
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#1f2869">
    <link rel="preload" href="{{ asset('fonts/SourceSans3.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Oswald.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body class="portal-body">
<a class="skip" href="#main">Skip to content</a>
<header class="ptop on-dark">
    <div class="ptop-in">
        <a class="pbrand" href="{{ route('dashboard') }}" aria-label="Golden West Federal Credit Union, online banking home">
            @include('partials.brand-mark')
            <span>Golden West</span>
        </a>
        <nav class="pnav" aria-label="Online banking">
            @php($nav = [
                ['dashboard', 'Accounts', ['dashboard', 'accounts.*', 'transactions.*']],
                ['activity', 'Activity', ['activity']],
                ['transfers.create', 'Transfer', ['transfers.*']],
                ['security.settings', 'Security', ['security.settings']],
                ['profile', 'Profile', ['profile*']],
            ])
            @foreach ($nav as [$route, $label, $patterns])
                <a href="{{ route($route) }}" @if (request()->routeIs(...$patterns)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="puser">
            <span class="pname">{{ auth()->user()->first_name ?? auth()->user()->name }}</span>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
        </div>
    </div>
</header>

<main id="main" class="pmain">
    <div class="pwrap">
        @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="flash bad" role="alert">{{ session('error') }}</div>@endif
        @yield('content')
    </div>
</main>

<footer class="pfoot">
    <div class="pwrap">
        <p>Customer Care <a href="tel:8005550147">800-555-0147</a> &middot; Mon-Fri 7am - 8pm PT, Sat 8am - 5pm PT &middot; Routing number 121478906</p>
        <p><a href="{{ url('/privacy') }}">Privacy</a> &middot; <a href="{{ url('/terms') }}">Terms</a> &middot; <a href="{{ url('/disclosures') }}">Disclosures</a> &middot; <a href="{{ url('/security') }}">Security &amp; Fraud Center</a></p>
        <p class="fine">Golden West will never ask for your password or a one-time code by phone, text or email.</p>
    </div>
</footer>
@stack('scripts')
</body>
</html>
