@extends('layouts.portal')
@section('title', 'Settings')
@section('content')
<h1>Settings</h1>
<p class="psub">Super Admin only.</p>
<div class="cols">
<div class="panel-card">
    <h3>Acting as a member</h3>
    <form class="sform" method="post" action="{{ route('admin.settings.update') }}">@csrf @method('PUT')
        <label style="grid-template-columns:auto 1fr;align-items:start;gap:.7rem;font-weight:400"><input type="checkbox" name="impersonation_allow_money_actions" value="1" @checked($moneyActions) style="width:auto;margin-top:.25rem"> Allow money actions while staff are acting as a member (each one still needs the staff member's own code)</label>
        <button type="submit">Save</button>
    </form>
</div>
<div class="panel-card">
    <h3>Staff</h3>
    <ul class="tl">@foreach ($staff as $s)<li>{{ $s->name }} <span class="tag ok">{{ $s->adminTitle() }}</span><small>{{ $s->email }} &middot; Two-step {{ $s->hasTwoFactor() ? 'on' : 'off' }}</small></li>@endforeach</ul>
</div>
</div>
@endsection
