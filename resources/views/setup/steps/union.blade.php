@php $b = $d['brand'] ?? []; $v = fn ($k, $def = '') => old($k, $b[$k] ?? $def); @endphp
<h1>Your credit union</h1>
<p class="lead">These appear on every page and email. You can change all of them later in Admin, then Branding. Logos are uploaded there too.</p>
<div class="grid">
    <label>Full name<input name="name" value="{{ $v('name', $defaults['name']) }}" required maxlength="120" placeholder="Example Federal Credit Union" autofocus>@error('name')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Short name<input name="short_name" value="{{ $v('short_name', $defaults['short_name']) }}" required maxlength="60" placeholder="Example">@error('short_name')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Line under the name<input name="descriptor" value="{{ $v('descriptor', $defaults['descriptor']) }}" maxlength="60">@error('descriptor')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Tagline<input name="tagline" value="{{ $v('tagline', $defaults['tagline']) }}" maxlength="120">@error('tagline')<span class="err">{{ $message }}</span>@enderror</label>
    <label class="wide">Web address of the site<input name="url" value="{{ old('url', $d['url'] ?? $suggestedUrl) }}" required placeholder="https://www.example.org"><small>Used in emails and links. Use the address members will type.</small>@error('url')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Support email<input type="email" name="support_email" value="{{ $v('support_email', $defaults['support_email']) }}" required>@error('support_email')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Support phone<input name="support_phone" value="{{ $v('support_phone', $defaults['support_phone']) }}" required placeholder="800-555-0100">@error('support_phone')<span class="err">{{ $message }}</span>@enderror</label>
    <label class="wide">Support hours<input name="support_hours" value="{{ $v('support_hours', $defaults['support_hours']) }}" required maxlength="120" placeholder="Mon-Fri 8am - 6pm ET, Sat 9am - 1pm ET">@error('support_hours')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Routing number<input name="routing_number" value="{{ $v('routing_number', $defaults['routing_number']) }}" required inputmode="numeric" maxlength="9" pattern="[0-9]{9}"><small>Nine digits. Members see it for direct deposit.</small>@error('routing_number')<span class="err">{{ $message }}</span>@enderror</label>
    <label>NMLS number (optional)<input name="nmls_number" value="{{ $v('nmls_number', $defaults['nmls_number']) }}" inputmode="numeric" maxlength="12">@error('nmls_number')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Brand colour<input type="color" name="brand_color" value="{{ $v('brand_color', $defaults['brand_color']) }}" required>@error('brand_color')<span class="err">{{ $message }}</span>@enderror</label>
    <label>Highlight colour<input type="color" name="highlight_color" value="{{ $v('highlight_color', $defaults['highlight_color']) }}" required>@error('highlight_color')<span class="err">{{ $message }}</span>@enderror</label>
    <label class="wide">Time zone<select name="tz" required>@foreach ($tzs as $id => $label)<option value="{{ $id }}" @selected(old('tz', $d['tz'] ?? 'America/New_York') === $id)>{{ $label }} ({{ $id }})</option>@endforeach</select><small>Every date, daily job and the wire cutoff use this. Set it now: changing it later shifts times already saved.</small>@error('tz')<span class="err">{{ $message }}</span>@enderror</label>
</div>
<div class="actions"><button class="btn">Continue</button><a class="btn ghost" href="{{ route('setup', 'check') }}">Back</a></div>
