<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ResetPlatform extends Command
{
    protected $signature = 'platform:reset {--force : Skip the confirmation}';

    protected $description = 'Erase everything (members, staff, ledger, branding, uploads) and start the setup wizard again';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This deletes ALL data in the database, including members and the ledger. Continue?')) {
            return self::FAILURE;
        }
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This is a production site. Run with --force if you really mean it.');

            return self::FAILURE;
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
        foreach ([config('setup.lock'), config('setup.key_file')] as $f) {
            @unlink($f);
        }
        foreach (glob(storage_path('app/private/*')) ?: [] as $f) {
            is_dir($f) ? $this->rrmdir($f) : @unlink($f);
        }
        Artisan::call('cache:clear');
        $this->info('The platform is empty. Open the site in a browser to run the setup wizard.');

        return self::SUCCESS;
    }

    private function rrmdir(string $dir): void
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..', '.gitignore']) as $f) {
            is_dir("$dir/$f") ? $this->rrmdir("$dir/$f") : @unlink("$dir/$f");
        }
        @rmdir($dir);
    }
}
