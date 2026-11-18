@php($p = \App\Support\MailBrand::palette())
{{ $p['name'] }}
Questions? Call {{ $p['phone'] }} ({{ $p['hours'] }}) or write to {{ $p['email'] }}.
{{ $p['warning'] }}
@if ($p['footer_text'])
{{ $p['footer_text'] }}
@endif
