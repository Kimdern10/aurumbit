<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\AccountActivity;
use App\Notifications\AccountActivityEmail;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Emails every in-app account notification (deposits, withdrawals, plans,
 * identity checks...) to users who keep email notifications on.
 *
 * The email goes out only after the database change is saved, and a mail
 * problem is logged instead of thrown, so it can never undo or block a
 * balance change.
 */
class EmailAccountActivity
{
    /**
     * Handle the event.
     */
    public function handle(NotificationSent $event): void
    {
        $user = $event->notifiable;

        if (! $event->notification instanceof AccountActivity || $event->channel !== 'database' || ! $user instanceof User || ! $user->email_notifications) {
            return;
        }

        $activity = $event->notification;

        DB::afterCommit(function () use ($user, $activity): void {
            try {
                $user->notify(new AccountActivityEmail($activity->title, $activity->body, $activity->url, $activity->tone));
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
