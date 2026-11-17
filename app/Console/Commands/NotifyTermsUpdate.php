<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\MemberNotice;
use Illuminate\Console\Command;

class NotifyTermsUpdate extends Command
{
    protected $signature = 'members:notify-terms {effective : The date the new terms take effect, for example 2027-01-15}';

    protected $description = 'Email every active member that the Terms of Use or Privacy notice are changing';

    public function handle(): int
    {
        $date = \Illuminate\Support\Carbon::parse($this->argument('effective'));
        $n = 0;
        User::where('status', 'active')->whereNotNull('email')->get()->reject->isAdmin()->each(function (User $u) use ($date, &$n) {
            MemberNotice::send($u, 'Changes to our Terms of Use and Privacy notice', [
                'We are updating our Terms of Use and Privacy notice. The changes take effect on '.$date->format('F j, Y').'.',
                'You can read the new versions on our website. If you keep using online banking after that date, the new terms apply.',
            ], 'Read the terms', url('/terms'), true);
            $n++;
        });
        $this->info("Sent to {$n} members.");

        return self::SUCCESS;
    }
}
