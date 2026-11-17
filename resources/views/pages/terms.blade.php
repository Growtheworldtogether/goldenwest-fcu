@extends('layouts.inner')
@section('title', 'Terms of Use | '.brand('name'))
@section('crumb', 'Terms of Use')
@section('heading', 'Terms of Use')
@section('intro', 'The terms that apply when you use our website and digital banking.')

@section('body')
<section class="sec"><div class="wrap"><div class="prose">
@if (site_content('terms.updated'))<p class="fine">Last updated {{ site_content('terms.updated') }}</p>@endif
{!! site_content('terms.body') !!}
</div></div></section>
@endsection
