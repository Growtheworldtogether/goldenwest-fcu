@extends('layouts.inner')
@section('title', 'Search | '.brand('name'))
@section('crumb', 'Search')
@section('heading', 'Search')
@section('intro', 'Find accounts, loans, help topics and articles.')

@section('body')
<section class="sec">
    <div class="wrap">
        <form class="form" method="get" action="{{ url('/search') }}" role="search">
            <div class="row" style="grid-template-columns:1fr auto;align-items:end">
                <label>Search {{ brand('short_name') }}<input type="search" name="q" value="{{ $q }}" autofocus></label>
                <button class="btn" type="submit">Search</button>
            </div>
        </form>
        @if ($q !== '')
            <h2 style="margin-top:2rem">{{ $results->count() }} {{ \Illuminate\Support\Str::plural('result', $results->count()) }} for &ldquo;{{ $q }}&rdquo;</h2>
            @forelse ($results as $r)
                <p><a href="{{ url($r['url']) }}" style="color:var(--blue);font-weight:700;font-size:1.125rem">{{ $r['title'] }}</a><br><span class="fine">{{ $r['area'] }}</span></p>
            @empty
                <p>We could not find a match. Try a different word, or call Customer Care at <a href="tel:{{ brand('tel') }}">{{ brand('support_phone') }}</a>.</p>
            @endforelse
        @endif
    </div>
</section>
@endsection
