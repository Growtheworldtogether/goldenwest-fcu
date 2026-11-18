@php($p = \App\Support\MailBrand::palette())
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center" style="color:#5b6763;font-size:13px;line-height:1.5;">
<p style="margin:0 0 .6em;color:#5b6763;font-size:13px;text-align:center;"><strong>{{ $p['name'] }}</strong></p>
<p style="margin:0 0 .6em;color:#5b6763;font-size:13px;text-align:center;">Questions? Call <a href="tel:{{ $p['tel'] }}" style="color:{{ $p['link'] }};">{{ $p['phone'] }}</a> ({{ $p['hours'] }}) or write to <a href="mailto:{{ $p['email'] }}" style="color:{{ $p['link'] }};">{{ $p['email'] }}</a>.</p>
<p style="margin:0 0 .6em;color:#5b6763;font-size:13px;text-align:center;">{{ $p['warning'] }}</p>
@if ($p['footer_text'])
<p style="margin:0 0 .6em;color:#5b6763;font-size:13px;text-align:center;">{{ $p['footer_text'] }}</p>
@endif
<p style="margin:0;color:#8a9692;font-size:12px;text-align:center;">&copy; {{ date('Y') }} {{ $p['name'] }}</p>
</td>
</tr>
</table>
</td>
</tr>
