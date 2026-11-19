<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserSecurityAnswer;
use App\Notifications\MemberNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Security questions at sign in. A member picks three questions when they join. At every sign in one of the three is
 * asked, never the one asked last time. A sign in that looks unusual (a new device, a new network, or several wrong
 * passwords first) also needs a code sent to the member's email.
 */
class SecurityQuestions
{
    public const SESSION = ['sq_pending', 'sq_key', 'sq_risky', 'sq_code', 'sq_code_expires', 'sq_code_sent_at'];

    public static function enabled(): bool
    {
        return Setting::bool('security_questions', (bool) config('security.questions_default'));
    }

    public static function questions(): array
    {
        return config('security.questions');
    }

    public function has(User $user): bool
    {
        return UserSecurityAnswer::where('user_id', $user->id)->count() >= (int) config('security.questions_required');
    }

    /** What the answer is compared as: lower case letters and digits only, so "St. Louis" and "st louis" match. */
    public static function normalize(string $answer): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($answer)) ?? '';
    }

    /** @param array<string,string> $pairs question key => answer */
    public function save(User $user, array $pairs): void
    {
        $need = (int) config('security.questions_required');
        if (count($pairs) !== $need) {
            throw ValidationException::withMessages(['questions' => 'Choose '.$need.' different questions.']);
        }
        foreach ($pairs as $key => $answer) {
            if (! isset(self::questions()[$key])) {
                throw ValidationException::withMessages(['questions' => 'Choose your questions from the list.']);
            }
            if (strlen(self::normalize((string) $answer)) < 2) {
                throw ValidationException::withMessages(['questions' => 'Each answer needs at least 2 letters or numbers.']);
            }
        }
        DB::transaction(function () use ($user, $pairs) {
            UserSecurityAnswer::where('user_id', $user->id)->delete();
            foreach ($pairs as $key => $answer) {
                UserSecurityAnswer::create(['user_id' => $user->id, 'question_key' => $key, 'answer_hash' => Hash::make(self::normalize((string) $answer))]);
            }
            $user->forceFill(['security_last_question' => null])->saveQuietly();
        });
    }

    public function check(User $user, string $key, string $answer): bool
    {
        $row = UserSecurityAnswer::where('user_id', $user->id)->where('question_key', $key)->first();

        return $row !== null && Hash::check(self::normalize($answer), $row->answer_hash);
    }

    /** One of the member's questions, random, and not the one asked at the last sign in. */
    public function pick(User $user): string
    {
        $keys = UserSecurityAnswer::where('user_id', $user->id)->pluck('question_key')->all();
        $pool = array_values(array_diff($keys, [$user->security_last_question]));
        $pool = $pool ?: $keys;
        $key = $pool[random_int(0, count($pool) - 1)];
        $user->forceFill(['security_last_question' => $key])->saveQuietly();

        return $key;
    }

    /** True when this sign in is out of the ordinary. Remembers the network and device either way. */
    public function suspicious(User $user, Request $request): bool
    {
        $newDevice = DeviceWatch::seen($user, $request);
        $ip = (string) $request->ip();
        $knownIps = DB::table('login_ips')->where('user_id', $user->id)->exists();
        $row = DB::table('login_ips')->where('user_id', $user->id)->where('ip', $ip)->first();
        if ($row) {
            DB::table('login_ips')->where('id', $row->id)->update(['last_seen_at' => now()]);
        } else {
            DB::table('login_ips')->insert(['user_id' => $user->id, 'ip' => $ip, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        }
        $newIp = $knownIps && ! $row; // the very first sign in is not news
        $failures = \App\Models\AuditLog::where('action', 'login.failed')->where('subject_type', class_basename($user))->where('subject_id', $user->id)
            ->where('created_at', '>=', now()->subHour())->count();

        return $newDevice || $newIp || $failures >= (int) config('security.failed_logins_suspicious');
    }

    public function sendCode(User $user, Request $request): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $request->session()->put('sq_code', Hash::make($code));
        $request->session()->put('sq_code_expires', now()->addMinutes((int) config('security.code_minutes'))->timestamp);
        $request->session()->put('sq_code_sent_at', now()->timestamp);
        MemberNotice::send($user, 'Your '.brand('short_name').' sign-in code', [
            'Your sign-in code is '.$code.'. It works for '.config('security.code_minutes').' minutes.',
            'We ask for a code when a sign-in looks different from your usual ones. If you did not try to sign in, change your password and call Customer Care at '.brand('support_phone').'.',
        ], null, null, true);
    }

    public function codeOk(Request $request, string $code): bool
    {
        $hash = $request->session()->get('sq_code');
        if (! $hash || now()->timestamp > (int) $request->session()->get('sq_code_expires', 0)) {
            return false;
        }

        return Hash::check(trim($code), $hash);
    }
}
