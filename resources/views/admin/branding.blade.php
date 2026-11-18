@extends('layouts.portal')
@section('title', 'Branding')
@section('band')
<h1>Branding</h1>
<p class="psub">Name, logo, colours and contact details. Changes go live on every page, email and statement as soon as you save.</p>
@endsection
@section('content')
<form method="post" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" class="brandform">
    @csrf @method('PUT')
    @php($err = fn ($k) => $errors->first($k))

    <div class="panel-card">
        <h2>Name</h2>
        <div class="fgrid">
            <label>Full name<input name="name" value="{{ old('name', $b['name']) }}" required maxlength="120"><small>Used in page titles, emails, statements and legal text.</small>@error('name')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Short name<input name="short_name" value="{{ old('short_name', $b['short_name']) }}" required maxlength="60"><small>Used in navigation and running text.</small>@error('short_name')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Line under the name<input name="descriptor" value="{{ old('descriptor', $b['descriptor']) }}" maxlength="60"><small>Shown small beneath the wordmark, for example Federal Credit Union.</small></label>
            <label>Tagline<input name="tagline" value="{{ old('tagline', $b['tagline']) }}" maxlength="120"><small>Used in the browser tab title of the home page.</small></label>
        </div>
    </div>

    <div class="panel-card">
        <h2>Logo and icon</h2>
        <p class="fine" style="margin-top:0">PNG, JPG or WebP up to 4 MB. Without an upload, the name appears as a text wordmark. SVG files are not accepted.</p>
        <div class="fgrid">
            @foreach ([['logo_light', 'logo_light_id', 'Logo for light backgrounds', 'Website header and footer.', '#fff'], ['logo_dark', 'logo_dark_id', 'Logo for dark backgrounds', 'Online banking sidebar.', 'var(--navy)']] as [$input, $col, $label, $help, $bg])
            <div class="upl">
                <span class="lbl">{{ $label }}</span>
                <div class="thumb" style="background:{{ $bg }}">@if ($url = \App\Services\Brand::mediaUrl($b[$col]))<img src="{{ $url }}" alt="Current {{ strtolower($label) }}">@else<em>Text wordmark</em>@endif</div>
                <input type="file" name="{{ $input }}" accept="image/png,image/jpeg,image/webp" aria-label="{{ $label }}">
                <small>{{ $help }}</small>
                @error($input)<span class="err">{{ $message }}</span>@enderror
                @if ($b[$col])<label class="chk"><input type="checkbox" name="remove_{{ $input }}" value="1"> Remove and go back to the text wordmark</label>@endif
            </div>
            @endforeach
            <div class="upl">
                <span class="lbl">Favicon and app icon</span>
                <div class="thumb" style="background:#fff"><img src="{{ route('brand.favicon') }}?v={{ $b['favicon_id'] ?? 0 }}" alt="Current favicon" width="48" height="48"></div>
                <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp" aria-label="Favicon">
                <small>Square, at least 64 by 64 pixels. Shown in the browser tab and on home screens.</small>
                @error('favicon')<span class="err">{{ $message }}</span>@enderror
                @if ($b['favicon_id'])<label class="chk"><input type="checkbox" name="remove_favicon" value="1"> Remove and use the default icon</label>@endif
            </div>
        </div>
    </div>

    <div class="panel-card">
        <h2>Colours</h2>
        <p class="fine" style="margin-top:0">The brand colour drives headers, buttons and links. The highlight colour is used for the main call-to-action button. Pairs that are hard to read are refused.</p>
        <div class="fgrid">
            <label>Brand colour
                <span class="colorrow"><input type="color" id="brand_picker" value="{{ old('brand_color', $b['brand_color']) }}" aria-label="Pick brand colour"><input name="brand_color" id="brand_color" value="{{ old('brand_color', $b['brand_color']) }}" maxlength="7" required pattern="#[0-9a-fA-F]{6}"></span>
                @error('brand_color')<span class="err">{{ $message }}</span>@enderror
            </label>
            <label>Highlight colour
                <span class="colorrow"><input type="color" id="hi_picker" value="{{ old('highlight_color', $b['highlight_color']) }}" aria-label="Pick highlight colour"><input name="highlight_color" id="highlight_color" value="{{ old('highlight_color', $b['highlight_color']) }}" maxlength="7" required pattern="#[0-9a-fA-F]{6}"></span>
                @error('highlight_color')<span class="err">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="swatch" id="swatch" style="background:{{ $b['brand_color'] }}"><span class="btn-s gold" style="background:{{ $b['highlight_color'] }};color:{{ $b['brand_color'] }}">Open an account</span><span class="swtxt">Sample header text</span><span class="ratio" id="ratio"></span></div>
    </div>

    <div class="panel-card">
        <h2>Contact details</h2>
        <div class="fgrid">
            <label>Support email<input type="email" name="support_email" value="{{ old('support_email', $b['support_email']) }}" required>@error('support_email')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Support phone<input name="support_phone" value="{{ old('support_phone', $b['support_phone']) }}" required>@error('support_phone')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Support hours<input name="support_hours" value="{{ old('support_hours', $b['support_hours']) }}" required maxlength="120">@error('support_hours')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Routing number<input name="routing_number" value="{{ old('routing_number', $b['routing_number']) }}" required inputmode="numeric" maxlength="9" pattern="[0-9]{9}"><small>Shown to members for direct deposit and transfers.</small>@error('routing_number')<span class="err">{{ $message }}</span>@enderror</label>
            <label>NMLS number<input name="nmls_number" value="{{ old('nmls_number', $b['nmls_number']) }}" inputmode="numeric" maxlength="12"><small>Shown in the site footer. Leave empty to hide it.</small>@error('nmls_number')<span class="err">{{ $message }}</span>@enderror</label>
            <label>Email sender name<input name="sender_name" value="{{ old('sender_name', $b['sender_name']) }}" required maxlength="120"><small>The "from" name on mail. The sender address stays in server settings.</small></label>
        </div>
    </div>

    <div class="panel-card">
        <h2>Footer and social links</h2>
        <label class="wide">Footer text<textarea name="footer_text" rows="2" maxlength="400">{{ old('footer_text', $b['footer_text']) }}</textarea><small>An extra line above the legal notice. Optional.</small></label>
        <div class="fgrid" style="margin-top:1rem">
            @foreach (['facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'instagram' => 'Instagram'] as $k => $l)
                <label>{{ $l }} link<input type="url" name="social[{{ $k }}]" value="{{ old('social.'.$k, $b['social_links'][$k] ?? '') }}" placeholder="https://">@error('social.'.$k)<span class="err">{{ $message }}</span>@enderror</label>
            @endforeach
        </div>
        <p class="fine">The legal notice at the very bottom of every page is fixed and cannot be edited or removed here.</p>
    </div>

    <div class="formbar"><button class="btn-s" type="submit">Save branding</button></div>
</form>
@push('scripts')
<script>
(function () {
    function lum(h){var c=[1,3,5].map(function(i){var v=parseInt(h.substr(i,2),16)/255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)});return .2126*c[0]+.7152*c[1]+.0722*c[2];}
    function ratio(a,b){var x=lum(a),y=lum(b);if(x<y){var t=x;x=y;y=t}return (x+.05)/(y+.05);}
    var bc=document.getElementById('brand_color'),hc=document.getElementById('highlight_color'),bp=document.getElementById('brand_picker'),hp=document.getElementById('hi_picker'),sw=document.getElementById('swatch'),out=document.getElementById('ratio');
    function ok(v){return /^#[0-9a-fA-F]{6}$/.test(v);}
    function paint(){
        if(!ok(bc.value)||!ok(hc.value)){out.textContent='';return;}
        sw.style.background=bc.value;var b=sw.querySelector('.btn-s');b.style.background=hc.value;b.style.color=bc.value;
        var r1=ratio(bc.value,'#ffffff'),r2=ratio(hc.value,bc.value);
        out.textContent='White on brand '+r1.toFixed(1)+':1, button text '+r2.toFixed(1)+':1'+((r1<4.5||r2<4.5)?' (too low, needs 4.5)':'');
        out.style.color=(r1<4.5||r2<4.5)?'#ffd2cf':'#cfe3dc';
    }
    bp.addEventListener('input',function(){bc.value=bp.value;paint();});hp.addEventListener('input',function(){hc.value=hp.value;paint();});
    bc.addEventListener('input',function(){if(ok(bc.value))bp.value=bc.value;paint();});hc.addEventListener('input',function(){if(ok(hc.value))hp.value=hc.value;paint();});
    paint();
})();
</script>
@endpush
@endsection
