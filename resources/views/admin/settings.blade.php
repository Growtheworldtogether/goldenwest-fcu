@extends('layouts.portal')
@section('title', 'Settings')
@section('band')
<h1>Settings</h1>
<p class="psub">Super Admin only.</p>
@endsection
@section('content')

<h2 class="group-title" style="margin-top:0">Acting as a member</h2>
<form method="post" action="{{ route('admin.settings.update') }}" class="panel-card flush srows">
    @csrf @method('PUT')
    <div class="srow">
        <div><h3>Allow money actions</h3><p>Staff acting as a member can move money. Each one still asks for the staff member's own two-step code or password.</p></div>
        <div class="ctl">
            <label class="switch"><input type="checkbox" name="impersonation_allow_money_actions" value="1" @checked($moneyActions) aria-label="Allow money actions while acting as a member"><i></i></label>
            <button type="submit" class="btn-s sm">Save</button>
        </div>
    </div>
</form>

<h2 class="group-title">Staff</h2>
<div class="panel-card flush">
    <table class="dtable">
        <thead><tr><th>Name</th><th>Title</th><th class="hide-s">Email</th><th>Two-step</th></tr></thead>
        <tbody>
        @foreach ($staff as $s)
            <tr><td><strong>{{ $s->name }}</strong></td><td>{{ $s->adminTitle() }}</td><td class="hide-s">{{ $s->email }}</td><td><span class="pill {{ $s->hasTwoFactor() ? 'on' : 'off' }}">{{ $s->hasTwoFactor() ? 'On' : 'Off' }}</span></td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
