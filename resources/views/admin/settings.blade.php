@extends('layouts.portal')
@section('title', 'Settings')
@section('band')
<h1>Settings</h1>
<p class="psub">Super Admin only.</p>
@endsection
@section('content')

<form method="post" action="{{ route('admin.settings.update') }}" class="panel-card" style="max-width:760px;display:grid;gap:1.25rem">
    @csrf @method('PUT')
    <div>
        <h2 style="margin:0 0 .6rem">Acting as a member</h2>
        <label class="chk" style="display:flex;gap:.6rem;align-items:flex-start;font-weight:700;color:var(--navy)"><input type="checkbox" name="impersonation_allow_money_actions" value="1" @checked($moneyActions) style="margin-top:.3rem"><span>Allow money actions<small style="display:block;font-weight:400;color:var(--muted)">Staff acting as a member can move money. Each one still asks for the staff member's own two-step code or password.</small></span></label>
    </div>
    <div>
        <h2 style="margin:0 0 .6rem">Branding and site content</h2>
        <label class="chk" style="display:flex;gap:.6rem;align-items:flex-start;font-weight:700;color:var(--navy)"><input type="checkbox" name="managers_edit_site" value="1" @checked($managersEditSite) style="margin-top:.3rem"><span>Managers can edit branding and public pages<small style="display:block;font-weight:400;color:var(--muted)">Super Admin can always do this. Turn this on to let Managers do it too.</small></span></label>
    </div>
    <div><button type="submit" class="btn-s">Save settings</button></div>
</form>

<div class="table-title"><h2>Staff</h2></div>
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
