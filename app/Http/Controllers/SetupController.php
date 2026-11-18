<?php

namespace App\Http\Controllers;

use App\Services\Brand;
use App\Support\Installer;
use App\Support\Routing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** The first-run setup wizard. Reachable only until setup has finished. */
class SetupController extends Controller
{
    /** Step id => label, in order. */
    public const STEPS = ['key' => 'Owner key', 'check' => 'Server check', 'union' => 'Your credit union', 'database' => 'Database', 'email' => 'Email', 'admin' => 'First administrator', 'review' => 'Install'];

    public function show(Request $request, ?string $step = null)
    {
        $step = $step ?? $this->firstOpen($request);
        abort_unless(isset(self::STEPS[$step]), 404);
        if (! $this->reachable($request, $step)) {
            return redirect()->route('setup', $this->firstOpen($request));
        }

        $data = ['step' => $step, 'steps' => self::STEPS, 'd' => $request->session()->get('setup', []), 'done' => $this->done($request)];
        if ($step === 'key') {
            $data['keyFile'] = 'storage/app/install-key.txt';
            $data['local'] = ! Installer::keyRequired($request);
            Installer::key();
        }
        if ($step === 'check') {
            $data['rows'] = Installer::requirements();
            $data['blocked'] = collect($data['rows'])->contains(fn ($r) => $r['blocking'] && ! $r['ok']);
        }
        if ($step === 'union') {
            $data['tzs'] = Installer::timezones();
            $data['defaults'] = Brand::DEFAULTS;
            $data['suggestedUrl'] = rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return view('setup.step', $data);
    }

    public function save(Request $request, string $step)
    {
        abort_unless(isset(self::STEPS[$step]), 404);
        abort_unless($this->reachable($request, $step), 403);

        $method = 'save'.ucfirst($step);
        $result = $this->$method($request);
        if ($result !== null) {
            return $result;
        }

        $done = $this->done($request);
        $done[$step] = true;
        $request->session()->put('setup_done', $done);
        $keys = array_keys(self::STEPS);
        $next = $keys[array_search($step, $keys, true) + 1] ?? 'review';

        return redirect()->route('setup', $next);
    }

    private function saveKey(Request $request)
    {
        if (! Installer::keyRequired($request)) {
            return null;
        }
        $limit = 'setup-key:'.$request->ip();
        if (RateLimiter::tooManyAttempts($limit, 5)) {
            throw ValidationException::withMessages(['key' => 'Too many tries. Wait '.ceil(RateLimiter::availableIn($limit) / 60).' minutes, then try again.']);
        }
        $request->validate(['key' => ['required', 'string']]);
        if (! hash_equals(Installer::key(), strtoupper(trim((string) $request->input('key'))))) {
            RateLimiter::hit($limit, 900);
            throw ValidationException::withMessages(['key' => 'That is not the key in the file. Open the file on the server and copy it exactly.']);
        }

        RateLimiter::clear($limit);

        return null;
    }

    private function saveCheck(Request $request)
    {
        if (collect(Installer::requirements())->contains(fn ($r) => $r['blocking'] && ! $r['ok'])) {
            throw ValidationException::withMessages(['check' => 'Fix the items marked as missing first, then check again.']);
        }

        return null;
    }

    private function saveUnion(Request $request)
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'short_name' => ['required', 'string', 'max:60'],
            'descriptor' => ['nullable', 'string', 'max:60'], 'tagline' => ['nullable', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:200'],
            'support_email' => ['required', 'email', 'max:160'],
            'support_phone' => ['required', 'regex:/^[0-9+().\- ]{7,20}$/'],
            'support_hours' => ['required', 'string', 'max:120'],
            'routing_number' => ['required', 'digits:9', fn ($a, $val, $fail) => Routing::valid((string) $val) ?: $fail('That routing number does not pass the ABA check. Re-enter the nine digits.')],
            'nmls_number' => ['nullable', 'digits_between:4,12'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'highlight_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tz' => ['required', fn ($a, $val, $fail) => Installer::validTimezone((string) $val) ?: $fail('Choose a time zone from the list.')],
        ], ['support_phone.regex' => 'Enter a phone number using digits, spaces, dashes or parentheses.', 'brand_color.regex' => 'Enter the brand colour as a hex value such as #14403a.', 'highlight_color.regex' => 'Enter the highlight colour as a hex value such as #f5bd02.']);
        if ($problem = Brand::colorProblem($v['brand_color'], $v['highlight_color'])) {
            throw ValidationException::withMessages(['brand_color' => $problem]);
        }
        $request->session()->put('setup.url', rtrim($v['url'], '/'));
        $request->session()->put('setup.tz', $v['tz']);
        $request->session()->put('setup.brand', collect($v)->except(['url', 'tz'])->all());

        return null;
    }

    private function saveDatabase(Request $request)
    {
        $driver = $request->validate(['driver' => ['required', 'in:sqlite,mysql']])['driver'];
        $db = ['driver' => $driver];
        if ($driver === 'mysql') {
            $db = $request->validate([
                'driver' => ['required'], 'host' => ['required', 'string', 'max:160'], 'port' => ['required', 'integer', 'between:1,65535'],
                'name' => ['required', 'string', 'max:64'], 'user' => ['required', 'string', 'max:64'], 'pass' => ['nullable', 'string', 'max:200'],
            ]);
        }
        if ($error = Installer::testDatabase($db)) {
            throw ValidationException::withMessages(['driver' => $error]);
        }
        $request->session()->put('setup.db', $db);

        return null;
    }

    private function saveEmail(Request $request)
    {
        $mode = $request->validate(['mode' => ['required', 'in:smtp,later']])['mode'];
        $mail = ['mode' => $mode];
        if ($mode === 'smtp') {
            $mail += $request->validate([
                'host' => ['required', 'string', 'max:160'], 'port' => ['required', 'integer', 'between:1,65535'],
                'user' => ['required', 'string', 'max:200'], 'pass' => ['required', 'string', 'max:200'], 'from' => ['required', 'email', 'max:160'],
            ]);
            if ($error = Installer::testMail($mail['host'], (int) $mail['port'])) {
                throw ValidationException::withMessages(['host' => $error]);
            }
        }
        $request->session()->put('setup.mail', $mail);

        return null;
    }

    private function saveAdmin(Request $request)
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'], 'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ], ['password.confirmed' => 'The two passwords do not match.']);
        $request->session()->put('setup.admin', ['name' => $v['name'], 'email' => $v['email'], 'password' => $v['password']]);

        return null;
    }

    private function saveReview(Request $request)
    {
        $d = $request->session()->get('setup', []);
        foreach (['url', 'tz', 'brand', 'db', 'mail', 'admin'] as $k) {
            if (empty($d[$k])) {
                return redirect()->route('setup', 'union')->withErrors(['setup' => 'Some answers are missing. Go through the steps again.']);
            }
        }
        $d['live'] = $request->boolean('live');

        try {
            Installer::install($d);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['install' => 'Setup did not finish: '.preg_replace('/\s+/', ' ', mb_substr($e->getMessage(), 0, 300)).' Nothing is locked yet, so fix it and try again.']);
        }
        $request->session()->forget(['setup', 'setup_done']);

        return redirect('/login')->with('status', 'Setup is done. Sign in with the administrator account you just created.');
    }

    private function done(Request $request): array
    {
        return (array) $request->session()->get('setup_done', []);
    }

    private function firstOpen(Request $request): string
    {
        foreach (array_keys(self::STEPS) as $s) {
            if ($s !== 'review' && empty($this->done($request)[$s])) {
                return $s;
            }
        }

        return 'review';
    }

    /** A step opens once every step before it is done. */
    private function reachable(Request $request, string $step): bool
    {
        foreach (array_keys(self::STEPS) as $s) {
            if ($s === $step) {
                return true;
            }
            if (empty($this->done($request)[$s])) {
                return false;
            }
        }

        return false;
    }
}
