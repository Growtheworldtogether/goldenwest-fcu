@extends('layouts.site')

@section('content')
    {{-- Hero: three slides. Photos load from public/images/hero-N.jpg when present. --}}
    <section class="hero on-dark" aria-roledescription="carousel" aria-label="Featured">
        <div class="slide on" aria-label="1 of 3">
            <div class="photo" data-img="{{ asset('images/hero-1.jpg') }}"></div>
            <div class="panel"><div class="panel-body">
                <h2 class="caps">Banking built around your life.</h2>
                <p>Open a checking or savings account in about ten minutes, with no opening deposit and no fee to join.</p>
                <a class="btn" href="{{ url('/join') }}">Open an account</a>
            </div></div>
        </div>
        <div class="slide" aria-label="2 of 3">
            <div class="photo" data-img="{{ asset('images/hero-2.jpg') }}"></div>
            <div class="panel"><div class="panel-body">
                <h2 class="caps">Save toward what matters most.</h2>
                <p>Set a goal with a target and a date, and watch every deposit move you closer to it.</p>
                <a class="btn" href="{{ url('/accounts#savings') }}">Explore savings</a>
            </div></div>
        </div>
        <div class="slide" aria-label="3 of 3">
            <div class="photo" data-img="{{ asset('images/hero-3.jpg') }}"></div>
            <div class="panel"><div class="panel-body">
                <h2 class="caps">Borrow with the full picture.</h2>
                <p>See your rate, every monthly payment and the total cost of your loan before you apply.</p>
                <a class="btn" href="{{ url('/loans') }}">Explore loans</a>
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
                    <h3>Checking</h3><p>Everyday banking with no minimum balance.</p>
                </a>
                <a class="pcard" href="{{ url('/accounts#savings') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><path d="M8 38v-5M32 38v-5" /><ellipse cx="20" cy="38" rx="12" ry="4.5" fill="#fff"/><path d="M8 33v-5M32 33v-5"/><ellipse cx="20" cy="33" rx="12" ry="4.5" fill="#fff"/><path d="M8 28v-5M32 28v-5"/><ellipse cx="20" cy="23" rx="12" ry="4.5" fill="#f5bd02"/><path d="M36 40v-5M54 40v-5"/><ellipse cx="45" cy="40" rx="9" ry="3.6" fill="#fff"/><path d="M36 35v-4M54 35v-4"/><ellipse cx="45" cy="31" rx="9" ry="3.6" fill="#f5bd02"/></svg>
                    <h3>Savings</h3><p>Grow your money with goals and competitive yields.</p>
                </a>
                <a class="pcard" href="{{ url('/loans#personal') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><circle cx="32" cy="14" r="11" fill="#f5bd02"/><text x="32" y="19.5" text-anchor="middle" font-size="15" font-weight="700" font-family="Barlow, Arial, sans-serif" fill="#1f2869" stroke="none">$</text><path d="M5 35h10v11H5z"/><path d="M15 37l8-2.500h13c3.500 0 4 3.800.8 4.600L26 42"/><path d="M15 43c5 2.500 9 3.500 14 3.500h9.500l15-8c2.800-1.500 1.400-5-1.500-4.300L45 35"/></svg>
                    <h3>Personal loans</h3><p>Clear terms and fixed payments you can plan around.</p>
                </a>
                <a class="pcard" href="{{ url('/loans#auto') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><path d="M5 34v-7c0-2 1-3 3-3.500l6-1.500 5-8c1-1.500 2-2 4-2h20c2 0 3 .5 4 2l5 8 4 1.500c2 .5 3 1.500 3 3.500v7z" fill="#fff"/><path d="M19 22h26"/><path d="M20 22l3.500-6h8v6zM36 22v-6h8l3.500 6z"/><rect x="55" y="27" width="4" height="3" fill="#f5bd02"/><rect x="5" y="27" width="4" height="3" fill="#f5bd02"/><circle cx="17" cy="35" r="5.500" fill="#fff"/><circle cx="17" cy="35" r="1.800" fill="#f5bd02"/><circle cx="47" cy="35" r="5.500" fill="#fff"/><circle cx="47" cy="35" r="1.800" fill="#f5bd02"/></svg>
                    <h3>Auto loans</h3><p>Competitive rates on new and used vehicles.</p>
                </a>
                <a class="pcard" href="{{ url('/business') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><rect x="8" y="16" width="48" height="28" rx="3" fill="#fff"/><path d="M24 16v-4a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v4"/><path d="M8 29h48"/><rect x="28" y="25.500" width="8" height="7" rx="1" fill="#f5bd02"/></svg>
                    <h3>Business banking</h3><p>Checking, lending and cash management for your company.</p>
                </a>
                <a class="pcard" href="{{ url('/digital-banking') }}">
                    <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><rect x="6" y="8" width="52" height="32" rx="4"/><rect x="12" y="16" width="10" height="8" rx="1.500" fill="#f5bd02"/><path d="M12 32h12M28 32h6"/><path d="M42 18c3 3 3 7 0 10M47 15c5 5 5 11 0 16"/></svg>
                    <h3>Cards &amp; bill pay</h3><p>Manage cards, schedule payments and get instant alerts.</p>
                </a>
            </div>
            <button class="arrow next" type="button" aria-label="Next" data-next><svg viewBox="0 0 22 52" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m2 2 17 24L2 50"/></svg></button>
            <div class="dots" data-dots></div>
        </div>
    </section>

    <section class="band photo-right on-dark" aria-labelledby="how-title">
        <div class="photo" data-img="{{ asset('images/about.jpg') }}" role="img" aria-label="Golden West members at a branch"></div>
        <div class="band-body">
            <h2 class="caps" id="how-title">Member-owned since 1958.</h2>
            <p>Golden West began as a credit union for a few dozen teachers and shipyard workers in Oakland, California. Today more than 280,000 members count on us at 46 branches across California, Nevada, Arizona, Oregon and Washington.</p>
            <p>Because we are owned by the people we serve, our earnings go back to members through lower loan rates, higher savings yields and fewer fees.</p>
            <a class="btn" href="{{ url('/about') }}">Our story</a>
        </div>
    </section>

    <section class="articles" aria-labelledby="learn-title">
        <h2 class="caps" id="learn-title">Featured insights</h2>
        <div class="rail-wrap">
            <button class="arrow prev" type="button" aria-label="Previous" data-prev><svg viewBox="0 0 22 52" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 2 3 26l17 24"/></svg></button>
            <div class="rail" data-rail>
                <a class="acard" href="{{ url('/learn/read-your-statement') }}">
                    <div class="photo" data-img="{{ asset('images/learn-1.jpg') }}"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Your Money</span><time datetime="2026-11-12">11.12.2026</time></div>
                    <h3>How to read your monthly statement in five minutes</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/fake-bank-texts') }}">
                    <div class="photo" data-img="{{ asset('images/learn-2.jpg') }}"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Security</span><time datetime="2026-11-05">11.05.2026</time></div>
                    <h3>How to spot a fraudulent text before you tap</h3></div>
                </a>
                <a class="acard" href="{{ url('/learn/held-transaction') }}">
                    <div class="photo" data-img="{{ asset('images/learn-3.jpg') }}"></div>
                    <div class="acard-body"><div class="meta"><span class="chip">Your Account</span><time datetime="2026-10-29">10.29.2026</time></div>
                    <h3>Why a payment is held, and what to do next</h3></div>
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
            <button class="arrow next" type="button" aria-label="Next" data-next><svg viewBox="0 0 22 52" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m2 2 17 24L2 50"/></svg>
            <div class="dots" data-dots></div>
        </div>
        <div class="more"><a class="btn" href="{{ url('/learn') }}">Explore more insights</a></div>
    </section>

    <section class="band photo-left on-dark" aria-labelledby="help-title">
        <div class="photo" data-img="{{ asset('images/help.jpg') }}" role="img" aria-label="A member reviewing account alerts"></div>
        <div class="band-body">
            <h2 class="caps" id="help-title">Protected around the clock.</h2>
            <p>Two-step verification, instant alerts on every transaction and a fraud team that watches accounts day and night keep your money safe.</p>
            <p>Questions about a hold or a decision on your account? Open a ticket and a member advocate will reply, usually within one business day.</p>
            <a class="btn" href="{{ url('/security') }}">Security &amp; Fraud Center</a>
        </div>
    </section>
@endsection

@push('scripts')
<script>
(function () {
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
@endpush
