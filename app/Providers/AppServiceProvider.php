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
        foreach (['lookupMembers', 'postDeposits', 'lockAccounts', 'viewAudit', 'answerInquiries', 'toggleFeatures', 'manageAdmin', 'editSite', 'reviewKyc', 'waivePayeeWait', 'decideTransaction', 'viewQueue', 'adjustBalance', 'canAdjust', 'impersonate', 'approveCards', 'approveLoans'] as $ability) {
            \Illuminate\Support\Facades\Gate::define('staff.'.$ability, [\App\Policies\StaffPolicy::class, $ability]);
        }

        // The credit union's name drives the app name (2FA app label, mail footers) and the mail sender name.
        try {
            config(['app.name' => brand('name'), 'mail.from.name' => brand('sender_name')]);
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

        // @money($cents) prints integer cents as $1,234.56
        Blade::directive('money', fn ($expression) => "<?php echo e(\\App\\Support\\Money::cents((int) ({$expression}))->format()); ?>");
    }
}
