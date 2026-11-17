@extends('layouts.portal')
@section('title', $def['label'].' content')
@section('band')
<p class="crumbs2"><a href="{{ route('admin.site.index') }}">Site content</a> &rsaquo; {{ $def['label'] }}</p>
<div class="pband-row">
    <div><h1>{{ $def['label'] }}</h1>
    <p class="psub">
        @if ($row && ! empty($row->draft))<span class="status-chip draft">Unpublished changes</span> Saved {{ $row->draft_at?->diffForHumans() }}.
        @elseif ($row && $row->version > 0)<span class="status-chip live">Published</span> Version {{ $row->version }}, {{ $row->published_at?->format('M j, Y g:i a') }}.
        @else<span class="status-chip">Original text</span> Nothing has been edited yet.@endif
    </p></div>
    <a class="btn-s plain" href="{{ url($def['url']) }}" target="_blank" rel="noopener">View live page</a>
</div>
@endsection
@section('content')
@php($values = $values ?? old('fields', []))
<div class="editor">
    <div class="col-form">
        <form method="post" action="{{ route('admin.site.draft', $page) }}" enctype="multipart/form-data" class="panel-card" id="contentform">
            @csrf @method('PUT')
            @if ($errors->any())<p class="err" role="alert" style="margin:0 0 1rem">{{ $errors->first() }}</p>@endif
            <p class="fine" style="margin:0 0 1rem">Words in braces, such as <code>{name}</code>, <code>{short_name}</code>, <code>{phone}</code>, <code>{email}</code> and <code>{hours}</code>, fill in from the <a class="plink" href="{{ route('admin.branding') }}">Branding page</a>. Leave them as they are.</p>
            <div class="fields">
                @foreach ($def['fields'] as $key => $f)
                    @include('admin._field', ['f' => $f, 'name' => 'fields['.$key.']', 'fileName' => 'fields['.$key.'][file]', 'value' => $values[$key] ?? \App\Services\SiteContent::defaultFor($f), 'media' => $media, 'id' => $key])
                @endforeach
            </div>
            <div class="formbar" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
                <button class="btn-s" type="submit" name="then" value="draft">Save draft and refresh preview</button>
                <button class="btn-s gold" type="submit" name="then" value="publish">Save and publish</button>
            </div>
        </form>

        @if ($row && ! empty($row->draft))
        <div class="panel-card">
            <h2>Draft</h2>
            <p class="fine" style="margin-top:0">The preview on the right shows the saved draft. Visitors still see the published version.</p>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                <form method="post" action="{{ route('admin.site.publish', $page) }}">@csrf<button class="btn-s" type="submit">Publish the saved draft</button></form>
                <form method="post" action="{{ route('admin.site.discard', $page) }}">@csrf @method('DELETE')<button class="btn-s plain" type="submit">Discard draft</button></form>
            </div>
        </div>
        @endif

        <div class="panel-card">
            <h2>Versions</h2>
            @if ($revisions->count())
            <ul class="revlist">
                @foreach ($revisions as $rev)
                <li><span>Version {{ $rev->version }}<small>{{ $rev->created_at->format('M j, Y g:i a') }} &middot; {{ $rev->note }}</small></span>
                    @if ($row && $rev->version !== $row->version)<form method="post" action="{{ route('admin.site.rollback', [$page, $rev]) }}">@csrf<button class="btn-s plain sm" type="submit">Restore</button></form>@else<span class="status-chip live">Live</span>@endif</li>
                @endforeach
            </ul>
            @else<p class="fine" style="margin:0">No versions yet. The first publish creates one.</p>@endif
            <form method="post" action="{{ route('admin.site.reset', $page) }}" style="margin-top:1rem">@csrf<button class="linkbtn" type="submit">Reset this page to its original text</button></form>
        </div>
    </div>
    <div class="previewbox">
        <iframe title="Preview of {{ $def['label'] }} with the saved draft" src="{{ route('admin.site.preview', $page) }}"></iframe>
    </div>
</div>
@push('scripts')
<script>
(function () {
    // Rich text: a small toolbar over a contenteditable area; the cleaned HTML goes into the hidden textarea on submit.
    document.querySelectorAll('[data-rte]').forEach(function (box) {
        var bar = box.querySelector('.rte-bar'), body = box.querySelector('.rte-body'), ta = box.querySelector('textarea');
        var tools = [['B', 'bold', 'Bold'], ['I', 'italic', 'Italic'], ['• List', 'insertUnorderedList', 'Bulleted list'], ['1. List', 'insertOrderedList', 'Numbered list'], ['Link', 'link', 'Add a link'], ['Clear', 'removeFormat', 'Remove formatting']];
        if (box.hasAttribute('data-headings')) { tools.splice(2, 0, ['H2', 'h2', 'Heading']); tools.splice(3, 0, ['P', 'p', 'Paragraph']); }
        tools.forEach(function (t) {
            var b = document.createElement('button'); b.type = 'button'; b.textContent = t[0]; b.title = t[2]; b.setAttribute('aria-label', t[2]);
            b.addEventListener('mousedown', function (e) { e.preventDefault(); });
            b.addEventListener('click', function () {
                body.focus();
                if (t[1] === 'link') { var u = prompt('Link address (https://, mailto:, tel: or /page)'); if (u) { document.execCommand('createLink', false, u); } }
                else if (t[1] === 'h2' || t[1] === 'p') { document.execCommand('formatBlock', false, t[1]); }
                else { document.execCommand(t[1], false, null); }
            });
            bar.appendChild(b);
        });
        body.addEventListener('paste', function (e) { e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain')); });
        box.closest('form').addEventListener('submit', function () { ta.value = body.innerHTML; });
    });
    // Repeatable lists
    document.querySelectorAll('[data-list]').forEach(function (list) {
        var rows = list.querySelector('[data-rows]'), tpl = list.querySelector('[data-template]'), add = list.querySelector('[data-add]');
        var max = +list.dataset.max, min = +list.dataset.min;
        function renumber() {
            var all = rows.querySelectorAll('[data-row]');
            all.forEach(function (r, i) {
                r.querySelector('[data-n]').textContent = i + 1;
                r.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/\[(\d+|__i__)\]/, '[' + i + ']'); });
                r.querySelector('[data-remove]').hidden = all.length <= min;
            });
            add.hidden = all.length >= max;
        }
        add.addEventListener('click', function () {
            var holder = document.createElement('div'); holder.innerHTML = tpl.innerHTML; rows.appendChild(holder.firstElementChild);
            rows.querySelectorAll('[data-rte]').length; initNew(rows.lastElementChild); renumber();
        });
        list.addEventListener('click', function (e) { if (e.target.matches('[data-remove]')) { e.target.closest('[data-row]').remove(); renumber(); } });
        function initNew(row) { /* rich text is not used inside lists */ }
        renumber();
    });
})();
</script>
@endpush
@endsection
