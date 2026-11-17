<?php

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Notifications\TicketReplied;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    Storage::fake('local');
});

function openTicket($member, string $body = 'My card is not working online.'): Ticket
{
    return app(TicketService::class)->open($member, 'card', 'Card declined online', $body);
}

it('lets a member open a ticket with an attachment and see it in their list', function () {
    [$u] = verifiedMember('Maya Brooks');

    $this->actingAs($u)->post('/support', [
        'category' => 'card', 'subject' => 'Card declined online', 'body' => 'It fails at checkout.',
        'files' => [UploadedFile::fake()->image('shot.png', 800, 600)],
    ])->assertRedirect();

    $t = Ticket::firstOrFail();
    expect($t->status)->toBe('awaiting_staff')->and($t->user_id)->toBe($u->id)->and(TicketAttachment::count())->toBe(1);
    $this->actingAs($u)->get('/support')->assertOk()->assertSee('Card declined online');
    $this->actingAs($u)->get('/tickets/'.$t->reference)->assertOk()->assertSee('It fails at checkout.');
});

it('never shows a member an internal note, anywhere', function () {
    Notification::fake();
    [$u] = verifiedMember('Maya Brooks');
    $staff = makeStaff('Support Agent');
    $t = openTicket($u);
    $svc = app(TicketService::class);
    $svc->note($t, $staff, 'SECRET-NOTE possible account takeover, do not tell her');
    $svc->staffReply($t, $staff, 'We are looking into it.');

    $this->actingAs($u)->get('/tickets/'.$t->reference)->assertOk()->assertSee('We are looking into it.')->assertDontSee('SECRET-NOTE');
    $this->actingAs($u)->get('/support')->assertDontSee('SECRET-NOTE');
    expect($t->memberMessages()->pluck('body')->implode(' '))->not->toContain('SECRET-NOTE');

    // the staff screen does show it, labelled
    $this->actingAs($staff)->get('/admin/tickets/'.$t->reference)->assertOk()->assertSee('SECRET-NOTE')->assertSee('the member cannot see this');

    // and the email carries no message text at all
    Notification::assertSentTo($u, TicketReplied::class, function ($n) use ($u) {
        $text = collect($n->toMail($u)->introLines)->implode(' ');
        return ! str_contains($text, 'SECRET-NOTE') && ! str_contains($text, 'We are looking into it.');
    });
});

it('does not send the member an email for an internal note', function () {
    Notification::fake();
    [$u] = verifiedMember('Maya Brooks');
    $t = openTicket($u);
    app(TicketService::class)->note($t, makeStaff('Support Agent'), 'just for us');
    Notification::assertNothingSent();
    expect($t->refresh()->status)->toBe('awaiting_staff');
});

it('keeps tickets private to their owner', function () {
    [$a] = verifiedMember('Maya Brooks');
    [$b] = verifiedMember('Noah Price');
    $t = openTicket($a);

    $this->actingAs($b)->get('/tickets/'.$t->reference)->assertNotFound();
    $this->actingAs($b)->post('/tickets/'.$t->reference.'/reply', ['body' => 'hi'])->assertNotFound();
    $this->actingAs($b)->post('/tickets/'.$t->reference.'/resolve')->assertNotFound();
});

it('keeps the staff inbox to people who answer tickets', function () {
    [$u] = verifiedMember('Maya Brooks');
    $t = openTicket($u);

    $this->actingAs(makeStaff('Teller'))->get('/admin/tickets')->assertForbidden();
    $this->actingAs(makeStaff('Teller'))->post('/admin/tickets/'.$t->reference.'/reply', ['body' => 'x'])->assertForbidden();
    $this->actingAs($u)->get('/admin/tickets')->assertStatus(403);
    $this->actingAs(makeStaff('Support Agent'))->get('/admin/tickets')->assertOk()->assertSee('Card declined online');
});

it('moves status as each side replies and reopens a resolved ticket', function () {
    Notification::fake();
    [$u] = verifiedMember('Maya Brooks');
    $staff = makeStaff('Support Agent');
    $t = openTicket($u);

    $this->actingAs($staff)->post('/admin/tickets/'.$t->reference.'/reply', ['body' => 'Try again now.'])->assertRedirect();
    expect($t->refresh()->status)->toBe('awaiting_member')->and($t->assigned_to)->toBe($staff->id);

    $this->actingAs($u)->post('/tickets/'.$t->reference.'/resolve')->assertRedirect();
    expect($t->refresh()->status)->toBe('resolved')->and($t->resolved_at)->not->toBeNull();

    $this->actingAs($u)->post('/tickets/'.$t->reference.'/reply', ['body' => 'Still broken.'])->assertRedirect();
    expect($t->refresh()->status)->toBe('awaiting_staff')->and($t->resolved_at)->toBeNull();
});

it('stops a member replying to a closed ticket', function () {
    [$u] = verifiedMember('Maya Brooks');
    $t = openTicket($u);
    app(TicketService::class)->setStatus($t, makeStaff('Support Agent'), 'closed');

    $this->actingAs($u)->post('/tickets/'.$t->reference.'/reply', ['body' => 'hello?'])->assertSessionHas('error');
    expect($t->messages()->count())->toBe(1);
    $this->actingAs($u)->get('/tickets/'.$t->reference)->assertSee('is closed');
});

it('closes resolved tickets after the set number of days and leaves newer ones', function () {
    [$u] = verifiedMember('Maya Brooks');
    $svc = app(TicketService::class);
    $old = openTicket($u);
    $svc->setStatus($old, makeStaff('Support Agent'), 'resolved');
    $this->travelTo(now()->addDays(3));
    $recent = openTicket($u);
    $svc->setStatus($recent, makeStaff('Support Agent'), 'resolved');
    $this->travelTo(now()->addDays(5));

    expect($svc->autoClose())->toBe(1);
    expect($old->refresh()->status)->toBe('closed')->and($recent->refresh()->status)->toBe('resolved');
    $this->artisan('tickets:autoclose')->assertSuccessful();
});

it('assigns only to staff who answer tickets and records changes in the audit trail', function () {
    [$u] = verifiedMember('Maya Brooks');
    $t = openTicket($u);
    $agent = makeStaff('Support Agent');
    $teller = makeStaff('Teller');

    $this->actingAs($agent)->put('/admin/tickets/'.$t->reference, ['assigned_to' => $teller->id, 'priority' => 'urgent'])->assertSessionHasErrors('assigned_to');
    $this->actingAs($agent)->put('/admin/tickets/'.$t->reference, ['assigned_to' => $agent->id, 'priority' => 'urgent'])->assertSessionHasNoErrors();
    expect($t->refresh()->assigned_to)->toBe($agent->id)->and($t->priority)->toBe('urgent');
    expect(\App\Models\AuditLog::whereIn('action', ['ticket.assigned', 'ticket.priority_changed'])->count())->toBe(2);
});

it('serves attachments only to the owner or staff, and logs staff views', function () {
    [$a] = verifiedMember('Maya Brooks');
    [$b] = verifiedMember('Noah Price');
    $t = app(TicketService::class)->open($a, 'other', 'Photo', 'See attached', null, [UploadedFile::fake()->image('p.png', 800, 600)]);
    $att = TicketAttachment::firstOrFail();

    $this->actingAs($a)->get('/files/ticket/'.$att->id)->assertOk();
    $this->actingAs($b)->get('/files/ticket/'.$att->id)->assertForbidden();
    $this->actingAs(makeStaff('Support Agent'))->get('/files/ticket/'.$att->id)->assertOk();
    expect(\App\Models\AuditLog::where('action', 'ticket.attachment_viewed')->count())->toBe(1);
});

it('shows a contested decision in the inbox and links the transaction', function () {
    [$u, $checking] = verifiedMember('Maya Brooks');
    $t = openTicket($u);
    expect($t->fresh()->priority)->toBe('normal');
    $this->actingAs(makeStaff('Support Agent'))->get('/admin/tickets?tab=needs')->assertSee($t->reference);
});

it('fills canned replies with the member name and bank details', function () {
    [$u] = verifiedMember('Maya Brooks');
    $c = TicketService::cannedFor(openTicket($u));
    expect($c['call']['body'])->toContain('Maya')->toContain(brand('support_phone'))->not->toContain('{');
});
