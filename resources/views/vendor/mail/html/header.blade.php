@props(['url'])
@php($p = \App\Support\MailBrand::palette())
<tr>
<td class="header" style="background-color:{{ $p['header_bg'] }};padding:22px 0;text-align:center;">
<a href="{{ $url }}" style="display:inline-block;text-decoration:none;color:{{ $p['header_text'] }};font-size:20px;font-weight:bold;">
@if ($p['logo'])
<img src="{{ $p['logo'] }}" alt="{{ $p['name'] }}" height="44" style="height:44px;max-width:260px;width:auto;border:0;display:block;margin:0 auto;">
@else
{{ $p['name'] }}
@endif
</a>
</td>
</tr>
