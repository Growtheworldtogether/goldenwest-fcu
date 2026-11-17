@extends('layouts.inner')
@section('title', 'Accessibility | '.brand('name'))
@section('crumb', 'Accessibility')
@section('heading', 'Accessibility')
@section('intro', 'We want everyone to be able to bank with us.')

@section('body')
<section class="sec"><div class="wrap"><div class="prose">
@if (site_content('accessibility.updated'))<p class="fine">Last updated {{ site_content('accessibility.updated') }}</p>@endif
{!! site_content('accessibility.body') !!}
</div></div></section>
@endsection
