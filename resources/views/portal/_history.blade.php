{{-- Filters and transaction table shared by the account and activity pages. Expects $rows, $page, $groups, $filters, $accounts, $account (nullable), $exportUrl --}}
<form class="filters" method="get">
    <label>Search<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Merchant, memo or amount"></label>
    @unless ($account)
        <label>Account
            <select name="account"><option value="">All accounts</option>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((string) ($filters['account'] ?? '') === (string) $a->id)>{{ $a->nickname }} {{ $a->masked() }}</option>@endforeach
            </select>
        </label>
    @endunless
    <label>Type
        <select name="type"><option value="">All types</option>
            @foreach ($groups as $key => [$label])<option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $label }}</option>@endforeach
        </select>
    </label>
    <label>Money
        <select name="direction"><option value="">In and out</option><option value="in" @selected(($filters['direction'] ?? '') === 'in')>Money in</option><option value="out" @selected(($filters['direction'] ?? '') === 'out')>Money out</option></select>
    </label>
    <label>Status
        <select name="status"><option value="">Any status</option>
            @foreach (['completed' => 'Completed', 'pending' => 'Pending', 'held' => 'On hold', 'rejected' => 'Declined', 'reversed' => 'Reversed'] as $v => $l)<option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>@endforeach
        </select>
    </label>
    <label>From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
    <label>To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
    <button class="btn" type="submit">Apply</button>
</form>

<div class="panel-card" style="padding:.5rem 1rem 1rem">
    @if (count($rows))
        <div style="overflow-x:auto">
        <table class="txtable">
            <thead><tr><th>Date</th><th>Description</th>@unless ($account)<th class="hide-s">Account</th>@endunless<th style="text-align:right">Amount</th><th style="text-align:right" class="hide-s">Balance</th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td style="white-space:nowrap">{{ $r['tx']->effective_date->format('M j, Y') }}</td>
                    <td>
                        <a href="{{ route('transactions.show', $r['tx']->reference) }}">{{ $r['title'] }}</a>
                        @if ($r['tx']->status->value !== 'completed')
                            <span class="tag {{ $r['tx']->status->value }}">{{ ['pending' => 'Pending', 'held' => 'On hold', 'rejected' => 'Declined', 'failed' => 'Failed', 'reversed' => 'Reversed', 'approved' => 'Approved'][$r['tx']->status->value] }}</span>
                        @endif
                        @if ($r['tx']->memo)<span class="sub">{{ $r['tx']->memo }}</span>@endif
                    </td>
                    @unless ($account)<td class="hide-s">{{ $r['account']->nickname }}<span class="sub">{{ $r['account']->masked() }}</span></td>@endunless
                    <td class="amt {{ $r['cents'] > 0 ? 'in' : '' }}">{{ $r['cents'] > 0 ? '+' : '-' }}@money(abs($r['cents']))</td>
                    <td class="bal-col hide-s">{{ $r['balance_after'] ? $r['balance_after']->format() : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="pager">
            <span class="fine" style="margin:0">Showing {{ $page->firstItem() }} to {{ $page->lastItem() }} of {{ $page->total() }} transactions</span>
            <span>
                @if ($page->previousPageUrl())<a class="plink" href="{{ $page->previousPageUrl() }}">&larr; Newer</a>@endif
                @if ($page->previousPageUrl() && $page->nextPageUrl()) &nbsp;|&nbsp; @endif
                @if ($page->nextPageUrl())<a class="plink" href="{{ $page->nextPageUrl() }}">Older &rarr;</a>@endif
            </span>
            <a class="plink" href="{{ $exportUrl }}">Download CSV</a>
        </div>
    @else
        <p style="padding:1.5rem 1rem;margin:0">No transactions match. Try a wider date range or clear the filters.</p>
    @endif
</div>
