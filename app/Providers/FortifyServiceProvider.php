<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use App\Services\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Laravel\Fortify\Contracts\LoginResponse::class, \App\Http\Responses\StaffAwareLoginResponse::class);
        $this->app->singleton(\Laravel\Fortify\Contracts\RegisterResponse::class, \App\Http\Responses\MemberRegisterResponse::class);
        $this->app->singleton(\Laravel\Fortify\Contracts\TwoFactorLoginResponse::class, \App\Http\Responses\StaffAwareLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // The default check, plus: a member recorded as deceased can no longer sign in.
        Fortify::authenticateUsing(function (Request $request) {
            $user = \App\Models\User::where(Fortify::username(), $request->input(Fortify::username()))->first();
            if ($user && \Illuminate\Support\Facades\Hash::check((string) $request->input('password'), $user->password) && $user->status !== 'deceased') {
                return $user;
            }

            return null;
        });
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register', ['fields' => \App\Services\SiteContent::resolve(CreateNewUser::FIELDS)]));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        Event::listen(Login::class, function (Login $e) {
            if (request()->attributes->get('silent_login')) {
                return;
            }
            $e->user->forceFill(['last_login_at' => now()])->saveQuietly();
            Audit::record('login.success', $e->user, null, null, $e->user->id);
            if (! $e->user->isAdmin()) {
                \App\Services\DeviceWatch::seen($e->user, request());
            }
        });
        Event::listen(Failed::class, fn (Failed $e) => $e->user ? Audit::record('login.failed', $e->user, null, null, $e->user->id) : null);
        Event::listen(TwoFactorAuthenticationConfirmed::class, fn ($e) => Audit::record('two_factor.enabled', $e->user, null, null, $e->user->id));
        Event::listen(TwoFactorAuthenticationDisabled::class, fn ($e) => Audit::record('two_factor.disabled', $e->user, null, null, $e->user->id));

        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
