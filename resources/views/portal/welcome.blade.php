@extends('layouts.auth')
@section('title', 'Welcome | '.brand('name'))
@section('box-class', 'wide')
@section('art', 'branch.jpg')
@section('art-title', 'Welcome to '.brand('short_name'))
@section('art-text', 'You are now a member-owner. Your accounts are open and ready.')
@section('heading', 'Setting up your membership')
@section('form')
<div class="welcome" id="welcome">
    <ol class="runlist" id="runlist" aria-live="polite">
        @foreach (['Checking your information', 'Confirming your eligibility', 'Receiving your documents', 'Opening your accounts', 'Securing your login'] as $label)
            <li><span class="mark" aria-hidden="true"></span><span>{{ $label }}</span></li>
        @endforeach
    </ol>
    <div class="runbar" id="runbar" aria-hidden="true"><i></i></div>

    <div class="finished" id="finished">
        <p class="caps">Congratulations</p>
        <h3>Welcome, {{ $user->first_name }}. Your accounts are open.</h3>
        <p class="fine" style="margin-top:0">Member number <strong>{{ $user->member_number }}</strong>. Keep it handy when you call Customer Care.</p>

        <div class="acctlist">
            @foreach ($accounts as $a)
                <div class="acct">
                    <div><strong>{{ $a->nickname ?: $a->product->name }}</strong><small>{{ $a->kindLabel() }}@if ($a->product->isBusiness() && $user->businessProfile) &middot; {{ $user->businessProfile->legal_name }}@endif</small></div>
                    <dl>
                        <dt>Account number</dt><dd>{{ $a->account_number }}</dd>
                        <dt>Routing number</dt><dd>121478906</dd>
                        <dt>Balance</dt><dd>$0.00</dd>
                    </dl>
                </div>
            @endforeach
        </div>

        <p class="fine">{{ $docs >= 2 ? 'Your documents are with our review team, usually for one business day. Until you are verified you cannot deposit checks or move money in from another bank.' : 'Please upload your documents from Verify your identity in online banking.' }}
        @unless ($user->hasVerifiedEmail()) We also sent a link to {{ $user->email }}. Open it to confirm your email address.@endunless</p>
        <a class="btn" href="{{ route('dashboard') }}">Go to my dashboard</a>
    </div>
</div>
@push('scripts')
<script>
(function () {
    var root = document.getElementById('welcome');
    var items = Array.prototype.slice.call(document.querySelectorAll('#runlist li'));
    var bar = document.querySelector('#runbar i');
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    root.classList.add('is-running');
    function finish() { root.classList.remove('is-running'); root.classList.add('is-done'); document.getElementById('finished').setAttribute('tabindex', '-1'); document.getElementById('finished').focus({ preventScroll: true }); }
    if (reduce) { items.forEach(function (li) { li.classList.add('ok'); }); bar.style.width = '100%'; finish(); return; }
    var i = 0;
    function step() {
        if (i > 0) items[i - 1].classList.replace('now', 'ok');
        if (i === items.length) { bar.style.width = '100%'; setTimeout(finish, 450); return; }
        items[i].classList.add('now'); bar.style.width = Math.round((i + .5) / items.length * 100) + '%'; i++;
        setTimeout(step, 900 + Math.random() * 500);
    }
    step();
})();
</script>
@endpush
@endsection
