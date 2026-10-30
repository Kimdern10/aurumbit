<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * An in-app message shown under the bell in the user's dashboard, e.g.
 * "Your deposit was credited". Stored in the database; no email is needed.
 */
class AccountActivity extends Notification
{
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
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, url: string|null, tone: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tone' => $this->tone,
        ];
    }
}
