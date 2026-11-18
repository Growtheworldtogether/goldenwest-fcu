@extends('layouts.inner')
@section('title', 'Business Banking | '.brand('name'))
@section('description', 'Business checking, lending and cash management for small and mid-sized companies across the West.')
@section('crumb', 'Business')
@section('heading', 'Business banking for the West')
@section('intro', 'Checking, lending and cash management built for owners who would rather run a company than manage a bank.')
@section('hero-action')<a class="btn" href="{{ url('/appointments') }}">Talk to a business banker</a>@endsection

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>Everything your business needs</h2>
        <div class="grid-3">
            <article class="tile"><h3>Business checking</h3><p>Simple accounts with free incoming wires, remote check deposit and a business debit card for every authorized user.</p><ul class="checks"><li>No monthly fee on Business Basic</li><li>Same-day ACH available</li><li>Separate user permissions</li></ul></article>
            <article class="tile"><h3>Business lending</h3><p>Term loans, lines of credit and equipment financing, reviewed by bankers who live in your community.</p><ul class="checks"><li>Loans from $10,000</li><li>Terms up to 84 months</li><li>Local decisions</li></ul></article>
            <article class="tile"><h3>Cash management</h3><p>Pay vendors, collect from customers and see balances across accounts from one dashboard.</p><ul class="checks"><li>ACH and wire origination</li><li>Positive pay</li><li>Payroll and tax payments</li></ul></article>
        </div>
    </div>
</section>
<section class="sec alt">
    <div class="wrap split">
        <div>
            <h2>A banker who knows your name</h2>
            <p>Every business client has a dedicated relationship banker and a direct phone line. Whether you are opening your first account or financing your third location, you work with the same person.</p>
            <p>Our teams serve businesses in California, Nevada, Arizona, Oregon and Washington, from sole proprietors to companies with hundreds of employees.</p>
        </div>
        <div class="splitcol">@include('partials.bodyphoto', ['name' => 'business-banker'])<aside class="aside on-dark"><h3>Business Customer Care</h3><p>Call <a href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a> and choose the business option. Monday through Friday, 7am to 8pm PT.</p><a class="btn" href="{{ url('/contact') }}">Send a message</a></aside></div>
    </div>
</section>
@endsection

@section('cta')
<h2>Let us look at your numbers</h2><p>Schedule a no-obligation conversation with a business banker.</p>
@endsection
@section('cta-url', url('/appointments'))
@section('cta-label', 'Schedule a meeting')
