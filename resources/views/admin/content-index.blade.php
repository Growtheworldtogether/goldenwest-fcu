@extends('layouts.portal')
@section('title', 'Site content')
@section('band')
<h1>Site content</h1>
<p class="psub">Edit the text and images of the public pages. Visitors see only what you publish.</p>
@endsection
@section('content')
<div class="panel-card flush">
    <table class="dtable">
        <thead><tr><th>Page</th><th>Status</th><th class="hide-s">Last published</th><th></th></tr></thead>
        <tbody>
        @foreach ($pages as $key => $def)
            @php($row = $rows[$key] ?? null)
            <tr>
                <td><strong>{{ $def['label'] }}</strong><small>{{ $def['url'] }}</small></td>
                <td>
                    @if ($row && ! empty($row->draft))<span class="status-chip draft">Unpublished changes</span>
                    @elseif ($row && $row->version > 0)<span class="status-chip live">Edited, version {{ $row->version }}</span>
                    @else<span class="status-chip">Original text</span>@endif
                </td>
                <td class="hide-s">{{ $row?->published_at?->format('M j, Y g:i a') ?? '-' }}</td>
                <td class="go"><a class="plink" href="{{ route('admin.site.edit', $key) }}">Edit</a> &nbsp; <a class="plink" href="{{ url($def['url']) }}" target="_blank" rel="noopener">View page</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="fine" style="margin-top:1rem">Rates, minimums and APRs are not edited here. Text such as the name, phone number and hours follows the <a class="plink" href="{{ route('admin.branding') }}">Branding page</a>.</p>
@endsection
