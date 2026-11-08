<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Emails the six-digit code that confirms a withdrawal.
 */
class WithdrawalCodeNotification extends Notification
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
        return (new MailMessage)
            ->subject(__(':code is your :site withdrawal code', ['code' => $this->code, 'site' => Setting::get('site_name')]))
            ->greeting(__('Confirm your withdrawal'))
            ->line(__('Enter this code on the Withdraw page to confirm your withdrawal:'))
            ->line(new HtmlString('<div class="code-box">'.e(trim(chunk_split($this->code, 3, ' '))).'</div>'))
            ->line(__('The code works for :minutes minutes.', ['minutes' => $this->minutesValid]))
            ->line(__('If you did not ask to withdraw, someone may have your password. Change it now and contact support.'));
    }
}
