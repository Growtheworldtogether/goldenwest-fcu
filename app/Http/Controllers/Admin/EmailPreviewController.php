<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\MemberNotice;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Shows how each kind of email looks with the current branding, and sends a test email to the signed-in staff member. */
class EmailPreviewController extends Controller
{
    /** @return array<string, array{0: string, 1: string, 2: \Closure}> */
    private function samples(): array
    {
        $short = brand('short_name');
        $phone = brand('support_phone');

        return [
            'activity' => ['Account activity', 'Used for loan decisions, joint accounts, card and request updates, and similar notices.', fn ($u) => (new MemberNotice('Your loan application was approved', ['Good news: we approved your application.', 'Sign in to online banking to review the terms.'], 'Open online banking', url('/borrow')))->toMail($u)],
            'welcome' => ['Welcome', 'Sent when a member confirms their email address.', fn ($u) => (new MemberNotice('Welcome to '.$short, ['Your email address is confirmed and your online banking is ready.', 'Sign in to open accounts, set up alerts and finish verifying your identity.'], 'Go to online banking', route('dashboard'), true))->toMail($u)],
            'password' => ['Password changed', 'A security notice, always sent.', fn ($u) => (new MemberNotice('Your password was changed', ['The password for your online banking was just changed.', 'If you did not make this change, call Customer Care right away at '.$phone.'.'], null, null, true))->toMail($u)],
            'verify' => ['Confirm your email', 'The link a new member uses to confirm their email address.', fn ($u) => (new VerifyEmail)->toMail($u)],
            'reset' => ['Reset your password', 'The link a member uses after "Forgot your password?".', fn ($u) => (new ResetPassword('sample-token'))->toMail($u)],
        ];
    }

    public function index()
    {
        Gate::authorize('staff.editSite');

        return view('admin.emails', ['samples' => $this->samples(), 'from' => config('mail.from.address'), 'mailer' => config('mail.default')]);
    }

    public function show(string $key)
    {
        Gate::authorize('staff.editSite');
        $sample = $this->samples()[$key] ?? abort(404);

        return response((string) $sample[2](auth()->user())->render());
    }

    public function test(Request $request)
    {
        Gate::authorize('staff.editSite');
        $user = $request->user();
        try {
            $user->notify(new MemberNotice('Test email from '.brand('short_name'), ['This is a test email. If you can read it, your email settings work.', 'The name, colours, logo and contact details here come from the Branding page.'], 'Open the dashboard', route('dashboard'), true));
        } catch (\Throwable $e) {
            return back()->with('error', 'The test email could not be sent. Check the mail settings. '.\Illuminate\Support\Str::limit($e->getMessage(), 140));
        }

        return back()->with('sent', config('mail.default') === 'log' ? 'Email is set to log, so nothing was delivered. It was written to the log file.' : 'Test email sent to '.$user->email.'.');
    }
}
