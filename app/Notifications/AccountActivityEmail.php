<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email copy of an AccountActivity notification, e.g. "Deposit credited".
 * Queued, so with a real queue (QUEUE_CONNECTION=database plus a worker) the
 * email is sent in the background instead of during the page request.
 */
class AccountActivityEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'success'|'danger'|'info'  $tone
     */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $tone = 'info',
    ) {}

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
        $message = (new MailMessage)
            ->subject($this->title.' · '.Setting::get('site_name'))
            ->greeting($this->title)
            ->line($this->body);

        if ($this->tone === 'danger') {
            $message->error();
        }

        if ($this->url) {
            $message->action(__('Open your account'), $this->url);
        }

        return $message->line(__('You can turn these emails off on the Notifications page of your account.'));
    }
}
