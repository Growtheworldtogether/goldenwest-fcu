@extends('layouts.inner')
@section('title', 'Sitemap | Golden West Federal Credit Union')
@section('crumb', 'Sitemap')
@section('heading', 'Sitemap')
@section('intro', 'Every page on our site in one place.')

@section('body')
<section class="sec">
    <div class="wrap sitemap">
        @foreach ($groups as $group => $pages)
            <h3>{{ $group }}</h3>
            <ul>@foreach ($pages as $p)<li><a href="{{ url($p[2]) }}">{{ $p[1] }}</a></li>@endforeach</ul>
        @endforeach
    </div>
</section>
@endsection
