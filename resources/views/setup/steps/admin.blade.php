@php $a = $d['admin'] ?? []; @endphp
<h1>First administrator</h1>
<p class="lead">This account has full access to the staff area. You will add tellers, managers and others from there. Two-step verification can be turned on from the Security page after you sign in.</p>
<div class="grid">
    <label>Full name<input name="name" value="{{ old('name', $a['name'] ?? '') }}" required autofocus>@error('name')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Email<input type="email" name="email" value="{{ old('email', $a['email'] ?? '') }}" required autocomplete="off">@error('email')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Password<input type="password" name="password" required autocomplete="new-password"><small>At least 12 characters, with upper and lower case letters and a number.</small>@error('password')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Repeat password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
</div>
<div class="actions"><button class="btn">Continue</button><a class="btn ghost" href="{{ route('setup', 'email') }}">Back</a></div>
