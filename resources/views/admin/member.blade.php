@extends('layouts.portal')
@section('title', $member->name)
@section('band')
<p class="crumbs2"><a href="{{ route('admin.members.index') }}">Members</a> &rsaquo; {{ $member->name }}</p>
<div class="pband-row">
    <div>
        <h1>{{ $member->name }}</h1>
        <p class="psub">Member #{{ $member->member_number }} &middot; {{ $member->email }} &middot; {{ $member->phone }}</p>
        <p class="chips">
            <span class="tag">Joined {{ $member->created_at->format('M j, Y') }}</span>
            @if ($member->profile)<span class="tag">{{ str($member->profile->field_of_membership)->replace('_', ' ')->title() }}</span>@endif
            <span class="tag {{ $member->kyc_status === 'verified' ? 'ok' : 'held' }}">ID {{ $member->kyc_status === 'verified' ? 'verified' : ($member->kyc_status === 'rejected' ? 'rejected' : 'not verified') }}</span>
            @if ($restrictions->count())<span class="tag lock">{{ $restrictions->count() }} {{ \Illuminate\Support\Str::plural('restriction', $restrictions->count()) }}</span>@endif
            @if ($waiting->count())<span class="tag held">{{ $waiting->count() }} waiting</span>@endif
        </p>
    </div>
    <div class="macts">
        @can('staff.impersonate', $member)<button type="button" class="btn-s gold" data-open="dlg-act">Log in as member</button>@endcan
        @can('staff.postDeposits')<button type="button" class="btn-s plain" data-open="dlg-cash">Cash deposit</button>@endcan
        @can('staff.canAdjust')<button type="button" class="btn-s plain" data-open="dlg-adjust">Adjust balance</button>@if ($emptyAccounts->isNotEmpty()) <button type="button" class="btn-s plain" data-open="dlg-history">Add account history</button>@endif @endcan
        @if ($member->status !== 'deceased')@can('staff.lockAccounts')<button type="button" class="btn-s plain" data-open="dlg-deceased">Mark as deceased</button>@endcan @endif
        @can('staff.lockAccounts')<button type="button" class="btn-s plain" data-open="dlg-lock">Lock</button>@endcan
    </div>
</div>
@endsection
@section('content')
@php($initials = strtoupper(mb_substr($member->first_name ?? $member->name, 0, 1).mb_substr($member->last_name ?? '', 0, 1)))
@if ($errors->any())@push('toast')<div class="toast bad" role="alert"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v6M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg><p>{{ $errors->first() }}</p><button type="button" class="toast-x" aria-label="Dismiss">&times;</button></div>@endpush @endif


<div class="mgrid">
    <div class="mmain">
        <div class="sechead"><h2>Accounts</h2><span class="fine" style="margin:0">Total {{ \App\Support\Money::cents($accounts->sum(fn ($a) => $a->balance->cents))->format() }}</span></div>
        <div class="panel-card rows">
            @foreach ($accounts as $a)
                <div class="arow">
                    <div><strong>{{ $a->nickname }}</strong> @if ($a->status !== 'active')<span class="tag lock">{{ $a->status }}</span>@endif<small>{{ $a->product->name }} &middot; {{ $a->masked() }}</small></div>
                    <div class="r"><b>{{ $a->balance->format() }}</b><small>@if ($a->held_balance->cents) {{ $a->held_balance->format() }} reserved &middot; @endif Available {{ $a->available_balance->format() }}</small></div>
                </div>
            @endforeach
        </div>

        <div class="sechead"><h2>Recent transactions</h2></div>
        <div class="panel-card rows">
            @forelse ($transactions as $tx)
                <div class="arow">
                    <div><a class="plink" href="{{ route('admin.queue.show', $tx->reference) }}">{{ $tx->member_description ?? str($tx->type->value)->replace('_', ' ')->title() }}</a>
                        <small>{{ $tx->effective_date->format('M j, Y') }} &middot; {{ str($tx->type->value)->replace('_', ' ')->title() }} @if ($tx->status->value !== 'completed')<span class="chip {{ $tx->status->value }}">{{ $tx->status->value }}</span>@endif</small></div>
                    <div class="r"><b>{{ $tx->amount->format() }}</b></div>
                </div>
            @empty
                <p style="margin:0;padding:1rem">No transactions yet.</p>
            @endforelse
        </div>
    </div>

    <aside class="mside">
        <div class="panel-card">
            <h3>Needs attention</h3>
            @forelse ($waiting as $tx)
                <p class="item"><a class="plink" href="{{ route('admin.queue.show', $tx->reference) }}">{{ $tx->amount->format() }} {{ str($tx->type->value)->replace('_', ' ') }}</a> <span class="tag {{ $tx->status->value }}">{{ $tx->status->value }}</span></p>
            @empty
            @endforelse
            @foreach ($restrictions as $r)
                <div class="item lockitem">
                    <strong>{{ str($r->type)->replace('_', ' ')->title() }}</strong> on {{ $r->account ? ($r->account->nickname ?? $r->account->masked()) : 'the whole member' }}
                    <small>{{ $r->reason_text }} ({{ $r->locked_at->format('M j') }})</small>
                    @can('staff.lockAccounts')
                    <details class="act"><summary>Unlock</summary>
                        <form class="sform" method="post" action="{{ route('admin.locks.unlock', $r) }}">@csrf
                            <label>Note<input name="note" required></label><button type="submit" class="plain">Unlock</button></form>
                    </details>
                    @endcan
                </div>
            @endforeach
            @if (! $waiting->count() && ! $restrictions->count())<p style="margin:0;color:var(--muted)">Nothing waiting. No restrictions.</p>@endif
        </div>

        <div class="panel-card">
            <h3>Features</h3>
            @foreach (\App\Services\Features::ALL as $key => $label)
                @php($on = in_array($key, $features, true))
                <div class="item">{{ $label }} <span class="chip {{ $on ? 'completed' : 'pending' }}">{{ $on ? 'on' : 'off' }}</span>
                    @can('staff.toggleFeatures')
                    <form method="post" action="{{ route('admin.features.toggle', [$member, $key]) }}" style="margin-top:.35rem">@csrf
                        <input type="hidden" name="enabled" value="{{ $on ? 0 : 1 }}"><button type="submit" class="linkbtn">{{ $on ? 'Turn off' : 'Turn on' }}</button></form>
                    @endcan
                </div>
            @endforeach
        </div>

        <div class="panel-card">
            <h3>Loans</h3>
            @forelse ($loans as $l)
                <p class="item">@can('staff.approveLoans')<a class="plink" href="{{ route('admin.loans.loan', $l->reference) }}">{{ $l->product->name }}</a>@else{{ $l->product->name }}@endcan {{ $l->balanceMoney()->format() }} <span class="chip {{ $l->status === 'delinquent' ? 'rejected' : 'completed' }}">{{ str_replace('_', ' ', $l->status) }}</span></p>
            @empty
                <p style="margin:0;color:var(--muted)">No loans.</p>
            @endforelse
        </div>

        <div class="panel-card">
            <h3>Virtual cards</h3>
            @forelse ($cards as $c)
                <p class="item">@can('staff.approveCards')<a class="plink" href="{{ route('admin.cards.index', ['tab' => 'all']) }}">{{ $c->last4 ? 'Ending '.$c->last4 : 'Request' }}</a>@else{{ $c->last4 ? 'Ending '.$c->last4 : 'Request' }}@endcan <span class="chip {{ ['active' => 'completed', 'rejected' => 'rejected', 'frozen' => 'held'][$c->status] ?? 'pending' }}">{{ $c->status }}</span></p>
            @empty
                <p style="margin:0;color:var(--muted)">No card requested.</p>
            @endforelse
        </div>

        <div class="panel-card">
            <h3>Crypto wallet</h3>
            @if (! in_array('crypto', $features, true))
                <p style="margin:0;color:var(--muted)">Crypto is off for this member. Turn it on under Features.</p>
            @else
                @foreach ($cryptoAssets as $a)
                    @php($w = $cryptoWallets->get($a->id))
                    @php($addr = $cryptoAddresses->first(fn ($x) => $x->asset_id === $a->id))
                    <p class="item">{{ $a->symbol }} <b>{{ $a->format($w?->balance ?? 0, false) }}</b>@if ($w && $w->held > 0) <small>{{ $a->format($w->held, false) }} set aside</small>@endif
                        <small>@if ($addr?->status === 'active'){{ \Illuminate\Support\Str::limit($addr->address, 26, '...') }}@elseif ($addr) Address requested @else No address @endif</small></p>
                @endforeach
                @can('staff.approveCrypto')
                <details class="act"><summary>Set a deposit address</summary>
                    <form class="sform" method="post" action="{{ route('admin.crypto.address', $member) }}">@csrf
                        <label>Asset<select name="asset_id">@foreach ($cryptoAssets as $a)<option value="{{ $a->id }}">{{ $a->label() }}</option>@endforeach</select></label>
                        <label>Address<input name="address" maxlength="120" required spellcheck="false"></label>
                        <label>Memo or tag (optional)<input name="memo" maxlength="60"></label>
                        <button type="submit" class="plain">Save address</button></form></details>
                <details class="act"><summary>Record funding</summary>
                    <form class="sform" method="post" action="{{ route('admin.crypto.funding', $member) }}">@csrf
                        <label>Asset<select name="asset_id">@foreach ($cryptoAssets as $a)<option value="{{ $a->id }}">{{ $a->label() }}</option>@endforeach</select></label>
                        <label>Amount received<input name="amount" inputmode="decimal" required></label>
                        <label>Note<input name="note" maxlength="140"></label>
                        <button type="submit" class="plain">Add to wallet</button></form></details>
                @endcan
                @can('staff.canAdjust')
                <details class="act"><summary>Adjust a wallet</summary>
                    <form class="sform" method="post" action="{{ route('admin.crypto.adjust', $member) }}">@csrf
                        <label>Asset<select name="asset_id">@foreach ($cryptoAssets as $a)<option value="{{ $a->id }}">{{ $a->label() }}</option>@endforeach</select></label>
                        <div class="two2"><label>Direction<select name="direction"><option value="credit">Credit (add)</option><option value="debit">Debit (take)</option></select></label>
                            <label>Amount<input name="amount" inputmode="decimal" required></label></div>
                        <label>Description the member sees<input name="description" maxlength="140" required></label>
                        <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($adjustReasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
                        <button type="submit" class="plain">Post adjustment</button></form></details>
                @endcan
                @foreach ($cryptoTx as $t)
                    <p class="item"><small>{{ $t->created_at->format('M j') }} &middot; {{ ucfirst($t->type) }} {{ $t->asset->format($t->amount) }} &middot; {{ $t->statusLabel() }}</small></p>
                @endforeach
            @endif
        </div>

        <div class="panel-card">
            <h3>Holders and beneficiaries</h3>
            @foreach ($jointInfo as $accountId => $info)
                @php($acct = $accounts->firstWhere('id', $accountId))
                <div class="item"><b>{{ $acct->nickname }}</b> <small>{{ $acct->masked() }}</small>
                    @foreach ($info['holders'] as $h)
                        <small style="display:block">{{ $h->role === 'primary' ? 'Primary' : 'Joint' }}: <a class="plink" href="{{ route('admin.members.show', $h->user) }}">{{ $h->user->name }}</a></small>
                        @if ($info['holders']->count() > 1 && $h->user_id === $member->id)
                            @can('staff.approveJoint')
                            <details class="act"><summary>Take {{ $member->first_name ?? 'this member' }} off this account</summary>
                                <form class="sform" method="post" action="{{ route('admin.joint.remove', $member) }}">@csrf<input type="hidden" name="account_id" value="{{ $accountId }}">
                                    <label>Reason<select name="reason_code">@foreach ($lockReasons as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                                    <label>What we found<textarea name="reason_text" rows="2" maxlength="500" required></textarea></label>
                                    <button type="submit" class="plain">Remove holder</button></form></details>
                            @endcan
                        @endif
                    @endforeach
                    @forelse ($info['beneficiaries'] as $b)
                        <small style="display:block">Beneficiary: {{ $b->name }} ({{ \App\Services\BeneficiaryService::TIERS[$b->tier] }}, {{ $b->share_percent }}%)</small>
                    @empty
                        <small style="display:block">No beneficiaries named.</small>
                    @endforelse
                </div>
            @endforeach
            @if ($member->status === 'deceased')<p class="item"><span class="chip rejected">Marked deceased {{ $member->deceased_at?->format('M j, Y') }}</span></p>@endif
        </div>

        <div class="panel-card">
            <h3>Identity documents</h3>
            @forelse ($kycDocs as $d)
                <p class="item">@can('staff.reviewKyc')<a class="plink" href="{{ route('admin.kyc.show', $d) }}">{{ $d->typeLabel() }}</a>@else{{ $d->typeLabel() }}@endcan <span class="chip {{ ['approved' => 'completed', 'rejected' => 'rejected'][$d->status] ?? 'pending' }}">{{ $d->status }}</span></p>
            @empty
                <p style="margin:0;color:var(--muted)">No documents uploaded.</p>
            @endforelse
        </div>

        <div class="panel-card">
            <h3>External accounts</h3>
            @forelse ($payees as $p)
                <div class="item">{{ $p->name }} ending {{ $p->last4 }}
                    @if ($p->isActive())<span class="chip completed">ready</span>@else<small>Waiting period ends {{ $p->activates_at->format('M j, g:i a') }}</small>
                        @can('staff.waivePayeeWait')<form method="post" action="{{ route('admin.payees.waive', [$member, $p]) }}" style="margin-top:.35rem">@csrf<button type="submit" class="linkbtn">End the wait now</button></form>@endcan
                    @endif
                </div>
            @empty
                <p style="margin:0;color:var(--muted)">None saved.</p>
            @endforelse
        </div>

        <div class="panel-card">
            <h3>Staff access</h3>
            <ul class="tl">
            @forelse ($sessions as $s)<li>{{ $s->admin->name }}<small>{{ $s->started_at->format('M j, g:i a') }} &middot; {{ $actingReasons[$s->reason_code] ?? $s->reason_code }}</small></li>@empty<li style="color:var(--muted)">No staff have acted as this member.</li>@endforelse
            </ul>
        </div>

        <div class="panel-card">
            <h3>Recent audit</h3>
            <ul class="tl">
            @forelse ($audit->take(6) as $a)<li>{{ ucfirst(str_replace(['.', '_'], ' ', $a->action)) }}<small>{{ $a->created_at->format('M j, g:i a') }}@if ($a->impersonator_id) &middot; staff acting @endif</small></li>@empty<li style="color:var(--muted)">Nothing yet.</li>@endforelse
            </ul>
            @can('staff.viewAudit')<p style="margin:.8rem 0 0"><a class="plink" href="{{ route('admin.audit', ['actor' => $member->email]) }}">Full audit log</a></p>@endcan
        </div>
    </aside>
</div>

@can('staff.impersonate', $member)
<dialog id="dlg-act"><div class="dh"><h3>Log in as {{ $member->first_name ?? $member->name }}</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <p class="fine" style="margin-top:0">Opens their online banking for 30 minutes. A reason is required and the member can see it.</p>
    <form class="sform" method="post" action="{{ route('admin.impersonate.start', $member) }}">@csrf
        <label>Reason<select name="reason_code" required>@foreach ($actingReasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
        <label>Ticket or reference (optional)<input name="ticket" maxlength="60"></label>
        <label>Note (optional)<input name="reason_text" maxlength="500"></label>
        <button type="submit" class="gold">Log in as member</button>
    </form></dialog>
@endcan
@can('staff.lockAccounts')
<dialog id="dlg-lock"><div class="dh"><h3>Lock</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <form class="sform" method="post" action="{{ route('admin.locks.store', $member) }}">@csrf
        <label>Apply to<select name="target"><option value="member">The whole member</option>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach</select></label>
        <label>Type<select name="type">@foreach (\App\Services\RestrictionService::TYPES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></label>
        <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($lockReasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
        <label>Details the member can see<textarea name="reason_text" rows="2" required></textarea></label>
        <button type="submit" class="red">Apply lock</button>
    </form></dialog>
@endcan
@can('staff.postDeposits')
<dialog id="dlg-cash"><div class="dh"><h3>Cash deposit</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <p class="fine" style="margin-top:0">Record cash the member handed you at the counter. Count it twice. Amounts of {{ \App\Support\Money::cents(config('bank.cash_review_threshold'))->format() }} or more wait for a second person.</p>
    <form class="sform" method="post" action="{{ route('admin.deposits.cash', $member) }}">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Account<select name="account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach</select></label>
        <label>Amount<input name="amount" inputmode="decimal" placeholder="0.00" required></label>
        <label>Note (optional)<input name="note" maxlength="200"></label>
        <button type="submit" class="gold">Record deposit</button>
    </form></dialog>
@endcan
@can('staff.lockAccounts')
<dialog id="dlg-deceased"><div class="dh"><h3>Mark as deceased</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <form class="sform" method="post" action="{{ route('admin.deceased', $member) }}">@csrf
        <p class="fine" style="margin:0">Their sign-in stops and their cards are cancelled. Accounts only they hold are fully locked. Joint accounts stay open for the other holders. Payouts are then done with documented adjustments.</p>
        <label>What was received (for example the death certificate)<textarea name="note" rows="3" maxlength="1000" required></textarea></label>
        <button type="submit">Mark as deceased</button>
    </form></dialog>
@endcan
@can('staff.canAdjust')
<dialog id="dlg-adjust"><div class="dh"><h3>Adjust balance</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <form class="sform" method="post" action="{{ route('admin.adjustments.store', $member) }}">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Account<select name="account_id">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach</select></label>
        <div class="two2">
            <label>Direction<select name="direction"><option value="credit">Credit (add)</option><option value="debit">Debit (take)</option></select></label>
            <label>Amount<input name="amount" inputmode="decimal" placeholder="0.00" required></label>
        </div>
        <label>Description the member sees<input name="description" maxlength="140" required></label>
        <label>Reason<select name="reason_code" required><option value="">Choose a reason</option>@foreach ($adjustReasons as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></label>
        <label>Internal note<textarea name="note" rows="2" required></textarea></label>
        <button type="submit">Post adjustment</button>
        <p class="fine" style="margin:0">{{ \App\Support\Money::cents(config('bank.review_threshold'))->format() }} or more waits for another staff member. {{ \App\Support\Money::cents(config('bank.dual_approval_threshold'))->format() }} or more needs two.</p>
    </form></dialog>
@endcan

@can('staff.canAdjust')
@if ($emptyAccounts->isNotEmpty())
<dialog id="dlg-history"><div class="dh"><h3>Add account history</h3><button type="button" class="x" data-close aria-label="Close">&times;</button></div>
    <form class="sform" method="post" action="{{ route('admin.history.store', $member) }}">@csrf
        <label>Account<select name="account_id">@foreach ($emptyAccounts as $a)<option value="{{ $a->id }}" @selected(old('account_id') == $a->id)>{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach</select></label>
        <label>Balance the account should reach<input name="target" inputmode="decimal" placeholder="0.00" value="{{ old('target') }}" required>@error('target')<span class="err">{{ $message }}</span>@enderror</label>
        <div class="two2">
            <label>History starts<input type="date" name="from" value="{{ old('from', now()->subMonths(6)->toDateString()) }}" max="{{ now()->toDateString() }}" required>@error('from')<span class="err">{{ $message }}</span>@enderror</label>
            <label>History ends<input type="date" name="to" value="{{ old('to', now()->subDay()->toDateString()) }}" max="{{ now()->toDateString() }}" required>@error('to')<span class="err">{{ $message }}</span>@enderror</label>
        </div>
        <label style="display:flex;gap:.5rem;align-items:center"><input type="checkbox" name="backdate_open" value="1" @checked(old('backdate_open', '1'))> Show the account as opened on the start date</label>
        <label>Internal note<textarea name="note" rows="2" required>{{ old('note') }}</textarea></label>
        <button type="submit">Add history</button>
        <p class="fine" style="margin:0">Payroll deposits, card purchases, bills and cash withdrawals (or deposits and dividends on savings) are made up between the two dates. They end on exactly this balance. This can only be done once, on an account with no activity. It is recorded in the audit log.</p>
    </form></dialog>
@endif
@endcan

@push('scripts')
<script>
document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById(b.dataset.open).showModal(); }); });
document.querySelectorAll('dialog').forEach(function (d) {
    d.querySelectorAll('[data-close]').forEach(function (c) { c.addEventListener('click', function () { d.close(); }); });
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
});
</script>
@endpush
@endsection
