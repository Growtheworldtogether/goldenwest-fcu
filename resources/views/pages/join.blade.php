@extends('layouts.inner')
@section('title', 'Join Golden West | Golden West Federal Credit Union')
@section('description', 'Become a Golden West member. Joining is free and opening an account takes about ten minutes.')
@section('crumb', 'Join')
@section('heading', 'Become a member')
@section('intro', 'Joining is free and opening an account takes about ten minutes. No opening deposit is needed for checking.')

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>How to join</h2>
        <ol class="steps">
            <li><b>Check eligibility</b>You can join if you live, work, worship or attend school in California, Nevada, Arizona, Oregon or Washington, or if a family member is a member.</li>
            <li><b>Tell us about you</b>Have a government photo ID, your Social Security number and your address ready.</li>
            <li><b>Open your accounts</b>Your savings and checking accounts open right away with no deposit and no fee. Add money whenever you are ready, or open anything else you need.</li>
        </ol>
    </div>
</section>
<section class="sec alt">
    <div class="wrap split">
        <div>
            <h2>Ready when you are</h2>
            <p>Opening your accounts takes about ten minutes. You will need a government-issued photo ID, the last four digits of your Social Security number and your home address.</p>
            <ul class="checks"><li>No fee to join and no opening deposit</li><li>A savings and a checking account, open right away</li><li>Online and mobile banking from day one</li></ul>
            <a class="btn" href="{{ route('register') }}">Open my accounts</a>
            <p class="fine" style="margin-top:1rem">Already a member? <a href="{{ route('login') }}" style="color:var(--blue)">Log in</a>.</p>
        </div>
        <aside class="aside on-dark"><h3>Member benefits</h3><ul class="checks"><li>No fee to join</li><li>No minimum balance on checking</li><li>Fixed-rate loans</li><li>Online and mobile banking</li></ul><p>Questions? Call <a href="tel:8005550147">800-555-0147</a>.</p></aside>
    </div>
</section>
@endsection
