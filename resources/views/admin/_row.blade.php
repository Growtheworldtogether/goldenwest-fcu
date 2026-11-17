<div class="rowbox" data-row>
    <div class="rowhead"><span>{{ ucfirst($f['label']) }} <span data-n></span></span><button type="button" class="linkbtn" data-remove>Remove</button></div>
    @foreach ($f['fields'] as $k => $sub)
        @include('admin._field', ['f' => $sub, 'name' => $name.'['.$index.']['.$k.']', 'fileName' => $name.'['.$index.']['.$k.'][file]', 'value' => $row[$k] ?? null, 'media' => $media, 'id' => $id.'-'.$index.'-'.$k])
    @endforeach
</div>
