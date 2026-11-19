@extends($forced ? 'layouts.auth' : 'layouts.portal')
@section('title', 'Security questions | '.brand('name'))
@if ($forced)
@section('heading', 'Set your security questions')
@section('lede', 'We will ask one of these each time you sign in. Pick '.$need.' questions only you can answer.')
@else
@section('band')
<h1>Security questions</h1>
<p class="psub">Choose {{ $need }} questions only you can answer. Saving new ones replaces the old ones.</p>
@endsection
@endif
@section($forced ? 'form' : 'content')
<form class="form" method="post" action="{{ $forced ? route('security.questions.setup.save') : route('security.questions.update') }}" autocomplete="off">
    @csrf
    @error('questions')<span class="err">{{ $message }}</span>@enderror
    @for ($i = 1; $i <= $need; $i++)
    <label>Question {{ $i }}
        <select name="question_{{ $i }}" required>
            <option value="">Choose a question</option>
            @foreach ($questions as $key => $text)
            <option value="{{ $key }}" @selected(old('question_'.$i) === $key)>{{ $text }}</option>
            @endforeach
        </select>
        @error('question_'.$i)<span class="err">{{ $message }}</span>@enderror
    </label>
    <label>Your answer<input name="answer_{{ $i }}" type="password" autocomplete="off" required>@error('answer_'.$i)<span class="err">{{ $message }}</span>@enderror</label>
    @endfor
    <p class="fine">Answers are not case sensitive, and punctuation and spaces are ignored. Pick answers you will remember and others cannot find online.</p>
    <button class="btn" type="submit">Save my questions</button>
</form>
@if ($forced)
<form method="post" action="{{ route('logout') }}" style="margin-top:.5rem">@csrf<button class="linkbtn" type="submit">Sign out</button></form>
@endif
@endsection
