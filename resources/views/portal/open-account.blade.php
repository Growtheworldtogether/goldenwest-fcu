@extends('layouts.portal')
@section('title', 'Open an account')
@section('content')
<h1>Open another account</h1>
<p class="psub">There is no fee and no opening deposit. Your account is ready right away.</p>
<div class="panel-card">
    <form class="form" method="post" action="{{ route('accounts.store') }}">
        @csrf
        <label>Account type
            <select name="product" required>
                @foreach ($products as $code => $name)<option value="{{ $code }}" @selected(old('product') === $code)>{{ $name }}</option>@endforeach
            </select>
            @error('product')<span class="err">{{ $message }}</span>@enderror
        </label>
        <label>Nickname (optional)<input name="nickname" value="{{ old('nickname') }}" maxlength="40" placeholder="For example, Vacation fund">@error('nickname')<span class="err">{{ $message }}</span>@enderror</label>
        <button class="btn" type="submit">Open account</button>
    </form>
    <p class="fine" style="margin-top:1rem">Rates are on our <a class="plink" href="{{ url('/rates') }}">Rates page</a>. Money Market earns its top rate on balances of $10,000 or more.</p>
</div>
@endsection
