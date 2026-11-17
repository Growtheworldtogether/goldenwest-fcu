@extends('layouts.site')

@section('content')
<section class="authsplit @yield('box-class')">
    <div class="authart photo" data-img="{{ asset('images/'.trim($__env->yieldContent('art', 'branch.jpg'))) }}" aria-hidden="true">
        <div class="authart-copy">
            <p class="caps">@yield('art-title', 'Welcome back')</p>
            <p>@yield('art-text', 'Your accounts, cards and goals are right where you left them.')</p>
        </div>
    </div>
    <div class="authside">
        <div class="authbox @yield('box-class')">
            <h2>@yield('heading')</h2>
            @hasSection('lede')<p class="fine">@yield('lede')</p>@endif
            @yield('form')
        </div>
    </div>
</section>
@endsection
