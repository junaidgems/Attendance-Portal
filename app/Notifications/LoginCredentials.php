<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Portal login details (URL, email and password) for an employee, sent from Admin → Employees.
 * Encrypted while it waits in the queue, so the password is never stored in plain text in the "jobs" table.
 */
class LoginCredentials extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $email,
        public string $password,
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
        return (new MailMessage)
            ->subject('Your '.config('app.name').' login details')
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line('Your account on the attendance portal is ready. Use these details to log in:')
            ->line('**Portal:** '.route('login'))
            ->line('**Email:** '.$this->email)
            ->line('**Password:** '.$this->password)
            ->action('Log in to the Portal', route('login'))
            ->line('Please keep these details private and do not share your password with anyone.');
    }
}
