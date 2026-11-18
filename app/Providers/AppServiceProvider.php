<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['lookupMembers', 'postDeposits', 'lockAccounts', 'viewAudit', 'answerInquiries', 'toggleFeatures', 'manageAdmin', 'editSite', 'reviewKyc', 'waivePayeeWait', 'decideTransaction', 'viewQueue', 'adjustBalance', 'canAdjust', 'impersonate', 'approveCards', 'approveLoans', 'approveCrypto', 'approveJoint'] as $ability) {
            \Illuminate\Support\Facades\Gate::define('staff.'.$ability, [\App\Policies\StaffPolicy::class, $ability]);
        }

        // Request limits. Money-moving forms also carry their own, tighter limit in routes/web.php.
        \Illuminate\Support\Facades\RateLimiter::for('portal', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(180)->by($r->user()?->id ?: $r->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('staff', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(300)->by($r->user()?->id ?: $r->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('auth-forms', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(8)->by($r->ip()));

        // The credit union's name drives the app name (2FA app label, mail footers) and the mail sender name.
        try {
            config(['app.name' => brand('name'), 'mail.from.name' => brand('sender_name')]);
            // The From address is the credit union's support email unless MAIL_FROM_ADDRESS is set in .env.
            if (config('mail.from.address') === 'hello@example.com') {
                config(['mail.from.address' => brand('support_email')]);
            }
        } catch (\Throwable) {
            // Before the first migration the defaults in config apply.
        }

        // The portal top bar shows the previous sign-in, like a bank does.
        \Illuminate\Support\Facades\View::composer('layouts.portal', function ($view) {
            $user = auth()->user();
            $last = null;
            if ($user && ! session()->has('impersonation')) {
                $at = \App\Models\AuditLog::where('actor_id', $user->id)->where('action', 'login.success')->latest('id')->skip(1)->value('created_at');
                $last = $at ? \Illuminate\Support\Carbon::parse($at) : null;
            }
            $view->with('lastSignIn', $last);
        });

        // The two built-in messages, worded like the rest of our email.
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Confirm your email address')
                ->greeting('Hello '.($notifiable->first_name ?: $notifiable->name).',')
                ->line('Please confirm your email address to finish setting up your online banking.')
                ->action('Confirm email address', $url)
                ->line('The link works for '.config('auth.verification.expire', 60).' minutes. If you did not sign up with '.brand('short_name').', you can ignore this email.')
                ->salutation('The '.brand('short_name').' team');
        });
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Reset your password')
                ->greeting('Hello '.($notifiable->first_name ?: $notifiable->name).',')
                ->line('We received a request to reset the password for your online banking.')
                ->action('Reset password', $url)
                ->line('The link works for '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes. If you did not ask for this, you do not need to do anything and your password stays the same.')
                ->salutation('The '.brand('short_name').' team');
        });

        // A member who confirms their email gets a short welcome.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Verified::class, function ($event) {
            if (method_exists($event->user, 'isAdmin') && $event->user->isAdmin()) {
                return;
            }
            \App\Notifications\MemberNotice::send($event->user, 'Welcome to '.brand('short_name'), ['Your email address is confirmed and your online banking is ready.', 'Sign in to open accounts, set up alerts and finish verifying your identity.'], 'Go to online banking', route('dashboard'), true);
        });

        // @money($cents) prints integer cents as $1,234.56
        Blade::directive('money', fn ($expression) => "<?php echo e(\\App\\Support\\Money::cents((int) ({$expression}))->format()); ?>");
    }
}
