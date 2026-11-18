<?php

namespace App\Notifications;

use App\Http\Controllers\Portal\ProfileController;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One plain email for account activity. It says that something happened and sends the member to sign in for the details,
 * so no amounts, addresses or reasons travel by email. Security notices always go out; the rest follow the member's
 * "Deposits, transfers and payments" email preference.
 */
class MemberNotice extends Notification
{
    /** @param list<string> $lines */
    public function __construct(
        public string $subject,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public bool $security = false,
    ) {
    }

    public static function send(?User $member, string $subject, array $lines, ?string $actionText = null, ?string $actionUrl = null, bool $security = false): void
    {
        if ($member && $member->email && $member->status !== 'deceased') {
            $member->notify(new self($subject, $lines, $actionText, $actionUrl, $security));
        }
    }

    public function via(object $notifiable): array
    {
        $prefs = array_merge(ProfileController::DEFAULTS, $notifiable->notification_preferences ?? []);

        return $this->security || $prefs['email_transactions'] ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->greeting('Hello '.($notifiable->first_name ?? $notifiable->name).',');
        foreach ($this->lines as $line) {
            $mail->line($line);
        }
        if ($this->actionText && $this->actionUrl) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        return $mail->salutation('The '.brand('short_name').' team');
    }
}
