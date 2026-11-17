{{-- Search, filters and transaction table shared by the account and activity pages. Expects $rows, $page, $groups, $filters, $accounts, $account (nullable), $exportUrl --}}
@php($labels = ['pending' => 'Pending', 'held' => 'On hold', 'rejected' => 'Declined', 'failed' => 'Failed', 'reversed' => 'Reversed', 'approved' => 'Approved'])
@php([$pendingRows, $postedRows] = collect($rows)->partition(fn ($r) => in_array($r['tx']->status->value, ['pending', 'held'])))
<div class="panel-card flush">
    <form class="searchform" method="get">
    <div class="searchbar">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by merchant, memo or amount" aria-label="Search transactions">
        <button class="btn-s" type="submit">Search</button>
        <a class="plink" href="{{ $exportUrl }}">Download transactions</a>
    </div>
    <div class="morewrap">
        <details class="more" @if (collect($filters)->except(['q', 'page'])->filter()->isNotEmpty()) open @endif>
            <summary>More filters</summary>
            <div class="filters">
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
            </div>
            <div class="filter-actions"><button class="btn-s sm" type="submit">Apply filters</button><a class="plink" href="{{ url()->current() }}">Clear all</a></div>
        </details>
    </div>
    </form>

    @if (count($rows))
    <div style="overflow-x:auto">
    <table class="dtable">
        <thead><tr><th>Date</th><th>Description</th>@unless ($account)<th class="hide-s">Account</th>@endunless<th class="num">Amount</th><th class="num hide-s">Balance</th></tr></thead>
        @foreach ([['Pending transactions', $pendingRows], ['Posted transactions', $postedRows]] as [$title, $set])
            @if ($set->count())
            <tbody>
                @if ($pendingRows->count() && $postedRows->count())<tr class="grp"><th colspan="{{ $account ? 4 : 5 }}">{{ $title }}</th></tr>@endif
                @foreach ($set as $r)
                <tr>
                    <td style="white-space:nowrap">{{ $r['tx']->effective_date->format('M j, Y') }}</td>
                    <td>
                        <a class="main" href="{{ route('transactions.show', $r['tx']->reference) }}">{{ $r['title'] }}</a>
                        @if ($r['tx']->status->value !== 'completed')<span class="chip {{ $r['tx']->status->value }}">{{ $labels[$r['tx']->status->value] ?? '' }}</span>@endif
                        @if ($r['tx']->memberMemo())<small>{{ $r['tx']->memberMemo() }}</small>@endif
                    </td>
                    @unless ($account)<td class="hide-s">{{ $r['account']->nickname }}<small>{{ $r['account']->masked() }}</small></td>@endunless
                    <td class="num {{ $r['cents'] > 0 ? 'in' : '' }}">{{ $r['cents'] > 0 ? '+' : '-' }}@money(abs($r['cents']))</td>
                    <td class="num hide-s" style="font-weight:400;color:var(--muted)">{{ $r['balance_after'] ? $r['balance_after']->format() : '' }}</td>
                </tr>
                @endforeach
            </tbody>
            @endif
        @endforeach
    </table>
    </div>
    <div class="pager" style="padding:.9rem 1.25rem;margin:0;border-top:1px solid #e7ebf6">
        <span class="fine" style="margin:0">Showing {{ $page->firstItem() }} to {{ $page->lastItem() }} of {{ $page->total() }} transactions</span>
        <span>
            @if ($page->previousPageUrl())<a class="plink" href="{{ $page->previousPageUrl() }}">&larr; Newer</a>@endif
            @if ($page->previousPageUrl() && $page->nextPageUrl()) &nbsp;|&nbsp; @endif
            @if ($page->nextPageUrl())<a class="plink" href="{{ $page->nextPageUrl() }}">Older &rarr;</a>@endif
        </span>
    </div>
    @else
        <div class="empty"><b>No transactions match</b>Try a wider date range or clear the filters.</div>
    @endif
</div>
