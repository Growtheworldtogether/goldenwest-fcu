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
    <div>
        <h2 style="margin:0 0 .6rem">Wire transfers</h2>
        <div class="fgrid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:1rem">
            <label>Fee per wire, in dollars<input name="wire_fee" type="number" step="0.01" min="0" max="500" value="{{ number_format($wireFee, 2, '.', '') }}">@error('wire_fee')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Fee per international wire, in dollars<input name="wire_fee_intl" type="number" step="0.01" min="0" max="500" value="{{ number_format($wireFeeIntl, 2, '.', '') }}">@error('wire_fee_intl')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Same-day cutoff, Pacific time<input name="wire_cutoff" type="time" value="{{ $wireCutoff }}">@error('wire_cutoff')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
        <p class="fine" style="margin-bottom:0">Wires requested before the cutoff on a business day are sent that day once approved. Later ones are sent the next business day.</p>
    </div>
    <div>
        <h2 style="margin:0 0 .6rem">Where membership is open</h2>
        <p class="fine" style="margin-top:0">People with a home, job, school or place of worship in a checked state can join. Family of current members can always join.</p>
        <input type="hidden" name="region_form" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));gap:.4rem .9rem">
            @foreach (\App\Support\Region::STATES as $code => $name)
                <label class="chk" style="display:flex;gap:.5rem;align-items:center;font-weight:400"><input type="checkbox" name="region_states[]" value="{{ $code }}" @checked(in_array($code, old('region_states', $regionCodes)))>{{ $name }}</label>
            @endforeach
        </div>
        @error('region_states')<span class="err">{{ $message }}</span>@enderror
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
