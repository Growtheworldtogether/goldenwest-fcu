<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Golden West Federal Credit Union | Banking built around you')</title>
    <meta name="description" content="@yield('description', 'Golden West Federal Credit Union has served members across the West since 1958. Joining and opening an account is free.')">
    <meta name="theme-color" content="#1f2869">
    <link rel="preload" href="{{ asset('fonts/Barlow-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body id="top">
<a class="skip" href="#main">Skip to content</a>

<div class="idstrip">
    <details class="idnote">
        <summary>
            <svg class="idicon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v5c0 4.500 3 8 7 10 4-2 7-5.500 7-10V6z"/><path d="m9 12 2.200 2.200L15 10"/></svg>
            <em>Security notice: we will never ask for your password or a one-time code by phone, text or email.</em>
            <svg viewBox="0 0 12 8" width="12" height="8" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m1 1 5 5 5-5"/></svg>
        </summary>
        <p><strong>Protect your account.</strong> Golden West will never call, text or email you to ask for your online banking password, PIN, card number, or the one-time code we send to verify a sign-in. Fraudsters often fake caller ID and sound urgent, so do not trust a call just because it appears to come from us.<br><br>If someone contacts you claiming to be from Golden West and asks for any of these, do not share anything. Hang up and call Customer Care at <a href="tel:8005550147">800-555-0147</a>, or visit your nearest branch. If you already shared information, call us right away so we can secure your accounts. You can also report suspicious messages in the <a href="{{ url('/security') }}">Security &amp; Fraud Center</a>.</p>
    </details>
</div>

<header class="site-head">
    <div class="head-in">
        <a class="brand" href="{{ url('/') }}" aria-label="Golden West Federal Credit Union, home">
            <svg class="brand-mark" viewBox="0 0 46 46" aria-hidden="true"><circle cx="23" cy="23" r="23" fill="#1f2869"/><circle cx="23" cy="26" r="9" fill="#f5bd02"/><path d="M6 36h34" stroke="#f5bd02" stroke-width="3" stroke-linecap="round"/><path d="M23 7v5M10 13l3.500 3.500M36 13l-3.500 3.500" stroke="#f5bd02" stroke-width="2.600" stroke-linecap="round"/></svg>
            <span class="brand-name">Golden West<small>FEDERAL CREDIT UNION</small></span>
        </a>

        <div class="head-right" id="site-nav">
            <ul class="util">
                <li class="plain">Routing #: 121478906</li>
                <li><a href="{{ url('/learn') }}">Insights &amp; Stories</a></li>
                <li><a href="{{ url('/rates') }}">Rates</a></li>
                <li><a href="{{ url('/community') }}">Community</a></li>
                <li><a href="{{ url('/about') }}">About</a></li>
                <li><a href="{{ url('/help') }}">Help</a></li>
            </ul>
            <ul class="main-nav">
                <li><a href="{{ url('/accounts') }}">Personal</a></li>
                <li><a href="{{ url('/business') }}">Business</a></li>
                <li><a href="{{ url('/loans') }}">Loans</a></li>
                <li><a href="{{ url('/digital-banking') }}">Digital banking</a></li>
            </ul>
        </div>

        <div class="head-tools">
            <a class="tool hide-sm" href="{{ url('/search') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6"/><path d="M15 15l6 6"/></svg>SEARCH
            </a>
            <a class="tool" href="{{ url('/login') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zm-3 8V7a3 3 0 1 1 6 0v3z"/></svg>LOGIN
            </a>
            <button class="tool menu-btn" type="button" id="menu-btn" aria-expanded="false" aria-controls="site-nav">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>MENU
            </button>
        </div>
    </div>
</header>

<div class="taskbar on-dark">
    <div class="taskbar-in">
        <label for="task">What can we help you with today?</label>
        <form action="{{ url('/join') }}" method="get" id="task-form">
            <select id="task" name="task">
                <option value="{{ url('/join') }}">Open an account</option>
                <option value="{{ url('/rates') }}">See today's rates</option>
                <option value="{{ url('/loans') }}">Start a loan application</option>
                <option value="{{ url('/help#contest') }}">Contest a decision on my account</option>
                <option value="{{ url('/help#password') }}">Reset my password</option>
            </select>
            <button type="submit">Go</button>
        </form>
    </div>
</div>

<main id="main">
    @yield('content')
</main>

<footer class="site-foot">
    <div class="linkbar on-dark">
        <div class="linkbar-in">
            <div class="social" aria-label="Social media">
                <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 8V6.500c0-.7.200-1.100 1.200-1.100H17V2.200C16.600 2.100 15.600 2 14.500 2 12 2 10.300 3.500 10.300 6.200V8H7.500v3.500h2.800V22H14V11.500h2.700L17.200 8z"/></svg></a>
                <a href="#" aria-label="LinkedIn"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4.500 9H8v11H4.500zM6.200 3.500a2 2 0 1 1 0 4 2 2 0 0 1 0-4zM10 9h3.300v1.500c.5-.9 1.700-1.800 3.400-1.800 3.500 0 4.100 2.300 4.100 5.300V20h-3.500v-5.200c0-1.300 0-2.900-1.800-2.900s-2 1.400-2 2.800V20H10z"/></svg></a>
                <a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21.600 7.200a2.600 2.600 0 0 0-1.800-1.800C18.200 5 12 5 12 5s-6.200 0-7.800.4A2.600 2.600 0 0 0 2.400 7.200C2 8.800 2 12 2 12s0 3.200.4 4.800a2.600 2.600 0 0 0 1.800 1.800C5.800 19 12 19 12 19s6.200 0 7.800-.4a2.600 2.600 0 0 0 1.800-1.800c.4-1.600.4-4.800.4-4.800s0-3.200-.4-4.800zM10 15V9l5.200 3z"/></svg></a>
                <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3.500" y="3.500" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r=".8" fill="currentColor"/></svg></a>
            </div>
            <ul>
                <li><a href="{{ url('/contact') }}">Contact Us</a></li>
                <li><a href="{{ url('/careers') }}">Careers</a></li>
                <li><a href="{{ url('/appointments') }}">Schedule an Appointment</a></li>
            </ul>
        </div>
    </div>
    <div class="foot">
        <div class="foot-grid">
            <div>
                <a class="brand" href="{{ url('/') }}">
                    <svg class="brand-mark" viewBox="0 0 46 46" aria-hidden="true"><circle cx="23" cy="23" r="23" fill="#1f2869"/><circle cx="23" cy="26" r="9" fill="#f5bd02"/><path d="M6 36h34" stroke="#f5bd02" stroke-width="3" stroke-linecap="round"/><path d="M23 7v5M10 13l3.500 3.500M36 13l-3.500 3.500" stroke="#f5bd02" stroke-width="2.600" stroke-linecap="round"/></svg>
                    <span class="brand-name">Golden West<small>FEDERAL CREDIT UNION</small></span>
                </a>
                <p class="lede">Golden West Federal Credit Union has served California and the West since 1958. With 46 branches across five states, personal and business banking, and secure digital tools, we help more than 280,000 members save, borrow and plan with confidence.</p>
                <p class="badges"><span class="badge">Secure online banking</span><span class="badge">24/7 fraud monitoring</span></p>
            </div>
            <div>
                <h3>Quick Links</h3>
                <ul class="qlinks">
                    <li><a href="{{ url('/rates') }}">Rates</a></li>
                    <li><a href="{{ url('/privacy') }}">Privacy</a></li>
                    <li><a href="{{ url('/terms') }}">Terms</a></li>
                    <li><a href="{{ url('/disclosures') }}">Disclosures</a></li>
                    <li><a href="{{ url('/accessibility') }}">Accessibility</a></li>
                    <li><a href="{{ url('/security') }}">Security &amp; Fraud Center</a></li>
                    <li><a href="{{ url('/community') }}">Community Reinvestment</a></li>
                    <li><a href="{{ url('/sitemap') }}">Sitemap</a></li>
                </ul>
            </div>
            <div>
                <h3>Contact Us</h3>
                <p class="contact">Customer Care: <a href="tel:8005550147">800-555-0147</a><br>Mon-Fri: 7am - 8pm PT | Sat: 8am - 5pm PT<br>Routing number: 121478906<br>NMLS number: 482915</p>
                <p class="apps"><a class="app" href="#"><img src="{{ asset('images/app-store-badge.png') }}" alt="Download on the App Store" width="130" height="39"></a><a class="app" href="#"><img src="{{ asset('images/google-play-badge.png') }}" alt="Get it on Google Play" width="131" height="39"></a></p>
                <p class="contact">&copy; {{ date('Y') }} Golden West Federal Credit Union</p>
            </div>
        </div>
        <p class="legal">Golden West Federal Credit Union is an independent portfolio project and is not a licensed financial institution. No deposits are accepted and no accounts are insured.</p>
        <a class="totop" href="#top" aria-label="Back to top"><svg viewBox="0 0 20 12" width="20" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 10 8-8 8 8"/></svg></a>
    </div>
</footer>

<script>
(function () {
    var mb = document.getElementById('menu-btn'), nav = document.getElementById('site-nav');
    mb.addEventListener('click', function () {
        var o = nav.classList.toggle('open');
        mb.setAttribute('aria-expanded', o ? 'true' : 'false');
    });

    document.getElementById('task-form').addEventListener('submit', function (e) {
        e.preventDefault();
        window.location.href = document.getElementById('task').value;
    });

    // Photo slots: use the real image only if the file exists
    document.querySelectorAll('.photo[data-img]').forEach(function (el) {
        var img = new Image();
        img.onload = function () { el.style.backgroundImage = 'url("' + el.dataset.img + '")'; el.classList.add('has-img'); };
        img.src = el.dataset.img;
    });
})();
</script>
@stack('scripts')
</body>
</html>
