@extends('layouts.inner')
@section('title', 'Insights & Stories | Golden West Federal Credit Union')
@section('description', 'Practical guides on saving, borrowing and staying safe from Golden West.')
@section('crumb', 'Insights & Stories')
@section('heading', 'Insights & stories')
@section('intro', 'Practical guidance on money, loans and staying safe, written by the people who help our members every day.')

@section('body')
<section class="sec">
    <div class="wrap">
        <div class="grid-3">
            @foreach ($articles as $a)
                <a class="tile" href="{{ route('learn.show', $a['slug']) }}" style="text-decoration:none;color:inherit">
                    <span class="chip" style="align-self:flex-start;font-size:.8125rem;font-weight:700;color:var(--blue);text-transform:uppercase;letter-spacing:.05em">{{ $a['cat'] }}</span>
                    <h3>{{ $a['title'] }}</h3>
                    <p>{{ $a['intro'] }}</p>
                    <span class="more">Read article</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endsection
