@extends('layouts.site')

@section('content')
    <section class="phero on-dark">
        <div class="phero-in">
            <ol class="crumbs" aria-label="Breadcrumb">
                <li><a href="{{ url('/') }}">Home</a></li>
                @yield('crumbs')
                <li aria-current="page">@yield('crumb')</li>
            </ol>
            <h1>@yield('heading')</h1>
            <p class="intro">@yield('intro')</p>
            @yield('hero-action')
        </div>
    </section>
    @yield('body')
    @hasSection('cta')
        <section class="cta on-dark">
            <div class="wrap">
                <div>@yield('cta')</div>
                <a class="btn" href="@yield('cta-url', url('/join'))">@yield('cta-label', 'Open an account')</a>
            </div>
        </section>
    @endif
@endsection
