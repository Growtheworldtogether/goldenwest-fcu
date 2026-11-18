@extends('layouts.inner')
@section('title', 'Rates | '.brand('name'))
@section('description', 'Current deposit and loan rates at '.brand('name').'.')
@section('crumb', 'Rates')
@section('heading', 'Today\'s rates')
@section('intro', 'Current rates on savings, certificates and loans. Rates as of '.now()->format('F j, Y').'.')

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>Savings and checking</h2>
        <div class="tblwrap"><table class="tbl">
            <thead><tr><th scope="col">Account</th><th scope="col">APY</th><th scope="col">Details</th><th scope="col">Minimum to open</th></tr></thead>
            <tbody>
            @foreach ($rates['deposit'] as $r)
                <tr><th scope="row" style="background:none;color:var(--navy)">{{ $r['name'] }}</th><td class="num" data-label="Rate">{{ $r['rate'] }}</td><td data-label="Details">{{ $r['detail'] }}</td><td data-label="Minimum">{{ $r['min'] }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="fine">APY means annual percentage yield. Rates may change at any time and fees could reduce earnings. Certificates carry a penalty for early withdrawal.</p>
    </div>
</section>
<section class="sec alt">
    <div class="wrap">
        <h2>Loans</h2>
        <div class="tblwrap"><table class="tbl">
            <thead><tr><th scope="col">Loan</th><th scope="col">APR as low as</th><th scope="col">Details</th><th scope="col">Minimum amount</th></tr></thead>
            <tbody>
            @foreach ($rates['loan'] as $r)
                <tr><th scope="row" style="background:none;color:var(--navy)">{{ $r['name'] }}</th><td class="num" data-label="Rate">{{ $r['rate'] }}</td><td data-label="Details">{{ $r['detail'] }}</td><td data-label="Minimum">{{ $r['min'] }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="fine">APR means annual percentage rate. Your rate depends on your credit history, the loan term and the amount you borrow. The lowest rates are for members with excellent credit. All loans are subject to approval.</p>
    </div>
</section>
@endsection

@section('cta')
<h2>Put your money to work</h2><p>Open a savings account or certificate in about ten minutes.</p>
@endsection
