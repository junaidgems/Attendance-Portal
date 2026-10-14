<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email copy of a portal (bell) notification: request received, decision made, attendance alert.
 * Queued when a queue is set up (see PortalNotification::sendEmails), otherwise sent immediately.
 */
class PortalMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $link = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($this->body);

        if ($this->link) {
            $mail->action('Open in Portal', $this->link);
        }

        return $mail->line('This is an automatic message from the attendance portal.');
    }
}
