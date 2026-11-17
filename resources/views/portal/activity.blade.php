@extends('layouts.portal')
@section('title', 'Activity')
@section('band')
<h1>Activity</h1>
<p class="psub">Every transaction across your accounts.</p>
@endsection
@section('content')
@include('portal._history', ['exportUrl' => route('activity.export', request()->query())])
@endsection
