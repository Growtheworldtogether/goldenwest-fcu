<?php

use App\Models\BrandSetting;
use App\Models\Card;
use App\Models\KycDocument;
use App\Notifications\MemberNotice;
use App\Services\Brand;
use App\Services\CardService;
use App\Services\KycService;
use App\Support\MailBrand;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    Brand::forget();
});

function renderNotice(?object $user = null): string
{
    $user ??= makeUser();

    return (string) (new MemberNotice('Hello subject', ['One line of text.'], 'Open online banking', url('/dashboard')))->toMail($user)->render();
}

it('builds every email from the branding settings, not from fixed text', function () {
    BrandSetting::create(['name' => 'Redwood Community Credit Union', 'short_name' => 'Redwood', 'brand_color' => '#123456', 'highlight_color' => '#ff8800', 'support_phone' => '888-555-0101', 'support_email' => 'help@redwood.example', 'support_hours' => 'Every day 8am - 6pm', 'sender_name' => 'Redwood CU', 'footer_text' => 'Member owned since 1964.']);
    Brand::forget();
    $html = renderNotice();
    expect($html)->toContain('Redwood Community Credit Union')->toContain('888-555-0101')->toContain('help@redwood.example')
        ->toContain('Every day 8am - 6pm')->toContain('Member owned since 1964.')->toContain('#ff8800')->toContain('#123456')
        ->toContain('Redwood will never ask for your password')
        ->not->toContain('Golden West')->not->toContain('Laravel')->not->toContain('#14403a');
});

it('uses the default branding when nothing has been saved', function () {
    $html = renderNotice();
    expect($html)->toContain('Golden West Federal Credit Union')->toContain('800-555-0147')->toContain('#f5bd02')->not->toContain('Laravel');
});

it('shows the logo in the header, using the file shipped with the site when none is uploaded', function () {
    expect(MailBrand::palette()['logo'])->toContain('/images/logo-');
    expect(renderNotice())->toContain('<img')->toContain('/images/logo-');
});

it('picks readable button text for any highlight colour', function () {
    BrandSetting::create(['name' => 'X', 'short_name' => 'X', 'brand_color' => '#14403a', 'highlight_color' => '#101010', 'support_phone' => '800-555-0147', 'support_email' => 'a@b.example', 'support_hours' => 'x', 'sender_name' => 'X']);
    Brand::forget();
    expect(MailBrand::palette()['on_highlight'])->toBe('#111111');
    BrandSetting::query()->update(['highlight_color' => '#f5bd02']);
    Brand::forget();
    expect(MailBrand::palette()['on_highlight'])->toBe('#14403a');
});

it('sends from the support email unless the env sets an address', function () {
    expect(config('mail.from.address'))->not->toBe('hello@example.com')->toContain('@');
    expect(config('mail.from.name'))->not->toBeEmpty();
});

it('sends a welcome email when a member confirms their email', function () {
    Notification::fake();
    [$user] = verifiedMember();
    event(new Verified($user));
    Notification::assertSentTo($user, MemberNotice::class, fn ($n) => str_starts_with($n->subject, 'Welcome to '));
});

it('tells a member when their password changes, always', function () {
    Notification::fake();
    [$user] = verifiedMember();
    $user->forceFill(['notification_preferences' => ['email_transactions' => false]])->save();
    $this->actingAs($user);
    app(\App\Actions\Fortify\UpdateUserPassword::class)->update($user, ['current_password' => 'a-long-test-password-1', 'password' => 'Another-Long-Pass-2', 'password_confirmation' => 'Another-Long-Pass-2']);
    Notification::assertSentTo($user, MemberNotice::class, fn ($n) => $n->subject === 'Your password was changed' && $n->security);
});

it('emails a member when an ID is approved or rejected, without the reason', function () {
    Notification::fake();
    [$user] = verifiedMember();
    $staff = makeStaff('Compliance');
    $img = fn () => UploadedFile::fake()->image('id.png', 800, 500);
    $doc = app(KycService::class)->submit($user, 'government_id', $img());
    $doc->update(['status' => 'pending']);
    app(KycService::class)->decide($doc, $staff, false, 'unclear_image', 'The photo was too dark to read.');
    Notification::assertSentTo($user, MemberNotice::class, function ($n) {
        $text = implode(' ', $n->lines);

        return str_contains($n->subject, 'could not accept') && ! str_contains($text, 'too dark');
    });
});

it('emails a member when staff decide on a card request', function () {
    Notification::fake();
    [$user, $checking] = verifiedMember();
    $user->forceFill(['kyc_status' => 'verified'])->save();
    $staff = makeStaff('Manager');
    $card = app(CardService::class)->request($user, $checking->id, 'Everyday');
    app(CardService::class)->decide($card, $staff, true);
    Notification::assertSentTo($user, MemberNotice::class, fn ($n) => $n->subject === 'Your card request was approved');
});

it('emails the member when staff hold and then reject a large transfer, with no amounts', function () {
    Notification::fake();
    $user = makeUser();
    $from = makeAccount($user, 2_000_000);
    $to = makeAccount(makeUser(), 0, 'GOLDEN-SAVINGS');
    $tx = transfer($from, $to, 600_000);
    $service = app(App\Services\Ledger\TransferService::class);
    $staff = makeStaff('Manager');
    $tx = $service->hold($tx, $staff, 'account_review', 'Looking at it.');
    Notification::assertSentTo($user, MemberNotice::class, fn ($n) => str_contains($n->subject, 'reviewing') && ! str_contains(implode(' ', $n->lines), '$'));
    $service->reject($tx, $staff, 'account_review', 'Not able to confirm.');
    Notification::assertSentTo($user, MemberNotice::class, fn ($n) => str_contains($n->subject, 'could not complete') && ! str_contains(implode(' ', $n->lines), 'confirm'));
});

it('lets staff preview each email and send a test', function () {
    $super = makeStaff('Super Admin');
    $page = $this->actingAs($super)->get('/admin/emails')->assertOk();
    foreach (['activity', 'welcome', 'password', 'verify', 'reset'] as $key) {
        $this->actingAs($super)->get("/admin/emails/preview/$key")->assertOk()->assertSee(brand('support_phone'), false);
    }
    Notification::fake();
    $this->actingAs($super)->post('/admin/emails/test')->assertSessionHas('sent');
    Notification::assertSentTo($super, MemberNotice::class);
});

it('keeps the email pages away from members and staff without site access', function () {
    [$member] = verifiedMember();
    $this->actingAs($member)->get('/admin/emails')->assertForbidden();
    $teller = makeStaff('Teller');
    $this->actingAs($teller)->get('/admin/emails')->assertForbidden();
});

it('has a command to test the mail settings', function () {
    Notification::fake();
    $this->artisan('mail:test', ['email' => 'owner@example.com'])->expectsOutputToContain('Mailer:')->assertSuccessful();
});

it('never prints a raw link in the text of an email', function () {
    $user = makeUser();
    $emails = [
        (new MemberNotice('Subject', ['Line.'], 'Open online banking', url('/dashboard')))->toMail($user),
        (new Illuminate\Auth\Notifications\VerifyEmail)->toMail($user),
        (new Illuminate\Auth\Notifications\ResetPassword('tok'))->toMail($user),
    ];
    foreach ($emails as $mail) {
        $visible = strip_tags(preg_replace('/<style.*?<\/style>/s', '', (string) $mail->render()));
        expect($visible)->not->toContain('http')->not->toContain('trouble clicking');
    }
});
