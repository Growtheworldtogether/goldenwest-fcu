@extends('layouts.inner')
@section('title', 'Checking and Savings Accounts | '.brand('name'))
@section('description', 'Everyday checking, savings, money market and certificates with no fee to join and no minimum balance on checking.')
@section('crumb', 'Checking & Savings')
@section('heading', 'Checking and savings')
@section('intro', 'Accounts that stay simple: no fee to join, no minimum balance on checking, and rates that reward you for saving.')
@section('hero-action')<a class="btn" href="{{ url('/join') }}">Open an account</a>@endsection

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>Choose the account that fits</h2>
        <p class="lead">Every {{ brand('short_name') }} account comes with online and mobile banking, a debit card, and free access to member service seven days a week.</p>
        <div class="grid-3">
            <article class="tile">
                <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><rect x="6" y="8" width="52" height="32" rx="4"/><path d="M6 18h52M14 30h14"/></svg>
                <h3>Everyday Checking</h3>
                <p>For paychecks, bills and daily spending. No monthly fee and no minimum balance.</p>
                <ul class="checks"><li>Free debit card and bill pay</li><li>Early direct deposit</li><li>Overdraft protection options</li></ul>
                <a class="more" href="{{ url('/join') }}">Open checking</a>
            </article>
            <article class="tile">
                <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><ellipse cx="22" cy="14" rx="14" ry="6"/><path d="M8 14v10c0 3.300 6.300 6 14 6s14-2.700 14-6V14"/><path d="M36 24h20v14c0 3-6 5-14 5"/></svg>
                <h3>Golden Savings</h3>
                <p>A place to build a cushion. Earn interest from your first dollar, compounded daily.</p>
                <ul class="checks"><li>No opening deposit</li><li>Set up automatic transfers</li><li>Create savings goals in the app</li></ul>
                <a class="more" href="{{ url('/join') }}">Open savings</a>
            </article>
            <article class="tile">
                <svg class="ico" viewBox="0 0 64 48" aria-hidden="true"><path d="M8 40h48M14 40V22M26 40V22M38 40V22M50 40V22M6 22 32 8l26 14z"/></svg>
                <h3>Money Market</h3>
                <p>Higher yield for larger balances, with check-writing and debit card access.</p>
                <ul class="checks"><li>Tiered rates</li><li>Check-writing included</li><li>$10,000 to earn the top rate</li></ul>
                <a class="more" href="{{ url('/rates') }}">See rates</a>
            </article>
        </div>
    </div>
</section>

<section class="sec alt">
    <div class="wrap split">
        <div>
            <h2>Certificates for the long term</h2>
            <p>If you will not need the money for a while, a certificate locks in a fixed rate for the term you choose, from three months to five years. Rates stay the same no matter what the market does.</p>
            <ul class="checks"><li>$500 minimum to open</li><li>Terms from 3 months to 5 years</li><li>Choose to receive interest monthly or at maturity</li><li>Ask about early withdrawal terms before you open</li></ul>
            <a class="btn" href="{{ url('/rates') }}">View certificate rates</a>
        </div>
        <div class="splitcol">@include('partials.bodyphoto', ['name' => 'accounts-switching'])<aside class="aside on-dark">
            <h3>Switching is easy</h3>
            <p>Moving your direct deposit and bill payments takes about 15 minutes. Our team can walk you through it by phone or in a branch.</p>
            <a class="btn" href="{{ url('/appointments') }}">Talk to a banker</a>
        </aside></div>
    </div>
</section>

<section class="sec">
    <div class="wrap">
        <h2>Questions about accounts</h2>
        <div class="faq">
            <details><summary>Who can open an account?</summary><p>Anyone who lives, works, worships or attends school in California, Nevada, Arizona, Oregon or Washington can join, along with family members of current members.</p></details>
            <details><summary>What do I need to open an account?</summary><p>A government-issued photo ID, your Social Security number and a mailing address. Opening takes about ten minutes online.</p></details>
            <details><summary>Is there a monthly fee?</summary><p>Everyday Checking has no monthly fee and no minimum balance. See the Disclosures page for the full fee schedule.</p></details>
            <details><summary>How do I deposit a check?</summary><p>Take a photo of it in the mobile app, or visit any branch. Funds availability is described on the Disclosures page.</p></details>
        </div>
        <p class="fine">Annual percentage yields are accurate as of the date shown on the Rates page and may change after the account is opened.</p>
    </div>
</section>
@endsection

@section('cta')
<h2>Ready to get started?</h2><p>Open your account online in about ten minutes.</p>
@endsection
