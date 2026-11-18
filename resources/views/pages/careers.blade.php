@extends('layouts.inner')
@section('title', 'Careers | '.brand('name'))
@section('description', 'Build a career at '.brand('short_name').'. Meaningful work, strong benefits and room to grow.')
@section('crumb', 'Careers')
@section('heading', 'Build a career that matters')
@section('intro', 'Join a team of more than 2,400 people who help members reach their goals every day.')

@section('body')
<section class="sec">
    <div class="wrap">
        <h2>Why {{ brand('short_name') }}</h2>
        <div class="grid-4">
            <article class="tile"><h3>Health coverage</h3><p>Medical, dental and vision plans that start on your first day.</p></article>
            <article class="tile"><h3>Retirement</h3><p>A 401(k) with a generous company match.</p></article>
            <article class="tile"><h3>Time away</h3><p>Paid vacation, sick days, holidays and volunteer time.</p></article>
            <article class="tile"><h3>Growth</h3><p>Tuition support and clear paths from entry level to leadership.</p></article>
        </div>
    </div>
</section>
<section class="sec alt">
    <div class="wrap">
        <h2>Where you could work</h2>
        <div class="tblwrap"><table class="tbl">
            <thead><tr><th scope="col">Team</th><th scope="col">Roles</th><th scope="col">Locations</th></tr></thead>
            <tbody>
                <tr><td data-label="Team">Branch banking</td><td data-label="Roles">Member service representative, branch manager, lending officer</td><td data-label="Locations">California, Nevada, Arizona, Oregon, Washington</td></tr>
                <tr><td data-label="Team">Contact center</td><td data-label="Roles">Member care specialist, fraud analyst</td><td data-label="Locations">Oakland, CA and remote</td></tr>
                <tr><td data-label="Team">Technology</td><td data-label="Roles">Software engineer, security analyst, data analyst</td><td data-label="Locations">Oakland, CA and remote</td></tr>
                <tr><td data-label="Team">Lending and operations</td><td data-label="Roles">Underwriter, loan processor, payments specialist</td><td data-label="Locations">Oakland, CA and Phoenix, AZ</td></tr>
            </tbody>
        </table></div>
        <p class="fine">{{ brand('short_name') }} is an equal opportunity employer. We do not discriminate on the basis of race, color, religion, sex, national origin, age, disability, veteran status or any other protected characteristic.</p>
    </div>
</section>
@endsection

@section('cta')
<h2>Ready to apply?</h2><p>Tell us about yourself and our recruiting team will be in touch.</p>
@endsection
@section('cta-url', url('/contact'))
@section('cta-label', 'Contact recruiting')
