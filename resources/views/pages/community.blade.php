@extends('layouts.inner')
@section('title', 'Community | '.brand('name'))
@section('description', 'How '.brand('short_name').' invests in the communities it serves through lending, scholarships, volunteering and financial education.')
@section('crumb', 'Community')
@section('heading', 'Investing where we live')
@section('intro', 'Strong communities make strong members. Here is how '.brand('short_name').' gives back across the West.')

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>How we give back</h2>
        <div class="grid-3">
            <article class="tile"><h3>Community lending</h3><p>We offer affordable loans to first-time homebuyers, small businesses and nonprofits in neighborhoods that traditional lenders overlook.</p></article>
            <article class="tile"><h3>Financial education</h3><p>Free workshops on budgeting, credit and saving, held at branches, schools and community centers throughout the year.</p></article>
            <article class="tile"><h3>Volunteer time</h3><p>Every employee receives paid time each year to volunteer with local organizations.</p></article>
        </div>
    </div>
</section>
<section class="sec alt">
    <div class="wrap split">
        <div>
            <h2>Member scholarships</h2>
            <p>Each spring we award scholarships to members who are headed to college or trade school. Applications open in January, and awards are based on service, leadership and financial need.</p>
            <a class="btn" href="{{ url('/contact') }}">Ask about scholarships</a>
        </div>
        <aside class="aside on-dark"><h3>Community Reinvestment</h3><p>{{ brand('short_name') }} is committed to meeting the credit needs of the communities we serve, including low- and moderate-income neighborhoods. Ask any branch for our current Community Reinvestment statement.</p></aside>
    </div>
</section>
@endsection
