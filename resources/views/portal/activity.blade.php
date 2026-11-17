@extends('layouts.portal')
@section('title', 'Activity')
@section('content')
<h1>Activity</h1>
<p class="psub">Every transaction across your accounts.</p>
@include('portal._history', ['exportUrl' => route('activity.export', request()->query())])
@endsection
