<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Online Banking') | {{ brand('name') }}</title>
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="{{ brand('brand_color') }}">
    <link rel="preload" href="{{ asset('fonts/SourceSans3.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Oswald.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <style>{!! \App\Services\Brand::cssVars() !!}</style>
    <link rel=\"icon\" href=\"{{ route('brand.favicon') }}\">
    <script>document.documentElement.classList.add('js')</script>
</head>
@php
    $user = auth()->user();
    $first = $user->first_name ?? $user->name;
    $initials = strtoupper(mb_substr($user->first_name ?? $user->name, 0, 1).mb_substr($user->last_name ?? '', 0, 1));
    $icons = [
        'accounts' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10.5h18M7 15h3"/>',
        'activity' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'transfer' => '<path d="M6 8h13l-3.500-3.500M18 16H5l3.500 3.500"/>',
        'deposit' => '<path d="M12 4v11M7.500 10.500L12 15l4.500-4.500M5 20h14"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.5-1.500 1.800-2 3-2s2.500.5 3 2M14 10h4M14 13h3"/>',
        'open' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'security' => '<path d="M12 3l8 3v6c0 4.800-3.400 7.800-8 9-4.600-1.200-8-4.200-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-3.800 3.600-6 8-6s8 2.200 8 6"/>',
        'logout' => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>',
        'dash' => '<rect x="3" y="3" width="8" height="8" rx="1.500"/><rect x="13" y="3" width="8" height="5" rx="1.500"/><rect x="13" y="11" width="8" height="10" rx="1.500"/><rect x="3" y="14" width="8" height="7" rx="1.500"/>',
        'queue' => '<path d="M5 4h14v16H5z"/><path d="M9 9h6M9 13h6"/>',
        'members' => '<circle cx="9" cy="8" r="3.500"/><path d="M2 20c0-3.500 3-5.500 7-5.500s7 2 7 5.500M16 5a3.500 3.500 0 0 1 0 7M22 20c0-2.500-1.500-4.200-4-5"/>',
        'inbox' => '<path d="M3 13l3-8h12l3 8v6H3z"/><path d="M3 13h5l1 3h6l1-3h5"/>',
        'audit' => '<path d="M6 3h9l4 4v14H6z"/><path d="M9 12h7M9 16h7"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.600 5.600l2.100 2.100M16.300 16.300l2.100 2.100M5.600 18.400l2.100-2.100M16.300 7.700l2.100-2.100"/>',
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1.500 0 2-1 1.500-2-.5-1.200.2-2.500 1.700-2.500H17a4 4 0 0 0 4-4C21 6.500 17 3 12 3z"/><circle cx="7.500" cy="11" r="1"/><circle cx="10" cy="7.500" r="1"/><circle cx="14.500" cy="7.500" r="1"/>',
        'pages' => '<rect x="4" y="3" width="16" height="18" rx="1.500"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'phone' => '<path d="M5 4h4l2 5-2.500 1.500a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
    ];
    $staffMode = request()->routeIs('admin.*');
    $staffNav = [
        ['admin.home', 'Overview', 'dash', ['admin.home'], null],
        ['admin.queue.index', 'Review queue', 'queue', ['admin.queue.*'], 'staff.viewQueue'],
        ['admin.members.index', 'Members', 'members', ['admin.members.*'], 'staff.lookupMembers'],
        ['admin.kyc.index', 'Identity review', 'id', ['admin.kyc.*'], 'staff.reviewKyc'],
        ['admin.inquiries.index', 'Inquiries', 'inbox', ['admin.inquiries.*'], 'staff.answerInquiries'],
        ['admin.audit', 'Audit log', 'audit', ['admin.audit'], 'staff.viewAudit'],
        ['admin.branding', 'Branding', 'palette', ['admin.branding*'], 'staff.editSite'],
        ['admin.site.index', 'Site content', 'pages', ['admin.site.*'], 'staff.editSite'],
        ['admin.settings', 'Settings', 'settings', ['admin.settings*'], 'staff.manageAdmin'],
        ['admin.security', 'My security', 'security', ['admin.security'], null],
    ];
    $nav = $staffMode ? collect($staffNav)->filter(fn ($n) => ! $n[4] || \Illuminate\Support\Facades\Gate::allows($n[4]))->map(fn ($n) => array_slice($n, 0, 4))->all() : [
        ['dashboard', 'Accounts', 'accounts', ['dashboard', 'accounts.show', 'accounts.export', 'transactions.*']],
        ['activity', 'Activity', 'activity', ['activity']],
        ['transfers.create', 'Transfer', 'transfer', ['transfers.create', 'transfers.store']],
        ['deposits.index', 'Deposit', 'deposit', ['deposits.*', 'payees.*', 'kyc.*', 'transfers.external*']],
        ['accounts.create', 'Open an account', 'open', ['accounts.create']],
        ['security.settings', 'Security', 'security', ['security.settings']],
        ['profile', 'Profile', 'profile', ['profile*']],
    ];
@endphp
<body class="portal-body">
<a class="skip" href="#main">Skip to content</a>
<div class="shell">
    <aside class="side" id="side" aria-label="Online banking">
        <div class="side-head">
            <a class="pbrand" href="{{ route($staffMode ? 'admin.home' : 'dashboard') }}" aria-label="{{ brand('name') }}, online banking home">
                @include('partials.brand-logo', ['variant' => 'dark', 'caption' => $staffMode ? 'Staff console' : 'Online banking'])
            </a>
            <button class="side-close" type="button" data-side-close aria-label="Close menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <nav class="side-nav">
            @foreach ($nav as [$route, $label, $icon, $patterns])
                <a href="{{ route($route) }}" @if (request()->routeIs(...$patterns)) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$icon] !!}</svg>{{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="side-foot">
            <a class="side-help" href="tel:{{ brand('tel') }}"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['phone'] !!}</svg><span>Customer Care<b>{{ brand('support_phone') }}</b></span></a>
            <div class="side-user">
                <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                <span class="who">{{ $user->name }}<small>{{ $staffMode ? $user->adminTitle() : 'Member #'.$user->member_number }}</small></span>
            </div>
            <form method="post" action="{{ route('logout') }}">@csrf
                <button class="side-out" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons['logout'] !!}</svg>Log out</button>
            </form>
        </div>
    </aside>
    <div class="scrim" data-side-close hidden></div>

    <div class="content">
        <div class="stick">
        @if (session('impersonation'))
            @php($imp = session('impersonation'))
            <div class="acting" role="status">
                <span><strong>Acting as {{ $imp['member_name'] }}</strong> (staff: {{ $imp['admin_name'] }}). Ends at {{ \Illuminate\Support\Carbon::createFromTimestamp($imp['expires_at'])->format('g:i a') }}.</span>
                <form method="post" action="{{ route('impersonate.stop') }}">@csrf<button type="submit">Exit</button></form>
            </div>
        @endif
        <header class="ptopbar">
            <button class="menu-toggle" type="button" id="side-open" aria-controls="side" aria-expanded="false" aria-label="Open menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <span class="ptitle"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>{{ $staffMode ? 'Staff console' : 'Secure online banking' }}</span>
            <span class="tb-user">@if ($lastSignIn ?? null)<span class="tb-last">Last sign-in {{ $lastSignIn->format('M j, g:i a') }}</span>@endif<span class="tb-name">{{ $first }}</span><span class="avatar" aria-hidden="true">{{ $initials }}</span></span>
        </header>
        </div>

        <main id="main" class="pmain">
            @hasSection('band')<div class="pband{{ $staffMode ? ' staff' : '' }}"><div class="pwrap">@yield('band')</div></div>@endif
            <div class="pwrap">
                @yield('content')
            </div>
        </main>

        <footer class="pfoot">
            <div class="pwrap">
                <p>Customer Care <a href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a> &middot; {{ brand('support_hours') }} &middot; Routing number 121478906</p>
                <p><a href="{{ url('/privacy') }}">Privacy</a> &middot; <a href="{{ url('/terms') }}">Terms</a> &middot; <a href="{{ url('/disclosures') }}">Disclosures</a> &middot; <a href="{{ url('/security') }}">Security &amp; Fraud Center</a></p>
                <p class="fine">{{ brand('short_name') }} will never ask for your password or a one-time code by phone, text or email.</p>
            </div>
        </footer>
    </div>
</div>
<script>
(function () {
    var side = document.getElementById('side'), open = document.getElementById('side-open'), scrim = document.querySelector('.scrim');
    function set(on) {
        document.body.classList.toggle('side-open', on);
        open.setAttribute('aria-expanded', on ? 'true' : 'false');
        scrim.hidden = !on;
        if (on) { side.querySelector('a, button').focus(); } else { open.focus(); }
    }
    open.addEventListener('click', function () { set(true); });
    document.querySelectorAll('[data-side-close]').forEach(function (el) { el.addEventListener('click', function () { set(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.body.classList.contains('side-open')) set(false); });
    window.matchMedia('(min-width: 961px)').addEventListener('change', function (m) { if (m.matches) { document.body.classList.remove('side-open'); scrim.hidden = true; } });
})();
</script>
@stack('scripts')
@include('partials.toasts')
</body>
</html>
