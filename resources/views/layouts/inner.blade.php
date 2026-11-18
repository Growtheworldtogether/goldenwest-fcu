@extends('layouts.site')

@section('content')
    @php($cp = site_page())
    @php($heroPhoto = page_photo($cp))
    <section class="phero on-dark {{ $heroPhoto ? 'has-photo' : '' }}">
        @if ($heroPhoto)<div class="phero-photo photo" data-img="{{ $heroPhoto }}" role="presentation" aria-hidden="true"></div>@endif
        <div class="phero-in">
            <ol class="crumbs" aria-label="Breadcrumb">
                <li><a href="{{ url('/') }}">Home</a></li>
                @yield('crumbs')
                <li aria-current="page">@yield('crumb')</li>
            </ol>
            <h1>{{ site_content($cp.'.heading', trim($__env->yieldContent('heading'))) }}</h1>
            <p class="intro">{{ site_content($cp.'.intro', trim($__env->yieldContent('intro'))) }}</p>
            @yield('hero-action')
        </div>
    </section>
    @yield('body')
    @hasSection('cta')
        <section class="cta on-dark">
            <div class="wrap">
                @php($ctaButton = site_content($cp.'.cta_button'))
                @if (site_content($cp.'.cta_title'))
                    <div><h2>{{ site_content($cp.'.cta_title') }}</h2>@if (site_content($cp.'.cta_text'))<p>{{ site_content($cp.'.cta_text') }}</p>@endif</div>
                @else
                    <div>@yield('cta')</div>
                @endif
                <a class="btn" href="{{ $ctaButton ? url($ctaButton['url']) : trim($__env->yieldContent('cta-url', url('/join'))) }}">{{ $ctaButton['label'] ?? trim($__env->yieldContent('cta-label', 'Open an account')) }}</a>
            </div>
        </section>
    @endif
@endsection
