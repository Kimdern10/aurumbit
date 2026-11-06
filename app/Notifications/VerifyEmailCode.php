<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the six-digit code a new user types in to verify their email address.
 */
class VerifyEmailCode extends Notification
{
    public function __construct(public string $code, public int $minutesValid) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteName = Setting::get('site_name');

        return (new MailMessage)
            ->subject(__(':code is your :site verification code', ['code' => $this->code, 'site' => $siteName]))
            ->greeting(__('Verify your email'))
            ->line(__('Enter this code on the :site verification page to open your account:', ['site' => $siteName]))
            ->line('**'.chunk_split($this->code, 3, ' ').'**')
            ->line(__('The code works for :minutes minutes. If you did not create an account, you can ignore this email.', ['minutes' => $this->minutesValid]));
    }
}
