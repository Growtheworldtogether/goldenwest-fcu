<?php

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSecurityAnswer;
use App\Notifications\MemberNotice;
use App\Services\SecurityQuestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedReference();
    Setting::put('security_questions', true);
});

const SQ = ['first_pet' => 'Biscuit', 'birth_city' => 'St. Louis', 'first_car' => 'Honda'];

function memberWithQuestions(): User
{
    [$m] = verifiedMember();
    app(SecurityQuestions::class)->save($m, SQ);

    return $m->refresh();
}

function signOut(): void
{
    auth()->logout();
    test()->flushSession();
    Illuminate\Support\Facades\Cache::flush();
}

function signIn(User $m, array $headers = [])
{
    return test()->withServerVariables($headers)->post('/login', ['email' => $m->email, 'password' => 'a-long-test-password-1']);
}

it('stores the answers hashed, ignores case and punctuation, and needs three different questions', function () {
    $m = memberWithQuestions();
    expect(UserSecurityAnswer::where('user_id', $m->id)->value('answer_hash'))->not->toContain('biscuit');
    $sq = app(SecurityQuestions::class);
    expect($sq->check($m, 'birth_city', 'st louis'))->toBeTrue()->and($sq->check($m, 'birth_city', 'Chicago'))->toBeFalse();
    expect(fn () => $sq->save($m, ['first_pet' => 'a1', 'birth_city' => 'b2']))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('asks one of the three questions after the password, never the same one twice in a row', function () {
    $m = memberWithQuestions();
    $asked = [];
    foreach (range(1, 8) as $i) {
        signIn($m)->assertRedirect('/dashboard');
        $page = $this->get('/dashboard')->assertRedirect('/security-check');
        $text = $this->get('/security-check')->assertOk()->getContent();
        $key = collect(SecurityQuestions::questions())->filter(fn ($q) => str_contains($text, e($q)) || str_contains($text, $q))->keys()->first();
        expect($key)->toBeIn(array_keys(SQ));
        if ($asked) {
            expect($key)->not->toBe(end($asked));
        }
        $asked[] = $key;
        signOut();
    }
    expect(count(array_unique($asked)))->toBeGreaterThan(1);
});

it('lets the member in with the right answer and keeps them out with a wrong one', function () {
    $m = memberWithQuestions();
    signIn($m);
    $this->get('/security-check');
    $key = session('sq_key');
    $this->post('/security-check', ['answer' => 'wrong answer'])->assertSessionHasErrors('answer');
    $this->get('/dashboard')->assertRedirect('/security-check');
    $this->post('/security-check', ['answer' => SQ[$key]])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();
    expect(AuditLog::where('action', 'security_question.passed')->exists())->toBeTrue();
});

it('pauses the sign in after five wrong answers', function () {
    $m = memberWithQuestions();
    signIn($m);
    $this->get('/security-check');
    foreach (range(1, 5) as $i) {
        $r = $this->post('/security-check', ['answer' => 'nope'.$i]);
    }
    $r->assertRedirect('/login');
    expect($m->refresh()->locked_until?->isFuture())->toBeTrue();
    signIn($m)->assertSessionHasErrors();
    $this->assertGuest();
});

it('asks a member who has no questions yet to set them first', function () {
    [$m] = verifiedMember();
    signIn($m);
    $this->get('/dashboard')->assertRedirect('/security-questions/setup');
    $this->post('/security-questions/setup', ['question_1' => 'first_pet', 'answer_1' => 'Biscuit', 'question_2' => 'first_pet', 'answer_2' => 'Rex', 'question_3' => 'first_car', 'answer_3' => 'Honda'])->assertSessionHasErrors('questions');
    $this->post('/security-questions/setup', ['question_1' => 'first_pet', 'answer_1' => 'Biscuit', 'question_2' => 'birth_city', 'answer_2' => 'Reno', 'question_3' => 'first_car', 'answer_3' => 'Honda'])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();
    expect(UserSecurityAnswer::where('user_id', $m->id)->count())->toBe(3);
});

it('adds an emailed code when the sign in is from a new device', function () {
    Notification::fake();
    $m = memberWithQuestions();
    signIn($m, ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows) Chrome']);
    signOut();
    signIn($m, ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) Safari']);
    $this->get('/security-check')->assertOk()->assertSee('Code from your email');
    $code = null;
    Notification::assertSentTo($m, MemberNotice::class, function ($n) use (&$code) {
        if (str_contains($n->subject, 'sign-in code')) {
            preg_match('/code is (\d{6})/', $n->lines[0], $mm);
            $code = $mm[1] ?? null;

            return true;
        }

        return false;
    });
    $key = session('sq_key');
    $this->post('/security-check', ['answer' => SQ[$key], 'code' => '000000'])->assertSessionHasErrors('answer');
    $this->post('/security-check', ['answer' => SQ[$key], 'code' => $code])->assertRedirect('/dashboard');
});

it('does not ask for the code on a known device and network', function () {
    $m = memberWithQuestions();
    signIn($m);
    signOut();
    signIn($m);
    $this->get('/security-check')->assertOk()->assertDontSee('Code from your email');
});

it('asks for nothing when the switch is off, and staff are not forced to set questions', function () {
    Setting::put('security_questions', false);
    [$m] = verifiedMember();
    signIn($m)->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();

    Setting::put('security_questions', true);
    signOut();
    $staff = makeStaff('Manager', false);
    $this->post('/login', ['email' => $staff->email, 'password' => 'a-long-test-password-1']);
    $r = $this->get('/admin');
    expect($r->status())->toBe(200, $r->getContent());
});

it('lets a Super Admin switch it off in settings and audits it', function () {
    $super = makeStaff('Super Admin');
    $this->actingAs($super)->put('/admin/settings', ['impersonation_allow_money_actions' => '1'])->assertRedirect();
    expect(SecurityQuestions::enabled())->toBeFalse()->and(AuditLog::where('action', 'setting.changed')->exists())->toBeTrue();
    $this->actingAs($super)->put('/admin/settings', ['security_questions' => '1'])->assertRedirect();
    expect(SecurityQuestions::enabled())->toBeTrue();
});

it('asks for three questions on the registration form while the feature is on', function () {
    $this->get('/register')->assertOk()->assertSee('Security questions')->assertSee('Question 3');
    Setting::put('security_questions', false);
    $this->get('/register')->assertOk()->assertDontSee('Question 3');
});

it('lets a signed-in member change their questions after confirming the password', function () {
    $m = memberWithQuestions();
    $this->actingAs($m)->get('/security-questions')->assertRedirect('/user/confirm-password');
    $this->actingAs($m)->withSession(['auth.password_confirmed_at' => time()])->get('/security-questions')->assertOk()->assertSee('Question 3');
    $this->actingAs($m)->withSession(['auth.password_confirmed_at' => time()])->post('/security-questions', ['question_1' => 'first_job', 'answer_1' => 'Cashier', 'question_2' => 'first_pet', 'answer_2' => 'Rex', 'question_3' => 'first_car', 'answer_3' => 'Ford'])->assertRedirect('/security');
    expect(app(SecurityQuestions::class)->check($m, 'first_job', 'cashier'))->toBeTrue();
    // The setup page is only for a fresh sign in that has none.
    $this->actingAs($m)->get('/security-questions/setup')->assertNotFound();
});
