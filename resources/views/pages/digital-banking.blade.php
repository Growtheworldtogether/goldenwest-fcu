@extends('layouts.inner')
@section('title', 'Digital Banking | '.brand('name'))
@section('description', 'Online and mobile banking with deposits, transfers, bill pay, card controls and instant alerts.')
@section('crumb', 'Digital banking')
@section('heading', 'Bank anywhere, any time')
@section('intro', 'Deposit a check, pay a bill, move money and lock a lost card from your phone or computer, day or night.')
@section('hero-action')<a class="btn" href="{{ url('/login') }}">Log in</a>@endsection

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>What you can do</h2>
        <div class="grid-3">
            <article class="tile"><h3>Deposit checks</h3><p>Snap a photo of a check and deposit it in seconds. No trip to a branch.</p></article>
            <article class="tile"><h3>Move money</h3><p>Transfer between your accounts, to other members, or to accounts at other banks.</p></article>
            <article class="tile"><h3>Pay bills</h3><p>Schedule one-time or recurring payments to nearly anyone, and see what is coming up.</p></article>
            <article class="tile"><h3>Control your cards</h3><p>Lock and unlock a card instantly, set travel notices and see pending purchases.</p></article>
            <article class="tile"><h3>Get alerts</h3><p>Choose alerts for low balances, large purchases and deposits, by text, email or push.</p></article>
            <article class="tile"><h3>Save toward goals</h3><p>Create goals, automate transfers and watch your progress grow.</p></article>
        </div>
    </div>
</section>
<section class="sec alt">
    <div class="wrap split">
        <div>
            <h2>Built to keep you safe</h2>
            <ul class="checks"><li>Two-step verification on every sign-in from a new device</li><li>Fingerprint and face unlock on supported phones</li><li>Automatic sign-out after a period of inactivity</li><li>Real-time monitoring for unusual activity</li></ul>
            <a class="btn" href="{{ url('/security') }}">Visit the Security &amp; Fraud Center</a>
        </div>
        <aside class="aside on-dark"><h3>New to online banking?</h3><p>Choose Log in, then select <em>Enroll</em>. You will need your account number and Social Security number. Need help? Call <a href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a>.</p></aside>
    </div>
</section>
@endsection
