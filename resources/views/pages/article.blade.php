@extends('layouts.inner')
@section('title', $article['title'].' | Golden West Federal Credit Union')
@section('description', $article['intro'])
@section('crumbs')<li><a href="{{ url('/learn') }}">Insights &amp; Stories</a></li>@endsection
@section('crumb', $article['cat'])
@section('heading', $article['title'])
@section('intro', $article['intro'])
@section('hero-action')<div class="article-meta"><span>{{ $article['cat'] }}</span><time datetime="{{ $article['date'] }}">{{ \Illuminate\Support\Carbon::parse($article['date'])->format('F j, Y') }}</time></div>@endsection

@section('body')
<section class="sec">
    <div class="wrap">
        <div class="article-body">
            @foreach ($article['body'] as $block)
                @if ($block[0] === 'h')<h2>{{ $block[1] }}</h2>
                @elseif ($block[0] === 'ul')<ul class="checks">@foreach ($block[1] as $li)<li>{{ $li }}</li>@endforeach</ul>
                @else<p>{{ $block[1] }}</p>@endif
            @endforeach
        </div>
    </div>
</section>
<section class="sec alt">
    <div class="wrap">
        <h2>Keep reading</h2>
        <div class="grid-3">
            @foreach ($more as $a)
                <a class="tile" href="{{ route('learn.show', $a['slug']) }}" style="text-decoration:none;color:inherit"><h3>{{ $a['title'] }}</h3><p>{{ $a['cat'] }}</p></a>
            @endforeach
        </div>
    </div>
</section>
@endsection
