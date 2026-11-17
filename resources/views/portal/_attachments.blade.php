@if ($message->attachments->count())
    <p class="tfiles">@foreach ($message->attachments as $a)<a class="plink" href="{{ route('files.ticket', $a) }}" target="_blank" rel="noopener">{{ $a->name }}</a>@unless ($loop->last) &middot; @endunless @endforeach</p>
@endif
