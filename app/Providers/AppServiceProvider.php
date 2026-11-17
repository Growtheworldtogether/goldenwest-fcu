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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['lookupMembers', 'postDeposits', 'lockAccounts', 'viewAudit', 'answerInquiries', 'toggleFeatures', 'manageAdmin', 'decideTransaction', 'viewQueue', 'adjustBalance', 'canAdjust', 'impersonate'] as $ability) {
            \Illuminate\Support\Facades\Gate::define('staff.'.$ability, [\App\Policies\StaffPolicy::class, $ability]);
        }

        // @money($cents) prints integer cents as $1,234.56
        Blade::directive('money', fn ($expression) => "<?php echo e(\\App\\Support\\Money::cents((int) ({$expression}))->format()); ?>");
    }
}
