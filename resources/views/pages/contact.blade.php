@extends('layouts.inner')
@section('title', 'Contact Us | Golden West Federal Credit Union')
@section('description', 'Reach Golden West Customer Care by phone or send us a message.')
@section('crumb', 'Contact Us')
@section('heading', 'Contact us')
@section('intro', 'We are here to help. Call, send a message or schedule time with a banker.')

@section('body')
<section class="sec">
    <div class="wrap split">
        <div>
            @if (session('sent'))<div class="flash" role="status">{{ session('sent') }}</div>@endif
            <h2>Send us a message</h2>
            <p class="fine">Please do not include your full account number, password or Social Security number in a message.</p>
            <form class="form" method="post" action="{{ route('contact.send') }}">
                @csrf
                <div class="row">
                    <label>Full name<input name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
                    <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
                </div>
                <div class="row">
                    <label>Phone (optional)<input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel"></label>
                    <label>Topic
                        <select name="topic" required>
                            @foreach (['Accounts', 'Loans', 'Cards', 'Online banking', 'Report fraud', 'Scholarships', 'Careers', 'Something else'] as $t)
                                <option @selected(old('topic') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label>How can we help?<textarea name="message" required>{{ old('message') }}</textarea>@error('message')<span class="err">{{ $message }}</span>@enderror</label>
                <button class="btn" type="submit">Send message</button>
            </form>
        </div>
        <aside class="aside on-dark">
            <h3>Customer Care</h3>
            <p><a href="tel:8005550147" style="font-size:1.4rem;font-weight:700">800-555-0147</a></p>
            <p>Mon-Fri: 7am - 8pm PT<br>Sat: 8am - 5pm PT</p>
            <p>Lost or stolen card? Call any time, day or night.</p>
            <a class="btn" href="{{ url('/appointments') }}">Schedule an appointment</a>
        </aside>
    </div>
</section>
@endsection
