<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class VerifyEmail extends Command
{
    protected $signature = 'members:verify-email {email : The address to mark as confirmed}';

    protected $description = 'Mark a user\'s email address as confirmed without sending a link (useful before mail is set up).';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('No user has that email address.');

            return self::FAILURE;
        }
        if ($user->hasVerifiedEmail()) {
            $this->info('That address is already confirmed.');

            return self::SUCCESS;
        }
        $user->markEmailAsVerified();
        $this->info("Confirmed {$user->email}.");

        return self::SUCCESS;
    }
}
