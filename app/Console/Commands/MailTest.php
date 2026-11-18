<?php

namespace App\Console\Commands;

use App\Notifications\MemberNotice;
use App\Models\User;
use Illuminate\Console\Command;

class MailTest extends Command
{
    protected $signature = 'mail:test {email : Where to send the test email}';

    protected $description = 'Send a test email to check the mail settings';

    public function handle(): int
    {
        $to = $this->argument('email');
        $this->info('Mailer: '.config('mail.default').' | From: '.config('mail.from.name').' <'.config('mail.from.address').'>');
        try {
            (new User(['first_name' => 'there', 'name' => 'there', 'email' => $to]))->notify(new MemberNotice('Test email from '.brand('short_name'), ['This is a test email. If you can read it, your email settings work.'], null, null, true));
        } catch (\Throwable $e) {
            $this->error('Could not send: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info(config('mail.default') === 'log' ? 'Mailer is "log": the email was written to storage/logs, not delivered.' : 'Sent to '.$to.'.');

        return self::SUCCESS;
    }
}
