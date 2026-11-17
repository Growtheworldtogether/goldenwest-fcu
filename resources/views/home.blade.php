<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Golden West Federal Credit Union | Banking, free to join</title>
    <meta name="description" content="Golden West Federal Credit Union. Joining and opening accounts is free.">
    <meta name="theme-color" content="#1f2869">
    <link rel="preload" href="{{ asset('fonts/roboto.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body id="top">
<a class="skip" href="#main">Skip to content</a>

<div class="idstrip">
    <details class="idnote">
        <summary>
            <span class="idmark">DEMO</span>
            <em>Fictional credit union. Not a real bank and not federally insured.</em>
            <svg viewBox="0 0 12 8" width="12" height="8" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m1 1 5 5 5-5"/></svg>
        </summary>
        <p>Golden West was made up for a software project. No real money is held or moved, and nothing here is a financial offer.</p>
    </details>
</div>

<header class="site-head">
    <div class="head-in">
        <a class="brand" href="{{ url('/') }}" aria-label="Golden West Federal Credit Union, home">
            <svg class="brand-mark" viewBox="0 0 46 46" aria-hidden="true"><circle cx="23" cy="23" r="23" fill="#1f2869"/><circle cx="23" cy="26" r="9" fill="#f5bd02"/><path d="M6 36h34" stroke="#f5bd02" stroke-width="3" stroke-linecap="round"/><path d="M23 7v5M10 13l3.5 3.5M36 13l-3.5 3.5" stroke="#f5bd02" stroke-width="2.6" stroke-linecap="round"/></svg>
            <span class="brand-name">Golden West<small>FEDERAL CREDIT UNION</small></span>
        </a>

        <div class="head-right" id="site-nav">
            <ul class="util">
                <li class="plain">Routing #: 999999990</li>
                <li><a href="{{ url('/learn') }}">Insights &amp; Stories</a></li>
                <li><a href="{{ url('/rates') }}">Rates</a></li>
                <li><a href="{{ url('/community') }}">Community</a></li>
                <li><a href="{{ url('/about') }}">About</a></li>
                <li><a href="{{ url('/help') }}">Help</a></li>
            </ul>
            <ul class="main-nav">
                <li><a href="{{ url('/accounts') }}">Accounts</a></li>
                <li><a href="{{ url('/loans') }}">Loans</a></li>
                <li><a href="{{ url('/digital-banking') }}">Digital banking</a></li>
                <li><a href="{{ url('/join') }}">Join</a></li>
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
        <label for="task">What do you need to do?</label>
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
    {{-- Hero: three slides. Photos load from public/images/hero-N.jpg when present. --}}
    <section class="hero on-dark" aria-roledescription="carousel" aria-label="Featured">
        <div class="slide on" aria-label="1 of 3">
            <div class="photo" data-img="{{ asset('images/hero-1.jpg') }}" data-label="hero-1.jpg"></div>
            <div class="panel"><div class="panel-body">
                <h2 class="caps">Open an account in ten minutes. Pay nothing.</h2>
                <p>No opening deposit and no fee to join. Start on your phone or computer and finish in one sitting.</p>
                <a class="btn" href="{{ url('/join') }}">Join Golden West</a>
            </div></div>
        </div>
        <div class="slide" aria-label="2 of 3">
            <div class="photo" data-img="{{ asset('images/hero-2.jpg') }}" data-label="hero-2.jpg"></div>
            <div class="panel"><div class="panel-body">
                <h2 class="caps">Save for one thing at a time.</h2>
                <p>Name a goal, pick a date, and move money in when you can. A progress bar keeps score so you don't have to.</p>
                <a class="btn" href="{{ url('/accounts#savings') }}">See savings accounts</a>
            </div></div>
        </div>
        <div class="slide" aria-label="3 of 3">
            <div class="photo" data-img="{{ asset('images/hero-3.jpg') }}" data-label="hero-3.jpg"></div>
            <div class="panel"><div class="panel-body">
                <h2 class="caps">See every loan payment before you apply.</h2>
                <p>Choose an amount and a term. We lay out each payment, how much is interest, and the day it is due.</p>
                <a class="btn" href="{{ url('/loans') }}">Try the loan calculator</a>
            </div></div>
        </div>
        <div class="hero-ctrl" role="group" aria-label="Slide controls">
            <button type="button" aria-label="Slide 1" aria-current="true"></button>
            <button type="button" aria-label="Slide 2"></button>
            <button type="button" aria-label="Slide 3"></button>
            <button type="button" class="pause" id="pause" aria-label="Pause slides"><svg viewBox="0 0 12 12" aria-hidden="true"><rect x="1" y="0" width="3.5" height="12"/><rect x="7.5" y="0" width="3.5" height="12"/></svg></button>
        </div>
    </section>

    <section class="rail-section" aria-label="Accounts and services">
        <div class="rail-wrap">
            <button class="arrow prev" type="button" aria-label="Previous" data-prev><svg viewBox="0 0 22 52" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 2 3 26l17 24"/></svg></button>
            <div class="rail" data-rail>
                <a class="pcard" href="{{ url('/accounts#checking') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><rect x="6" y="8" width="52" height="32" rx="2"/><rect x="10" y="12" width="5" height="5" fill="#f5bd02"/><path d="M42 14h12M46 19h8M10 24h20M10 31h14M34 31h20"/><path d="M38 28c2-4 3 3 5 0s3 2 6-1"/></svg>
                    <h3>Checking</h3><p>Everyday money with no minimum balance.</p>
                </a>
                <a class="pcard" href="{{ url('/accounts#savings') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><path d="M8 38v-5M32 38v-5" /><ellipse cx="20" cy="38" rx="12" ry="4.5" fill="#fff"/><path d="M8 33v-5M32 33v-5"/><ellipse cx="20" cy="33" rx="12" ry="4.5" fill="#fff"/><path d="M8 28v-5M32 28v-5"/><ellipse cx="20" cy="23" rx="12" ry="4.5" fill="#f5bd02"/><path d="M36 40v-5M54 40v-5"/><ellipse cx="45" cy="40" rx="9" ry="3.6" fill="#fff"/><path d="M36 35v-4M54 35v-4"/><ellipse cx="45" cy="31" rx="9" ry="3.6" fill="#f5bd02"/></svg>
                    <h3>Savings</h3><p>Goals with a name, a target and a date.</p>
                </a>
                <a class="pcard" href="{{ url('/loans#personal') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><circle cx="32" cy="14" r="11" fill="#f5bd02"/><text x="32" y="19.5" text-anchor="middle" font-size="15" font-weight="700" font-family="Roboto, Arial, sans-serif" fill="#1f2869" stroke="none">$</text><path d="M5 35h10v11H5z"/><path d="M15 37l8-2.500h13c3.500 0 4 3.800.8 4.600L26 42"/><path d="M15 43c5 2.500 9 3.500 14 3.500h9.500l15-8c2.800-1.500 1.400-5-1.500-4.300L45 35"/></svg>
                    <h3>Personal loans</h3><p>The full payment schedule, shown up front.</p>
                </a>
                <a class="pcard" href="{{ url('/loans#auto') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><path d="M5 34v-7c0-2 1-3 3-3.500l6-1.500 5-8c1-1.500 2-2 4-2h20c2 0 3 .5 4 2l5 8 4 1.500c2 .5 3 1.500 3 3.500v7z" fill="#fff"/><path d="M19 22h26"/><path d="M20 22l3.500-6h8v6zM36 22v-6h8l3.500 6z"/><rect x="55" y="27" width="4" height="3" fill="#f5bd02"/><rect x="5" y="27" width="4" height="3" fill="#f5bd02"/><circle cx="17" cy="35" r="5.500" fill="#fff"/><circle cx="17" cy="35" r="1.800" fill="#f5bd02"/><circle cx="47" cy="35" r="5.500" fill="#fff"/><circle cx="47" cy="35" r="1.800" fill="#f5bd02"/></svg>
                    <h3>Auto loans</h3><p>Fixed payments that stay the same.</p>
                </a>
                <a class="pcard" href="{{ url('/digital-banking#card') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><rect x="6" y="8" width="52" height="32" rx="4"/><rect x="12" y="16" width="10" height="8" rx="1.500" fill="#f5bd02"/><path d="M12 32h12M28 32h6"/><path d="M42 18c3 3 3 7 0 10M47 15c5 5 5 11 0 16"/></svg>
                    <h3>Virtual card</h3><p>Ask for one. A person reviews the request.</p>
                </a>
                <a class="pcard" href="{{ url('/digital-banking#bills') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><path d="M16 4h20l10 10v30H16z"/><path d="M36 4v10h10"/><path d="M22 22h18M22 28h12M22 34h8"/><circle cx="46" cy="37" r="8" fill="#f5bd02"/><path d="m42.200 37 2.800 2.800 5-5.600"/></svg>
                    <h3>Bill pay</h3><p>Set it once and stop tracking due dates.</p>
                </a>
            </div>
            <button class="arrow next" type="button" aria-label="Next" data-next><svg viewBox="0 0 22 52" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m2 2 17 24L2 50"/></svg></button>
            <div class="dots" data-dots></div>
        </div>
    </section>

    <section class="band photo-right on-dark" aria-labelledby="how-title">
        <div class="photo" data-img="{{ asset('images/about.jpg') }}" data-label="about.jpg" role="img" aria-label="A member using Golden West on a phone"></div>
        <div class="band-body">
            <h2 class="caps" id="how-title">Banking that keeps up with you.</h2>
            <p>Golden West is owned by its members, so we answer to you. That keeps our rates fair and our explanations plain.</p>
            <p>When we hold, decline or approve a transaction, you get the reason in writing. Disagree? One button opens a ticket and a person replies.</p>
            <a class="btn" href="{{ url('/about') }}">About Golden West</a>
        </div>
    </section>

    <section class="articles" aria-labelledby="learn-title">
        <h2 class="caps" id="learn-title">Money questions, answered</h2>
        <div class="rail-wrap">
            <button class="arrow prev" type="button" aria-label="Previous" data-prev><svg viewBox="0 0 22 52" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 2 3 26l17 24"/></svg></button>
            <div class="rail" data-rail>
                <a class="acard" href="{{ url('/learn/read-your-statement') }}">
                    <div class="photo" data-img="{{ asset('images/learn-1.jpg') }}" data-label="learn-1.jpg"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Your money</span><time datetime="2026-11-12">11.12.2026</time></div>
                    <h3>How to read your monthly statement in five minutes</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/fake-bank-texts') }}">
                    <div class="photo" data-img="{{ asset('images/learn-2.jpg') }}" data-label="learn-2.jpg"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Security</span><time datetime="2026-11-05">11.05.2026</time></div>
                    <h3>That text from "your bank" is probably not from your bank</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/held-transaction') }}">
                    <div class="photo" data-img="{{ asset('images/learn-3.jpg') }}" data-label="learn-3.jpg"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Your account</span><time datetime="2026-10-29">10.29.2026</time></div>
                    <h3>My payment says "held". What now?</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/used-car-loan') }}">
                    <div class="photo" data-img="{{ asset('images/learn-4.jpg') }}" data-label="learn-4.jpg"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Borrowing</span><time datetime="2026-10-22">10.22.2026</time></div>
                    <h3>Buying a used car: auto loan or personal loan?</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/goal-savings') }}">
                    <div class="photo" data-img="{{ asset('images/learn-5.jpg') }}" data-label="learn-5.jpg"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Saving</span><time datetime="2026-10-15">10.15.2026</time></div>
                    <h3>Pick a number, pick a date: how goal savings works</h3></div>
                </a>
            </div>
            <button class="arrow next" type="button" aria-label="Next" data-next><svg viewBox="0 0 22 52" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m2 2 17 24L2 50"/></svg></button>
            <div class="dots" data-dots></div>
        </div>
        <div class="more"><a class="btn" href="{{ url('/learn') }}">Explore more articles</a></div>
    </section>

    <section class="band photo-left on-dark" aria-labelledby="help-title">
        <div class="photo" data-img="{{ asset('images/help.jpg') }}" data-label="help.jpg" role="img" aria-label="A member reading a reply from support"></div>
        <div class="band-body">
            <h2 class="caps" id="help-title">A real person reads every ticket.</h2>
            <p>Open a support ticket from your account and follow the whole conversation in one place. No phone tree, and no starting over when you call back.</p>
            <a class="btn" href="{{ url('/help') }}">Visit the help center</a>
        </div>
    </section>
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
                <p class="lede">Golden West Federal Credit Union offers checking and savings accounts, personal and auto loans, bill pay and more. Joining is free, opening an account is free, and every decision on your money comes with a reason.</p>
                <p class="badges"><span class="badge">Fictional institution</span><span class="badge">Not federally insured</span></p>
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
                    <li><a href="{{ url('/community') }}">Community</a></li>
                    <li><a href="{{ url('/sitemap') }}">Sitemap</a></li>
                </ul>
            </div>
            <div>
                <h3>Contact Us</h3>
                <p class="contact">Customer Care: <a href="tel:8005550147">800-555-0147</a><br>Mon-Fri: 8am - 8pm ET | Sat: 9am - 4pm ET<br>Routing number: 999999990<br>NMLS number: 000000</p>
                <p class="apps"><a class="app" href="#">Get the iPhone app</a><a class="app" href="#">Get the Android app</a></p>
                <p class="contact">&copy; {{ date('Y') }} Golden West Federal Credit Union</p>
            </div>
        </div>
        <p class="sim-note">Golden West Federal Credit Union is a fictional institution created for a software project. No real money is held or moved, and nothing here is a financial offer. Rates, numbers and figures shown are samples.</p>
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

    // Hero slides
    var slides = document.querySelectorAll('.slide'),
        dots = document.querySelectorAll('.hero-ctrl button:not(.pause)'),
        pauseBtn = document.getElementById('pause'),
        i = 0, timer = null,
        reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function show(n) {
        i = (n + slides.length) % slides.length;
        slides.forEach(function (s, k) { s.classList.toggle('on', k === i); });
        dots.forEach(function (d, k) { d.setAttribute('aria-current', k === i ? 'true' : 'false'); });
    }
    function play() { stop(); timer = setInterval(function () { show(i + 1); }, 7000); pauseBtn.setAttribute('aria-label', 'Pause slides'); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    dots.forEach(function (d, k) { d.addEventListener('click', function () { show(k); stop(); pauseBtn.setAttribute('aria-label', 'Play slides'); }); });
    pauseBtn.addEventListener('click', function () {
        if (timer) { stop(); pauseBtn.setAttribute('aria-label', 'Play slides'); } else { play(); }
    });
    if (reduce) { pauseBtn.setAttribute('aria-label', 'Play slides'); } else { play(); }

    // Scroll rows with arrows and dots
    document.querySelectorAll('[data-rail]').forEach(function (rail) {
        var box = rail.parentNode, holder = box.querySelector('[data-dots]');
        function build() {
            var pages = Math.max(1, Math.round(rail.scrollWidth / rail.clientWidth));
            holder.innerHTML = '';
            for (var p = 0; p < pages; p++) {
                (function (p) {
                    var b = document.createElement('button');
                    b.type = 'button'; b.setAttribute('aria-label', 'Show set ' + (p + 1));
                    b.addEventListener('click', function () { rail.scrollTo({ left: p * rail.clientWidth, behavior: 'smooth' }); });
                    holder.appendChild(b);
                })(p);
            }
            mark();
        }
        function mark() {
            var bs = holder.children, p = Math.round(rail.scrollLeft / rail.clientWidth);
            for (var k = 0; k < bs.length; k++) bs[k].setAttribute('aria-current', k === p ? 'true' : 'false');
        }
        box.querySelector('[data-prev]').addEventListener('click', function () { rail.scrollBy({ left: -rail.clientWidth, behavior: 'smooth' }); });
        box.querySelector('[data-next]').addEventListener('click', function () { rail.scrollBy({ left: rail.clientWidth, behavior: 'smooth' }); });
        rail.addEventListener('scroll', function () { window.requestAnimationFrame(mark); });
        window.addEventListener('resize', build);
        build();
    });
})();
</script>
</body>
</html>
