@extends('layouts.site')

@section('content')
<section class="sec alt" style="min-height:60vh">
    <div class="wrap">
        <div class="authbox @yield('box-class')">
            <h2>@yield('heading')</h2>
            @hasSection('lede')<p class="fine">@yield('lede')</p>@endif
            @yield('form')
        </div>
    </div>
</section>
@endsection
