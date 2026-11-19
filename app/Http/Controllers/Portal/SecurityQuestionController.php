<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\SecurityQuestions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\LoginResponse;

class SecurityQuestionController extends Controller
{
    public function __construct(private SecurityQuestions $sq)
    {
    }

    /** The question for this sign in, and, when the sign in looks unusual, a box for the emailed code. */
    public function check(Request $request)
    {
        if ($request->session()->get('sq_pending') !== 'challenge') {
            return redirect('/');
        }
        $user = $request->user();
        if (! $request->session()->has('sq_key')) {
            $request->session()->put('sq_key', $this->sq->pick($user));
        }
        $risky = (bool) $request->session()->get('sq_risky');
        if ($risky && ! $request->session()->has('sq_code')) {
            $this->sq->sendCode($user, $request);
        }

        return view('auth.security-question', [
            'question' => SecurityQuestions::questions()[$request->session()->get('sq_key')] ?? '',
            'risky' => $risky,
            'email' => preg_replace('/(?<=.).(?=[^@]*@)/', '•', $user->email),
        ]);
    }

    public function verify(Request $request)
    {
        abort_unless($request->session()->get('sq_pending') === 'challenge', 404);
        $user = $request->user();
        $data = $request->validate(['answer' => ['required', 'string', 'max:120'], 'code' => ['nullable', 'string', 'max:10']], ['answer.required' => 'Enter your answer.']);
        $risky = (bool) $request->session()->get('sq_risky');

        $answerOk = $this->sq->check($user, (string) $request->session()->get('sq_key'), $data['answer']);
        $codeOk = ! $risky || $this->sq->codeOk($request, (string) ($data['code'] ?? ''));

        if (! $answerOk || ! $codeOk) {
            return $this->wrong($request, $user, $risky);
        }

        RateLimiter::clear('sq-wrong:'.$user->id);
        foreach (SecurityQuestions::SESSION as $k) {
            $request->session()->forget($k);
        }
        Audit::record('security_question.passed', $user, null, ['code' => $risky], $user->id);

        return app(LoginResponse::class)->toResponse($request);
    }

    public function resend(Request $request)
    {
        abort_unless($request->session()->get('sq_pending') === 'challenge' && $request->session()->get('sq_risky'), 404);
        if (now()->timestamp - (int) $request->session()->get('sq_code_sent_at', 0) < 60) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Please wait a minute before asking for another code.']);
        }
        $this->sq->sendCode($request->user(), $request);

        return back()->with('toast', ['type' => 'ok', 'message' => 'We sent a new code to your email.']);
    }

    private function wrong(Request $request, User $user, bool $risky)
    {
        $key = 'sq-wrong:'.$user->id;
        RateLimiter::hit($key, (int) config('security.lock_minutes') * 60);
        Audit::record('security_question.failed', $user, null, null, $user->id);

        if (RateLimiter::attempts($key) >= (int) config('security.max_wrong')) {
            $user->forceFill(['locked_until' => now()->addMinutes((int) config('security.lock_minutes'))])->saveQuietly();
            Audit::record('security_question.locked', $user, null, null, $user->id);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('toast', ['type' => 'error', 'message' => 'Too many wrong answers. Your sign in is paused for '.config('security.lock_minutes').' minutes.']);
        }

        return back()->withErrors(['answer' => $risky ? 'The answer or the code did not match. Check both and try again.' : 'That answer does not match.']);
    }

    /** First-time setup for a member who signed in without questions, and changes later (after the password is confirmed). */
    public function setup(Request $request)
    {
        $forced = $request->routeIs('security.questions.setup*');
        abort_if($forced && $request->session()->get('sq_pending') !== 'setup', 404);

        return view('auth.security-questions-setup', [
            'forced' => $forced,
            'questions' => SecurityQuestions::questions(),
            'need' => (int) config('security.questions_required'),
        ]);
    }

    public function save(Request $request)
    {
        $need = (int) config('security.questions_required');
        $forced = $request->routeIs('security.questions.setup*');
        abort_if($forced && $request->session()->get('sq_pending') !== 'setup', 404);
        $rules = [];
        for ($i = 1; $i <= $need; $i++) {
            $rules["question_$i"] = ['required', 'string', 'in:'.implode(',', array_keys(SecurityQuestions::questions()))];
            $rules["answer_$i"] = ['required', 'string', 'max:120'];
        }
        $data = $request->validate($rules, ['*.required' => 'Choose a question and give an answer for each one.']);
        $pairs = [];
        for ($i = 1; $i <= $need; $i++) {
            $pairs[$data["question_$i"]] = $data["answer_$i"];
        }
        if (count($pairs) !== $need) {
            return back()->withInput($request->except(['answer_1', 'answer_2', 'answer_3']))->withErrors(['questions' => 'Choose '.$need.' different questions.']);
        }
        $this->sq->save($request->user(), $pairs);
        Audit::record($forced ? 'security_question.set' : 'security_question.changed', $request->user(), null, null, $request->user()->id);

        if ($forced) {
            $request->session()->forget('sq_pending');

            return app(LoginResponse::class)->toResponse($request);
        }

        return redirect()->route($request->user()->isAdmin() ? 'admin.security' : 'security')->with('toast', ['type' => 'ok', 'message' => 'Your security questions were saved.']);
    }
}
