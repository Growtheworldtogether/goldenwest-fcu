<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a member there is a reply. The message itself is never in the email: they sign in to read it. */
class TicketReplied extends Notification
{
    public function __construct(public Ticket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New reply on your '.brand('short_name').' support request '.$this->ticket->reference)
            ->greeting('Hello '.($notifiable->first_name ?? $notifiable->name).',')
            ->line('We replied to your support request '.$this->ticket->reference.'.')
            ->line('For your security we do not put the message in this email. Sign in to online banking to read it.')
            ->action('Read the reply', route('tickets.show', $this->ticket->reference))
            ->line(brand('short_name').' will never ask for your password or a one-time code by email, phone or text.')
            ->salutation('The '.brand('short_name').' team');
    }
}
