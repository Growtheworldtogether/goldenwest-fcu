@extends('layouts.inner')
@section('title', 'Privacy | '.brand('name'))
@section('crumb', 'Privacy')
@section('heading', 'Privacy')
@section('intro', 'How we collect, use and protect your personal information.')

@section('body')
<section class="sec"><div class="wrap"><div class="prose">
@if (site_content('privacy.updated'))<p class="fine">Last updated {{ site_content('privacy.updated') }}</p>@endif
{!! site_content('privacy.body') !!}
</div></div></section>
@endsection
