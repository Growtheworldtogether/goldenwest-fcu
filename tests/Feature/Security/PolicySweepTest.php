<?php

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(fn () => seedReference());

/** Every route, with a stand-in for each parameter that still matches the route's own pattern. */
function sweepRoutes(): array
{
    $out = [];
    foreach (Route::getRoutes() as $route) {
        /** @var LaravelRoute $route */
        $uri = $route->uri();
        if (str_starts_with($uri, 'livewire') || str_starts_with($uri, '_') || $uri === 'up' || str_starts_with($uri, 'storage')) {
            continue;
        }
        $path = preg_replace_callback('/\{(\w+)(?::\w+)?\??\}/', function ($m) use ($route) {
            $rule = $route->wheres[$m[1]] ?? null;
            if ($rule === null) {
                return 'x';
            }
            foreach (['1', 'x', str_repeat('0', 26), explode('|', $rule)[0]] as $try) {
                if (@preg_match('#^(?:'.$rule.')$#', $try) === 1) {
                    return $try;
                }
            }

            return 'x';
        }, $uri);
        $method = collect($route->methods())->first(fn ($x) => $x !== 'HEAD');
        $mw = collect($route->gatherMiddleware())->map(fn ($m) => is_string($m) ? $m : 'closure')->all();
        $out[] = ['method' => $method, 'path' => '/'.ltrim($path, '/'), 'uri' => $uri, 'mw' => $mw];
    }

    return $out;
}

function needs(array $route, string $needle): bool
{
    return collect($route['mw'])->contains(fn ($m) => str_contains($m, $needle));
}

it('sends a signed-out visitor to sign in for every protected route and never serves or crashes', function () {
    $checked = 0;
    foreach (sweepRoutes() as $r) {
        if (! (needs($r, 'auth') || needs($r, 'staff') || needs($r, 'member.area'))) {
            continue;
        }
        $res = $this->call($r['method'], $r['path']);
        expect($res->getStatusCode())->toBeIn([302, 401, 403, 404, 429], $r['method'].' '.$r['uri'].' gave '.$res->getStatusCode());
        if ($res->getStatusCode() === 302 && $r['uri'] !== 'logout') {
            expect(str_contains((string) $res->headers->get('Location'), 'login'))->toBeTrue($r['method'].' '.$r['uri'].' redirects somewhere other than sign in');
        }
        $checked++;
    }
    expect($checked)->toBeGreaterThan(150);
});

it('keeps every staff route closed to a member', function () {
    [$member] = verifiedMember('Maria Alvarez');
    $checked = 0;
    foreach (sweepRoutes() as $r) {
        if (! str_starts_with($r['uri'], 'admin') || $r['uri'] === 'admin/login') {
            continue;
        }
        $res = $this->actingAs($member)->call($r['method'], $r['path']);
        expect($res->getStatusCode())->toBeIn([302, 403, 404, 429], 'Member got '.$res->getStatusCode().' on '.$r['method'].' '.$r['uri']);
        $checked++;
    }
    expect($checked)->toBeGreaterThan(55);
});

it('keeps every member-area route closed to staff', function () {
    $staff = makeStaff('Super Admin');
    $checked = 0;
    foreach (sweepRoutes() as $r) {
        if (! needs($r, 'member.area')) {
            continue;
        }
        $res = $this->actingAs($staff)->call($r['method'], $r['path']);
        expect($res->getStatusCode())->toBeIn([302, 403, 404, 429], 'Staff got '.$res->getStatusCode().' on '.$r['method'].' '.$r['uri']);
        $checked++;
    }
    expect($checked)->toBeGreaterThan(80);
});

it('never crashes on any route for a verified member, even with bad parameters', function () {
    [$member] = verifiedMember('Maria Alvarez');
    foreach (sweepRoutes() as $r) {
        if (str_starts_with($r['uri'], 'admin') || in_array($r['uri'], ['logout', 'exit-member'], true)) {
            continue;
        }
        $res = $this->actingAs($member)->call($r['method'], $r['path']);
        expect($res->getStatusCode())->toBeLessThan(500, 'Member got '.$res->getStatusCode().' on '.$r['method'].' '.$r['uri']);
    }
});

it('never crashes on any staff route for a Super Admin, even with bad parameters', function () {
    $staff = makeStaff('Super Admin');
    foreach (sweepRoutes() as $r) {
        if (! str_starts_with($r['uri'], 'admin')) {
            continue;
        }
        $res = $this->actingAs($staff)->call($r['method'], $r['path']);
        expect($res->getStatusCode())->toBeLessThan(500, 'Staff got '.$res->getStatusCode().' on '.$r['method'].' '.$r['uri']);
    }
});

it('gives each staff title exactly the pages its permissions allow', function () {
    // Teller, Support Agent, Compliance, Manager, Super Admin. 200 and 302 mean allowed; 403 means refused.
    $matrix = [
        ['GET', 'admin/members', [1, 1, 1, 1, 1]],
        ['GET', 'admin/queue', [1, 0, 1, 1, 1]],
        ['GET', 'admin/identity', [0, 0, 1, 0, 1]],
        ['GET', 'admin/audit', [0, 0, 1, 1, 1]],
        ['GET', 'admin/loans', [0, 0, 1, 1, 1]],
        ['GET', 'admin/cards', [0, 0, 1, 1, 1]],
        ['GET', 'admin/joint', [0, 0, 1, 1, 1]],
        ['GET', 'admin/crypto', [0, 0, 1, 1, 1]],
        ['POST', 'admin/crypto/rates', [0, 0, 0, 0, 1]],
        ['GET', 'admin/announcements', [0, 0, 0, 0, 1]],
        ['POST', 'admin/announcements', [0, 0, 0, 0, 1]],
        ['GET', 'admin/tickets', [0, 1, 1, 1, 1]],
        ['GET', 'admin/inquiries', [0, 1, 1, 1, 1]],
        ['GET', 'admin/branding', [0, 0, 0, 0, 1]],
        ['PUT', 'admin/branding', [0, 0, 0, 0, 1]],
        ['GET', 'admin/site', [0, 0, 0, 0, 1]],
        ['GET', 'admin/settings', [0, 0, 0, 0, 1]],
        ['PUT', 'admin/settings', [0, 0, 0, 0, 1]],
    ];
    $titles = ['Teller', 'Support Agent', 'Compliance', 'Manager', 'Super Admin'];
    $staff = [];
    foreach ($titles as $i => $t) {
        $staff[$i] = makeStaff($t, true, $t.' Person');
    }
    foreach ($matrix as [$method, $uri, $allowed]) {
        foreach ($titles as $i => $t) {
            $status = $this->actingAs($staff[$i])->call($method, '/'.$uri)->status();
            expect($status !== 403)->toBe((bool) $allowed[$i], "$t on $method $uri gave $status");
        }
    }
});

it('keeps one member out of another member\'s accounts, transactions, tickets and recipients', function () {
    [$a, $checkingA] = verifiedMember('Maria Alvarez');
    [$b] = verifiedMember('Jon Parker');
    deposit($checkingA, 25_000);
    $tx = Transaction::where('to_account_id', $checkingA->id)->firstOrFail();
    $ticket = app(App\Services\TicketService::class)->open($a, 'card', 'Card declined online', 'It was declined at checkout.');
    $recipient = App\Models\WireRecipient::create(['user_id' => $a->id, 'name' => 'Pacific Title Co', 'address' => '100 Main St, Reno, NV', 'bank_name' => 'Harbor Bank', 'routing_number' => '021000021', 'account_number' => '998877665544', 'last4' => '5544']);
    $payee = App\Models\Payee::where('user_id', $a->id)->first();

    $this->actingAs($b);
    foreach (['/accounts/'.$checkingA->id, '/accounts/'.$checkingA->id.'/export', '/transactions/'.$tx->reference, '/tickets/'.$ticket->reference] as $url) {
        expect($this->get($url)->getStatusCode())->toBeIn([403, 404], "Another member could open $url");
    }
    expect($this->post('/tickets/'.$ticket->reference.'/resolve')->getStatusCode())->toBeIn([403, 404]);
    expect($this->delete('/wires/recipients/'.$recipient->id)->getStatusCode())->toBeIn([403, 404]);
    expect(App\Models\WireRecipient::find($recipient->id)->status)->toBe('active');
    $this->get('/activity')->assertOk()->assertDontSee($tx->reference);
});
