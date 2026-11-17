@extends('layouts.inner')
@section('title', 'Schedule an Appointment | '.brand('name'))
@section('description', 'Schedule time with a '.brand('short_name').' banker by phone or in person.')
@section('crumb', 'Schedule an Appointment')
@section('heading', 'Schedule an appointment')
@section('intro', 'Meet with a banker about opening an account, a loan, or your business. We will call to confirm a time that works for you.')

@section('body')
<section class="sec">
    <div class="wrap split">
        <div>
            <h2>Request a time</h2>
            <form class="form" method="post" action="{{ route('appointments.send') }}">
                @csrf
                <div class="row">
                    <label>Full name<input name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<span class="err">{{ $message }}</span>@enderror</label>
                    <label>Phone<input type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel">@error('phone')<span class="err">{{ $message }}</span>@enderror</label>
                </div>
                <label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
                <div class="row">
                    <label>I would like to discuss
                        <select name="topic" required>
                            @foreach (['Opening an account', 'A personal or auto loan', 'Business banking', 'Planning and saving', 'Something else'] as $t)
                                <option @selected(old('topic') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>City or ZIP code<input name="location" value="{{ old('location') }}" required>@error('location')<span class="err">{{ $message }}</span>@enderror</label>
                </div>
                <label>Best day and time<input name="preferred_when" value="{{ old('preferred_when') }}" placeholder="For example, weekdays after 3pm" required>@error('preferred_when')<span class="err">{{ $message }}</span>@enderror</label>
                <label>Anything we should know? (optional)<textarea name="message">{{ old('message') }}</textarea></label>
                <button class="btn" type="submit">Request appointment</button>
            </form>
        </div>
        <aside class="aside on-dark"><h3>Prefer to call?</h3><p>Customer Care can book your appointment right away at <a href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a>.</p><p>Appointments are available by phone, video or in person.</p></aside>
    </div>
</section>
@endsection
