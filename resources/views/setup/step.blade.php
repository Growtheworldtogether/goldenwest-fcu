<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Set up your credit union site</title>
    <link rel="stylesheet" href="{{ asset('css/setup.css') }}">
</head>
<body>
<div class="top"><b>Site setup</b><span>One time only. Takes about five minutes.</span></div>
<div class="wrap">
    <ol class="steps" aria-label="Setup steps">
        @foreach ($steps as $id => $label)
            <li class="{{ $id === $step ? 'on' : (! empty($done[$id]) ? 'ok' : '') }}">{{ $label }}</li>
        @endforeach
    </ol>
    <main class="card">
        @if ($errors->has('install') || $errors->has('setup'))
            <div class="alert" role="alert">{{ $errors->first('install') ?: $errors->first('setup') }}</div>
        @endif
        <form method="post" action="{{ route('setup.save', $step) }}" autocomplete="off">
            @csrf
            @include('setup.steps.'.$step)
        </form>
    </main>
</div>
</body>
</html>
