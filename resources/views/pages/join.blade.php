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
            <li><b>Fund your account</b>Make a $5 deposit to your savings, which is your share in Golden West, then add checking or anything else you need.</li>
        </ol>
    </div>
</section>
<section class="sec alt">
    <div class="wrap split">
        <div>
            @if (session('sent'))<div class="flash" role="status">{{ session('sent') }}</div>@endif
            <h2>Get started</h2>
            <p>Tell us a little about yourself and we will email you a secure link to finish opening your account.</p>
            <form class="form" method="post" action="{{ route('join.send') }}">
                @csrf
                <label>Full name<input name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
                <div class="row">
                    <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
                    <label>Phone (optional)<input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel"></label>
                </div>
                <label>I am interested in
                    <select name="topic" required>
                        @foreach (['Checking', 'Savings', 'Checking and savings', 'An auto or personal loan', 'Business banking'] as $t)
                            <option @selected(old('topic') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn" type="submit">Continue</button>
            </form>
            <p class="fine">Do not enter your Social Security number here. We will collect it later on a secure page.</p>
        </div>
        <aside class="aside on-dark"><h3>Member benefits</h3><ul class="checks"><li>No fee to join</li><li>No minimum balance on checking</li><li>Fixed-rate loans</li><li>Online and mobile banking</li></ul><p>Questions? Call <a href="tel:8005550147">800-555-0147</a>.</p></aside>
    </div>
</section>
@endsection
