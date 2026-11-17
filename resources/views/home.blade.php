<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Golden West Federal Credit Union | Online banking, free to join</title>
    <meta name="description" content="Golden West Federal Credit Union is an online-only credit union. Joining and opening accounts is free.">
    <meta name="theme-color" content="#1f2869">
    <link rel="preload" href="{{ asset('fonts/roboto.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

<div class="topnote">
    <div class="wrap"><b>DEMO</b> Golden West is a made-up credit union for a software project. No real money here.</div>
</div>

<header class="site-head">
    <div class="wrap">
        <a class="brand" href="{{ url('/') }}" aria-label="Golden West Federal Credit Union, home">
            <svg class="brand-mark" viewBox="0 0 46 46" aria-hidden="true"><circle cx="23" cy="23" r="23" fill="#1f2869"/><circle cx="23" cy="26" r="9" fill="#f5bd02"/><path d="M6 36h34" stroke="#f5bd02" stroke-width="3" stroke-linecap="round"/><path d="M23 7v5M10 13l3.5 3.5M36 13l-3.5 3.5" stroke="#f5bd02" stroke-width="2.6" stroke-linecap="round"/></svg>
            <span class="brand-name">Golden West<small>FEDERAL CREDIT UNION</small></span>
        </a>

        <div class="head-right" id="site-nav">
            <ul class="util">
                <li><a href="{{ url('/rates') }}">Rates</a></li>
                <li><a href="{{ url('/learn') }}">Learn</a></li>
                <li><a href="{{ url('/security') }}">Security</a></li>
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>SEARCH
            </a>
            <a class="tool" href="{{ url('/login') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>LOG IN
            </a>
            <button class="tool menu-btn" type="button" id="menu-btn" aria-expanded="false" aria-controls="site-nav">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>MENU
            </button>
        </div>
    </div>
</header>

<div class="taskbar">
    <div class="wrap">
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
    {{-- Hero: three slides, own copy. Photos load from public/images/hero-N.jpg if present. --}}
    <section class="hero" aria-roledescription="carousel" aria-label="Featured">
        <div class="slide on" aria-label="1 of 3">
            <div class="photo" data-img="{{ asset('images/hero-1.jpg') }}"></div>
            <div class="slide-panel"><div class="slide-body">
                <h2 class="caps">Open an account in ten minutes. Pay nothing.</h2>
                <p>No opening deposit and no fee to join. You can finish from your couch, and there is no lobby to wait in because we never built one.</p>
                <a class="btn" href="{{ url('/join') }}">Join Golden West</a>
            </div></div>
        </div>
        <div class="slide" aria-label="2 of 3">
            <div class="photo" data-img="{{ asset('images/hero-2.jpg') }}"></div>
            <div class="slide-panel"><div class="slide-body">
                <h2 class="caps">Save for one thing at a time.</h2>
                <p>Name a goal, pick a date, and move money in when you can. A progress bar keeps score so you don't have to.</p>
                <a class="btn" href="{{ url('/accounts#savings') }}">See savings accounts</a>
            </div></div>
        </div>
        <div class="slide" aria-label="3 of 3">
            <div class="photo" data-img="{{ asset('images/hero-3.jpg') }}"></div>
            <div class="slide-panel"><div class="slide-body">
                <h2 class="caps">See every loan payment before you apply.</h2>
                <p>Choose an amount and a term. We lay out each payment, how much is interest, and the date it is due.</p>
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

    <section class="products" aria-label="Accounts and services">
        <div class="wrap">
            <div class="rail" data-rail>
                <a class="pcard" href="{{ url('/accounts#checking') }}">
                    <svg viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="34" height="24" rx="3"/><path d="M5 18h34M11 27h8"/></svg>
                    <h3>Checking</h3><p>Everyday money with no minimum balance.</p>
                </a>
                <a class="pcard" href="{{ url('/accounts#savings') }}">
                    <svg viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 34V20M18 34V12M28 34V24M38 34V8"/><path d="M5 38h34"/></svg>
                    <h3>Savings</h3><p>Goals with a name, a target and a date.</p>
                </a>
                <a class="pcard" href="{{ url('/loans#personal') }}">
                    <svg viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="22" cy="22" r="16"/><path d="M26 15.5c-1-1.4-2.6-2-4.2-2-2.4 0-4.2 1.2-4.2 3.2 0 4.8 8.8 2.4 8.8 7 0 2-1.9 3.3-4.4 3.3-1.8 0-3.5-.8-4.5-2.2M22 10v3M22 31v3"/></svg>
                    <h3>Personal loans</h3><p>The full payment schedule, shown up front.</p>
                </a>
                <a class="pcard" href="{{ url('/loans#auto') }}">
                    <svg viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 27v-6l4-9h24l4 9v6z"/><path d="M6 21h32"/><circle cx="13" cy="30" r="3.2"/><circle cx="31" cy="30" r="3.2"/></svg>
                    <h3>Auto loans</h3><p>Fixed payments that stay the same.</p>
                </a>
                <a class="pcard" href="{{ url('/digital-banking#card') }}">
                    <svg viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="9" width="34" height="26" rx="4"/><circle cx="16" cy="22" r="5"/><circle cx="24" cy="22" r="5"/></svg>
                    <h3>Virtual card</h3><p>Ask for one. A person reviews the request.</p>
                </a>
                <a class="pcard" href="{{ url('/digital-banking#bills') }}">
                    <svg viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="6" width="30" height="32" rx="3"/><path d="M14 15h16M14 22h16M14 29h9"/></svg>
                    <h3>Bill pay</h3><p>Set it once and stop tracking due dates.</p>
                </a>
            </div>
            <div class="dots" data-dots></div>
        </div>
    </section>

    <section class="band" aria-labelledby="how-title">
        <div class="grad l"><div class="inner">
            <h2 class="caps" id="how-title">No branch to find. No lobby to visit.</h2>
            <p>Golden West lives online, and our members own it. That means we answer to you, and we can keep rates fair without paying for a building on every corner.</p>
            <p>If we hold, decline or approve a transaction, you get the reason in writing. Disagree? One button opens a ticket, and a person replies.</p>
            <a class="btn" href="{{ url('/about') }}">About Golden West</a>
        </div></div>
        <div class="photo" data-img="{{ asset('images/about.jpg') }}" role="img" aria-label="A member using Golden West on a phone"></div>
    </section>

    <section class="articles" aria-labelledby="learn-title">
        <div class="wrap">
            <h2 class="caps" id="learn-title">Money questions, answered</h2>
            <div class="rail" data-rail>
                <a class="acard" href="{{ url('/learn/read-your-statement') }}">
                    <div class="photo" data-img="{{ asset('images/learn-1.jpg') }}"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Your money</span><time datetime="2026-11-12">11.12.2026</time></div>
                    <h3>How to read your monthly statement in five minutes</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/fake-bank-texts') }}">
                    <div class="photo" data-img="{{ asset('images/learn-2.jpg') }}"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Security</span><time datetime="2026-11-05">11.05.2026</time></div>
                    <h3>That text from "your bank" is probably not from your bank</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/held-transaction') }}">
                    <div class="photo" data-img="{{ asset('images/learn-3.jpg') }}"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Your account</span><time datetime="2026-10-29">10.29.2026</time></div>
                    <h3>My payment says "held". What now?</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/used-car-loan') }}">
                    <div class="photo" data-img="{{ asset('images/learn-4.jpg') }}"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Borrowing</span><time datetime="2026-10-22">10.22.2026</time></div>
                    <h3>Buying a used car: auto loan or personal loan?</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/goal-savings') }}">
                    <div class="photo" data-img="{{ asset('images/learn-5.jpg') }}"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Saving</span><time datetime="2026-10-15">10.15.2026</time></div>
                    <h3>Pick a number, pick a date: how goal savings works</h3></div>
                </a>
            </div>
            <div class="dots" data-dots></div>
            <p class="more"><a href="{{ url('/learn') }}">Browse all articles</a></p>
        </div>
    </section>

    <section class="band" aria-labelledby="help-title">
        <div class="photo" data-img="{{ asset('images/help.jpg') }}" role="img" aria-label="A member reading a reply from support"></div>
        <div class="grad r"><div class="inner">
            <h2 class="caps" id="help-title">A real person reads every ticket.</h2>
            <p>Open a support ticket from your account and follow the whole conversation in one place. No phone tree, no starting over when you call back.</p>
            <a class="btn" href="{{ url('/help') }}">Visit the help center</a>
        </div></div>
    </section>
</main>

<footer>
    <div class="linkbar dark">
        <div class="wrap">
            <ul>
                <li><a href="{{ url('/contact') }}">Contact us</a></li>
                <li><a href="{{ url('/security#report') }}">Report fraud</a></li>
                <li><a href="{{ url('/help') }}">Open a ticket</a></li>
            </ul>
        </div>
    </div>
    <div class="foot">
        <div class="wrap">
            <div class="foot-grid">
                <div>
                    <a class="brand" href="{{ url('/') }}">
                        <svg class="brand-mark" viewBox="0 0 46 46" aria-hidden="true"><circle cx="23" cy="23" r="23" fill="#1f2869"/><circle cx="23" cy="26" r="9" fill="#f5bd02"/><path d="M6 36h34" stroke="#f5bd02" stroke-width="3" stroke-linecap="round"/></svg>
                        <span class="brand-name">Golden West<small>FEDERAL CREDIT UNION</small></span>
                    </a>
                    <p>Golden West is an online-only credit union. Joining is free, opening accounts is free, and every decision on your money comes with a reason.</p>
                </div>
                <div>
                    <h3>Quick links</h3>
                    <ul>
                        <li><a href="{{ url('/rates') }}">Rates</a></li>
                        <li><a href="{{ url('/privacy') }}">Privacy</a></li>
                        <li><a href="{{ url('/terms') }}">Terms</a></li>
                        <li><a href="{{ url('/disclosures') }}">Disclosures</a></li>
                        <li><a href="{{ url('/accessibility') }}">Accessibility</a></li>
                        <li><a href="{{ url('/security') }}">Security center</a></li>
                        <li><a href="{{ url('/sitemap') }}">Sitemap</a></li>
                    </ul>
                </div>
                <div>
                    <h3>Contact us</h3>
                    <p>Members: open a ticket from your account and we reply in the order received.</p>
                    <p>Not a member yet? Use the <a href="{{ url('/contact') }}" style="color:var(--blue)">contact form</a>.</p>
                    <p>&copy; {{ date('Y') }} Golden West Federal Credit Union</p>
                </div>
            </div>
            <p class="sim-note">Golden West Federal Credit Union is a fictional institution created for a software project. No real money is held or moved, and nothing here is a financial offer. Rates and figures shown are samples.</p>
        </div>
    </div>
</footer>

<script>
(function () {
    // Mobile menu
    var mb = document.getElementById('menu-btn'), nav = document.getElementById('site-nav');
    mb.addEventListener('click', function () {
        var o = nav.classList.toggle('open');
        mb.setAttribute('aria-expanded', o ? 'true' : 'false');
    });

    // Task bar jumps straight to the chosen page
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

    // Scroll rows with dots
    document.querySelectorAll('[data-rail]').forEach(function (rail) {
        var holder = rail.parentNode.querySelector('[data-dots]');
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
        rail.addEventListener('scroll', function () { window.requestAnimationFrame(mark); });
        window.addEventListener('resize', build);
        build();
    });
})();
</script>
</body>
</html>
