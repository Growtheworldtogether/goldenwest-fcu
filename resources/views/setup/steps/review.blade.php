@php $b = $d['brand']; $mail = $d['mail']; $db = $d['db']; @endphp
<h1>Ready to install</h1>
<p class="lead">Check your answers. Installing creates the tables and your administrator account, and takes up to a minute. Do not close the page.</p>
<ul class="rows">
    <li><span>Credit union</span><strong>{{ $b['name'] }}</strong></li>
    <li><span>Web address</span><strong>{{ $d['url'] }}</strong></li>
    <li><span>Support</span><strong>{{ $b['support_email'] }} &middot; {{ $b['support_phone'] }}</strong></li>
    <li><span>Routing number</span><strong>{{ $b['routing_number'] }}</strong></li>
    <li><span>Time zone</span><strong>{{ $d['tz'] }}</strong></li>
    <li><span>Database</span><strong>{{ $db['driver'] === 'sqlite' ? 'SQLite file' : $db['name'].' on '.$db['host'] }}</strong></li>
    <li><span>Email</span><strong>{{ $mail['mode'] === 'smtp' ? $mail['user'].' via '.$mail['host'] : 'Later (written to a log)' }}</strong></li>
    <li><span>Administrator</span><strong>{{ $d['admin']['email'] }}</strong></li>
</ul>
<label class="choice" style="margin-top:1.25rem"><input type="checkbox" name="live" value="1" @checked(str_starts_with($d['url'], 'https://'))><span><b>This is a live site</b>Turns off debug pages and detailed errors. Leave unticked on your own computer.</span></label>
<div class="actions"><button class="btn">Install now</button><a class="btn ghost" href="{{ route('setup', 'admin') }}">Back</a></div>
