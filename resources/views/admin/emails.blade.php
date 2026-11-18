@extends('layouts.portal')
@section('title', 'Emails')
@section('band')
<h1>Emails</h1>
<p class="psub">How the emails members receive look right now. They use the name, logo, colours and contact details from Branding.</p>
@endsection
@section('content')
<div class="panel-card flush">
    <div class="grouphead"><h2>Emails</h2><span>Sent from <b>{{ config('mail.from.name') }} &lt;{{ $from }}&gt;</b></span></div>
    <table class="dtable stack">
        <tbody>
        @foreach ($samples as $key => [$title, $note])
            <tr><td><strong>{{ $title }}</strong><small>{{ $note }}</small></td><td class="go"><a class="main" href="{{ route('admin.emails.show', $key) }}" target="_blank" rel="noopener">Preview</a></td></tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="panel-card" style="margin-top:1.25rem">
    <h3>Send a test email</h3>
    <p class="fine" style="margin-top:0">Sends a short email to {{ auth()->user()->email }} so you can check delivery.@if ($mailer === 'log') Email is set to <b>log</b> right now, so nothing leaves the server.@endif</p>
    <form method="post" action="{{ route('admin.emails.test') }}">@csrf<button class="btn-s" type="submit">Send test email</button></form>
</div>
@endsection
