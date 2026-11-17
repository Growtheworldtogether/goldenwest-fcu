{{-- One editable field. $f = field definition, $name = input name, $value = current value, $media = media lookup, $id = unique id --}}
@php($label = $f['label'])
@switch($f['type'])
    @case('text')
        <label>{{ ucfirst($label) }}<input name="{{ $name }}" value="{{ $value }}" maxlength="{{ $f['max'] ?? 300 }}" @required(empty($f['optional']))></label>
        @break
    @case('textarea')
        <label>{{ ucfirst($label) }}<textarea name="{{ $name }}" rows="3" maxlength="{{ $f['max'] ?? 600 }}" @required(empty($f['optional']))>{{ $value }}</textarea></label>
        @break
    @case('richtext')
        <div class="fieldset">
            <span class="lbl" id="l-{{ $id }}">{{ ucfirst($label) }}</span>
            <div class="rte" data-rte @if (! empty($f['headings'])) data-headings @endif>
                <div class="rte-bar" role="toolbar" aria-label="Formatting for {{ $label }}"></div>
                <div class="rte-body" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="l-{{ $id }}">{!! $value !!}</div>
                <textarea name="{{ $name }}" hidden>{{ $value }}</textarea>
            </div>
            <small class="fine" style="margin:0">Bold, italic, links and lists only.</small>
        </div>
        @break
    @case('button')
        <div class="fieldset"><span class="lbl">{{ ucfirst($label) }}</span>
            <div class="fgrid">
                <label>Label<input name="{{ $name }}[label]" value="{{ $value['label'] ?? '' }}" maxlength="40"></label>
                <label>Link<input name="{{ $name }}[url]" value="{{ $value['url'] ?? '' }}" placeholder="/join or https://"></label>
            </div>
        </div>
        @break
    @case('image')
        @php($m = ! empty($value['media']) ? ($media[$value['media']] ?? null) : null)
        <div class="fieldset"><span class="lbl">{{ ucfirst($label) }}</span>
            @if ($m)
                <div class="thumbline"><img src="{{ $m['url'] }}" alt="{{ $value['alt'] ?? '' }}"><label class="chk"><input type="checkbox" name="{{ $name }}[remove]" value="1"> Remove this image</label></div>
                <input type="hidden" name="{{ $name }}[media]" value="{{ $value['media'] }}">
            @endif
            <input type="file" name="{{ $fileName }}" accept="image/png,image/jpeg,image/webp" aria-label="{{ ucfirst($label) }} file">
            <label>Alt text (describe the image for people who cannot see it)<input name="{{ $name }}[alt]" value="{{ $value['alt'] ?? '' }}" maxlength="200"></label>
        </div>
        @break
    @case('list')
        <div class="fieldset" data-list data-max="{{ $f['max'] ?? 12 }}" data-min="{{ $f['min'] ?? 0 }}">
            <span class="lbl">{{ ucfirst($label) }}s</span>
            <div data-rows>
            @foreach (array_values($value ?: []) as $i => $row)
                @include('admin._row', ['f' => $f, 'name' => $name, 'index' => $i, 'row' => $row, 'media' => $media, 'id' => $id])
            @endforeach
            </div>
            <template data-template>@include('admin._row', ['f' => $f, 'name' => $name, 'index' => '__i__', 'row' => [], 'media' => $media, 'id' => $id])</template>
            <button type="button" class="btn-s plain sm" data-add>Add {{ strtolower($label) }}</button>
        </div>
        @break
@endswitch
