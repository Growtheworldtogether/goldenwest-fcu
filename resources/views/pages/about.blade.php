@extends('layouts.inner')
@section('title', 'About '.brand('short_name').' | '.brand('name'))
@section('description', brand('short_name').' has been owned by its members since 1958. Learn about our history, our values and the communities we serve.')
@section('crumb', 'About')
@section('heading', 'Owned by the people we serve')
@section('intro', 'Since 1958, '.brand('short_name').' has put members first. We are a not-for-profit cooperative, so our earnings return to you.')

@section('body')
<section class="sec">
    <div class="wrap split">
        <div>
            <h2>Our story</h2>
            {!! site_content('about.story') !!}
        </div>
        <aside class="aside on-dark"><h3>By the numbers</h3><p><strong style="font-size:1.6rem;font-family:var(--display)">1958</strong><br>Year we were founded</p><p><strong style="font-size:1.6rem;font-family:var(--display)">280,000+</strong><br>Members</p><p><strong style="font-size:1.6rem;font-family:var(--display)">46</strong><br>Branches in five states</p></aside>
    </div>
</section>
<section class="sec alt">
    <div class="wrap">
        <h2>What we stand for</h2>
        <div class="grid-3">
            <article class="tile"><h3>Members first</h3><p>Every product and policy is judged by one question: does it help our members?</p></article>
            <article class="tile"><h3>Plain language</h3><p>Clear terms, honest fees and decisions explained in words you can understand.</p></article>
            <article class="tile"><h3>Local roots</h3><p>Our people live where you live, and we invest in the neighborhoods we serve.</p></article>
        </div>
    </div>
</section>
<section class="sec">
    <div class="wrap">
        <h2>Leadership</h2>
        <div class="grid-4">
            <article class="tile"><h3>Elena Marquez</h3><p>President and Chief Executive Officer</p></article>
            <article class="tile"><h3>David Okafor</h3><p>Chief Financial Officer</p></article>
            <article class="tile"><h3>Priya Raman</h3><p>Chief Information Security Officer</p></article>
            <article class="tile"><h3>Thomas Whitfield</h3><p>Chief Member Experience Officer</p></article>
        </div>
    </div>
</section>
@endsection

@section('cta')
<h2>Become a member</h2><p>Joining is free and takes about ten minutes.</p>
@endsection
