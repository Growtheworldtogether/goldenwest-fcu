@extends('layouts.inner')
@section('title', 'Personal and Auto Loans | '.brand('name'))
@section('description', 'Fixed-rate personal and auto loans with clear terms, no prepayment penalty and decisions you can understand.')
@section('crumb', 'Loans')
@section('heading', 'Loans with clear terms')
@section('intro', 'Fixed rates, fixed payments and no prepayment penalty. Know exactly what you will pay before you sign.')
@section('hero-action')<a class="btn" href="{{ url('/join') }}">Start an application</a>@endsection

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>Borrow for what is next</h2>
        <div class="grid-3">
            <article class="tile">
                <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><path d="M6 32l5-12h30l9 8h8v8H6zM16 40a4 4 0 1 0 .1 0M44 40a4 4 0 1 0 .1 0"/></svg>
                <h3>Auto loans</h3>
                <p>New or used, buy from a dealer or a private seller, or refinance the loan you have now.</p>
                <p class="big">From 5.49% <small style="font-size:1rem">APR</small></p>
                <a class="more" href="{{ url('/rates') }}">See auto rates</a>
            </article>
            <article class="tile">
                <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><circle cx="32" cy="14" r="10"/><path d="M5 36h12v10H5zM17 38l9-3h12c3 0 4 3 1 4l-10 2"/></svg>
                <h3>Personal loans</h3>
                <p>For a move, a repair, a medical bill or consolidating higher-rate debt, from $1,000 to $50,000.</p>
                <p class="big">From 8.99% <small style="font-size:1rem">APR</small></p>
                <a class="more" href="{{ url('/rates') }}">See personal rates</a>
            </article>
            <article class="tile">
                <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><rect x="10" y="20" width="44" height="22" rx="3"/><path d="M22 20v-6a10 10 0 0 1 20 0v6"/></svg>
                <h3>Share-secured loans</h3>
                <p>Borrow against your savings at a low rate while your money keeps earning, and build your credit history.</p>
                <p class="big">From 4.25% <small style="font-size:1rem">APR</small></p>
                <a class="more" href="{{ url('/rates') }}">See secured rates</a>
            </article>
        </div>
    </div>
</section>

<section class="sec alt">
    <div class="wrap">
        <h2>How it works</h2>
        <ol class="steps">
            <li><b>Apply online</b>It takes about ten minutes and checking your options will not affect your credit score.</li>
            <li><b>Get a decision</b>Most applications are reviewed the same business day. If we cannot approve, we tell you why in plain language.</li>
            <li><b>Receive your funds</b>Approved funds go to your {{ brand('short_name') }} account or directly to the seller, usually within one business day.</li>
        </ol>
    </div>
</section>

<section class="sec">
    <div class="wrap split">
        <div>
            <h2>Why members borrow here</h2>
            <ul class="checks"><li>Fixed rates, so your payment never changes</li><li>No application fee, no origination fee</li><li>No penalty for paying early</li><li>A real person reviews every decision, and you can contest one at any time</li><li>Terms from 12 to 72 months</li></ul>
            <p class="fine">Rates shown are the lowest available and depend on creditworthiness, term and loan amount. Annual percentage rates are accurate as of the date shown on the Rates page.</p>
        </div>
        <aside class="aside on-dark">
            <h3>Estimate a payment</h3>
            <p>A $20,000 auto loan at 5.49% APR for 60 months has a payment of about $382 a month.</p>
            <a class="btn" href="{{ url('/appointments') }}">Speak with a lender</a>
        </aside>
    </div>
</section>
@endsection

@section('cta')
<h2>Check your options today</h2><p>Applying will not affect your credit score.</p>
@endsection
@section('cta-label', 'Start an application')
