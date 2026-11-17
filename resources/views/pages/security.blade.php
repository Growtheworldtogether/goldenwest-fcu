@extends('layouts.inner')
@section('title', 'Security & Fraud Center | Golden West Federal Credit Union')
@section('description', 'How Golden West protects your accounts, how to spot common scams, and what to do if you think you have been targeted.')
@section('crumb', 'Security & Fraud Center')
@section('heading', 'Security & Fraud Center')
@section('intro', 'Protecting your money is our most important job. Learn how we help, and how you can stay a step ahead of fraud.')
@section('hero-action')<a class="btn" href="tel:8005550147">Report fraud: 800-555-0147</a>@endsection

@section('body')
<section class="sec">
    <div class="wrap">
        <div class="notice"><p><strong>Golden West will never</strong> call, text or email to ask for your password, PIN, full card number or the one-time code we send you. If someone asks, hang up and call us at <a href="tel:8005550147">800-555-0147</a>.</p></div>
        <h2>How we protect you</h2>
        <div class="grid-3">
            <article class="tile"><h3>24/7 monitoring</h3><p>Our systems review card and account activity around the clock and flag unusual transactions for review.</p></article>
            <article class="tile"><h3>Encrypted connections</h3><p>Everything you send us through online and mobile banking is encrypted in transit.</p></article>
            <article class="tile"><h3>Verification at sign-in</h3><p>We confirm it is you with a one-time code when you sign in from a new device.</p></article>
        </div>
    </div>
</section>
<section class="sec alt">
    <div class="wrap">
        <h2>Common scams</h2>
        <div class="faq">
            <details open><summary>Fake bank calls and texts</summary><p>Scammers spoof our phone number and send urgent messages about "suspicious activity." We would never ask for a code. Hang up and call us at the number on the back of your card.</p></details>
            <details><summary>Payment app and wire scams</summary><p>Be wary of anyone who pressures you to send money quickly, especially to "protect" your account. Real banks never ask you to move money to stay safe.</p></details>
            <details><summary>Fraudulent checks</summary><p>A check can appear in your account before it clears. Never send money back to someone who overpaid you with a check.</p></details>
            <details><summary>Phishing emails and websites</summary><p>Look-alike sites try to collect your sign-in. Type our address yourself or use our app rather than following links.</p></details>
        </div>
    </div>
</section>
<section class="sec">
    <div class="wrap split">
        <div>
            <h2>If something looks wrong</h2>
            <ol class="checks" style="list-style:none">
                <li>Call Customer Care at <a href="tel:8005550147">800-555-0147</a> right away.</li>
                <li>Lock your card in the mobile app, or tell us to do it.</li>
                <li>Change your online banking password.</li>
                <li>Review recent transactions and tell us about any you do not recognize.</li>
            </ol>
            <p class="fine">Lost or stolen card outside business hours? Call the same number and choose the card option. It is answered around the clock.</p>
        </div>
        <aside class="aside on-dark"><h3>Report a suspicious message</h3><p>Forward a suspicious message or describe what happened and our fraud team will review it.</p><a class="btn" href="{{ url('/contact') }}">Send a report</a></aside>
    </div>
</section>
@endsection
