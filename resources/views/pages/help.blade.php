@extends('layouts.inner')
@section('title', 'Help Center | '.brand('name'))
@section('description', 'Answers to common questions, plus how to reset your password or contest a decision on your account.')
@section('crumb', 'Help Center')
@section('heading', 'How can we help?')
@section('intro', 'Find quick answers below, or reach Customer Care any time at '.brand('support_phone').'.')

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>Popular questions</h2>
        <div class="faq">
            <details><summary>How do I find my routing and account numbers?</summary><p>Our routing number is 121478906. Your account number is shown in online banking under Account details, and at the bottom of your checks.</p></details>
            <details><summary>How do I order checks?</summary><p>Order from online banking under Account services, or call Customer Care. New checks usually arrive in seven to ten business days.</p></details>
            <details><summary>How do I report a lost or stolen card?</summary><p>Lock it in the mobile app right away, then call <a href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a>. We will issue a replacement.</p></details>
            <details><summary>When will my deposit be available?</summary><p>Funds availability depends on the type of deposit. See the Disclosures page for details.</p></details>
            <details><summary>How do I update my address or phone number?</summary><p>Sign in and choose Profile, or visit a branch with a photo ID.</p></details>
        </div>
    </div>
</section>
<section class="sec alt" id="password">
    <div class="wrap split">
        <div>
            <h2>Reset your password</h2>
            <ol class="checks" style="list-style:none">
                <li>Go to the login page and choose <em>Forgot password</em>.</li>
                <li>Enter the email address on your account.</li>
                <li>Follow the link we send. It expires after one hour.</li>
            </ol>
            <p class="fine">We will never ask for your password. If you did not request a reset, ignore the email and call us.</p>
        </div>
        <aside class="aside on-dark"><h3>Locked out?</h3><p>Call <a href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a>. We will verify your identity and unlock your access.</p></aside>
    </div>
</section>
<section class="sec" id="contest">
    <div class="wrap split">
        <div>
            <h2>Contest a decision on your account</h2>
            <p>If you disagree with a decision we made, such as a held payment, a declined transfer or a fee, you can ask us to review it. A team member who was not part of the original decision will look at your request.</p>
            <ol class="checks" style="list-style:none">
                <li>Sign in to online banking and open the transaction or message in question, then choose <em>Contest this decision</em>.</li>
                <li>Tell us what happened and attach anything that helps.</li>
                <li>We reply in writing, usually within five business days, with the outcome and our reasoning.</li>
            </ol>
            <p>You can also tell us by phone at <a href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a> or <a href="{{ url('/contact') }}">send a message</a>.</p>
        </div>
        <aside class="aside on-dark"><h3>Need a person?</h3><p>Customer Care hours: {{ brand('support_hours') }}.</p><a class="btn" href="{{ url('/contact') }}">Contact us</a></aside>
    </div>
</section>
@endsection
