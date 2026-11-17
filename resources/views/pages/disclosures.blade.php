@extends('layouts.inner')
@section('title', 'Disclosures | '.brand('name'))
@section('crumb', 'Disclosures')
@section('heading', 'Disclosures')
@section('intro', 'Important information about fees, funds availability and rates.')

@section('body')
<section class="sec"><div class="wrap"><div class="prose">
@if (site_content('disclosures.updated'))<p class="fine">Last updated {{ site_content('disclosures.updated') }}</p>@endif
{!! site_content('disclosures.body') !!}
</div></div></section>
@endsection
