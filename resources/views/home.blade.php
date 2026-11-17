<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Golden West Federal Credit Union | Online banking, free to join</title>
    <meta name="description" content="Golden West Federal Credit Union is an online-only credit union. Joining and opening accounts is free.">
    <meta name="theme-color" content="#14224a">
    <link rel="preload" href="{{ asset('fonts/playfair-display.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

<div class="strip">
    <div class="wrap">
        <p>Online only. Free to join. Free to open an account.</p>
        <nav aria-label="Quick links">
            <a href="{{ url('/rates') }}">Rates</a>
            <a href="{{ url('/security') }}">Security</a>
            <a href="{{ url('/help') }}">Help</a>
            <a href="{{ url('/contact') }}">Contact</a>
        </nav>
    </div>
</div>

<header class="site-head">
    <div class="wrap">
        <a class="brand" href="{{ url('/') }}" aria-label="Golden West Federal Credit Union, home">
            <span class="brand-mark" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 26 26" fill="none"><circle cx="13" cy="15" r="6" fill="#d9a520"/><path d="M3 21h20" stroke="#d9a520" stroke-width="2.4" stroke-linecap="round"/><path d="M13 3v3M5.5 7l2 2M20.5 7l-2 2" stroke="#d9a520" stroke-width="2" stroke-linecap="round"/></svg>
            </span>
            <span class="brand-name">Golden West<small>Federal Credit Union</small></span>
        </a>

        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" id="menu-toggle">Menu</button>

        <div class="nav" id="site-nav">
            <ul>
                <li><a href="{{ url('/accounts') }}">Accounts</a></li>
                <li><a href="{{ url('/loans') }}">Loans</a></li>
                <li><a href="{{ url('/rates') }}">Rates</a></li>
                <li><a href="{{ url('/digital-banking') }}">Digital banking</a></li>
                <li><a href="{{ url('/security') }}">Security</a></li>
                <li><a href="{{ url('/help') }}">Help</a></li>
            </ul>
            <div class="actions">
                <a class="login" href="{{ url('/login') }}">Log in</a>
                <a class="btn btn-gold" href="{{ url('/join') }}">Join free</a>
            </div>
        </div>
    </div>
</header>

<div class="bar">
    <div class="wrap">
        <label for="task">What can we help you with today?</label>
        <form action="{{ url('/join') }}" method="get" id="task-form">
            <select id="task" name="task">
                <option value="{{ url('/join') }}">Open an account</option>
                <option value="{{ url('/rates') }}">Check rates</option>
                <option value="{{ url('/loans') }}">Apply for a loan</option>
                <option value="{{ url('/digital-banking') }}">See digital banking</option>
                <option value="{{ url('/help') }}">Get help</option>
            </select>
            <button type="submit">Go</button>
        </form>
    </div>
</div>

<main id="main">
    <section class="hero on-navy" aria-labelledby="hero-title">
        <div class="hero-copy">
            <h1 id="hero-title">Banking that <em>moves with you.</em></h1>
            <p>Join free, open accounts free, and bank from anywhere. Everything happens online.</p>
            <div class="cta">
                <a class="btn btn-gold" href="{{ url('/join') }}">Open an account</a>
                <a class="btn btn-line" href="{{ url('/digital-banking') }}">See how it works</a>
            </div>
        </div>
        <div class="hero-art">
            <div class="panel" role="img" aria-label="Sample account view with a balance and recent transactions">
                <div class="panel-top">
                    <div>
                        <small>Everyday Checking</small>
                        <strong class="num">$4,280.15</strong>
                        <small>Available balance</small>
                    </div>
                    <span class="tag">Active</span>
                </div>
                <ul class="rows">
                    <li><span>Payroll deposit<small>Nov 14</small></span><span class="in num">+$1,850.00</span></li>
                    <li><span>Electric bill<small>Nov 12</small></span><span class="num">-$96.40</span></li>
                    <li><span>Transfer to Savings<small>Nov 10</small></span><span class="num">-$250.00</span></li>
                    <li><span>Grocery store<small>Nov 9</small></span><span class="num">-$84.27</span></li>
                </ul>
                <div class="panel-foot">Sample data for illustration.</div>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="products-title">
        <div class="wrap">
            <div class="section-head">
                <h2 id="products-title">Everything you need, <em>nothing you don't.</em></h2>
                <p>Open an account in minutes. No opening deposit and no cost to join.</p>
            </div>
            <div class="products">
                <a href="{{ url('/accounts#checking') }}"><h3>Checking</h3><p>Everyday spending with a debit card and bill pay.</p></a>
                <a href="{{ url('/accounts#savings') }}"><h3>Savings</h3><p>Set goals and watch them grow with every deposit.</p></a>
                <a href="{{ url('/loans#personal') }}"><h3>Personal loans</h3><p>A clear payment schedule shown before you accept.</p></a>
                <a href="{{ url('/loans#auto') }}"><h3>Auto loans</h3><p>Finance a vehicle with fixed, predictable payments.</p></a>
                <a href="{{ url('/digital-banking#card') }}"><h3>Virtual card</h3><p>Request one when you want it. Approved by our team.</p></a>
                <a href="{{ url('/digital-banking#bills') }}"><h3>Bill pay</h3><p>Pay people and companies on a schedule you choose.</p></a>
            </div>
        </div>
    </section>

    <section class="section rates" aria-labelledby="rates-title">
        <div class="wrap">
            <div class="section-head">
                <h2 id="rates-title">Rates you can <em>read at a glance.</em></h2>
            </div>
            <div class="rate-grid">
                <div class="rate"><b class="num">4.10%</b><span>Savings</span><small>APY</small></div>
                <div class="rate"><b class="num">6.49%</b><span>Personal loan</span><small>APR, from</small></div>
                <div class="rate"><b class="num">5.79%</b><span>Auto loan</span><small>APR, from</small></div>
                <div class="rate"><b class="num">$0</b><span>To join or open</span><small>No opening deposit</small></div>
            </div>
            <p class="fine">Sample rates for this demonstration. They are not offers.</p>
        </div>
    </section>

    <section class="section" aria-labelledby="trust-title">
        <div class="wrap split">
            <div>
                <h2 id="trust-title">Clear about <em>every decision.</em></h2>
            </div>
            <ul class="facts">
                <li><strong>You always get a reason.</strong><span>If a transaction is held or declined, you see why, and you can contest it from the same screen.</span></li>
                <li><strong>You can see what our staff see.</strong><span>When a team member helps with your account, it is shown in your security center.</span></li>
                <li><strong>Support that answers.</strong><span>Open a ticket, follow the replies, and keep the history in one place.</span></li>
            </ul>
        </div>
    </section>

    <section class="join on-navy section" aria-labelledby="join-title">
        <div class="wrap">
            <h2 id="join-title">Ready when <em>you are.</em></h2>
            <p>It takes a few minutes to join, and it costs nothing.</p>
            <a class="btn btn-gold" href="{{ url('/join') }}">Join Golden West</a>
        </div>
    </section>
</main>

<footer class="foot">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <a class="brand" href="{{ url('/') }}">
                    <span class="brand-mark" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 26 26" fill="none"><circle cx="13" cy="15" r="6" fill="#d9a520"/><path d="M3 21h20" stroke="#d9a520" stroke-width="2.4" stroke-linecap="round"/></svg></span>
                    <span class="brand-name">Golden West<small>Federal Credit Union</small></span>
                </a>
                <p>Member-owned banking, entirely online.</p>
            </div>
            <div>
                <h3>Banking</h3>
                <ul>
                    <li><a href="{{ url('/accounts') }}">Accounts</a></li>
                    <li><a href="{{ url('/loans') }}">Loans</a></li>
                    <li><a href="{{ url('/rates') }}">Rates</a></li>
                    <li><a href="{{ url('/digital-banking') }}">Digital banking</a></li>
                </ul>
            </div>
            <div>
                <h3>Support</h3>
                <ul>
                    <li><a href="{{ url('/help') }}">Help center</a></li>
                    <li><a href="{{ url('/security') }}">Security</a></li>
                    <li><a href="{{ url('/contact') }}">Contact us</a></li>
                </ul>
            </div>
            <div>
                <h3>About</h3>
                <ul>
                    <li><a href="{{ url('/about') }}">About us</a></li>
                    <li><a href="{{ url('/privacy') }}">Privacy</a></li>
                    <li><a href="{{ url('/terms') }}">Terms</a></li>
                    <li><a href="{{ url('/accessibility') }}">Accessibility</a></li>
                </ul>
            </div>
        </div>
        <p class="sim-note">Golden West Federal Credit Union is a fictional institution created for a software project. No real money is held or moved, and nothing here is a financial offer.</p>
    </div>
</footer>

<script>
    (function () {
        var t = document.getElementById('menu-toggle'), n = document.getElementById('site-nav');
        t.addEventListener('click', function () {
            var open = n.classList.toggle('open');
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.getElementById('task-form').addEventListener('submit', function (e) {
            e.preventDefault();
            window.location.href = document.getElementById('task').value;
        });
    })();
</script>
</body>
</html>
