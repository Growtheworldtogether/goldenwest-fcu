@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php($p = \App\Support\MailBrand::palette())
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="background-color:{{ $p['highlight'] }};border-radius:4px;">
<a href="{{ $url }}" class="button button-primary" target="_blank" rel="noopener" style="display:inline-block;background-color:{{ $p['highlight'] }};border:12px solid {{ $p['highlight'] }};border-left-width:26px;border-right-width:26px;border-radius:4px;color:{{ $p['on_highlight'] }};font-size:16px;font-weight:bold;text-decoration:none;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
