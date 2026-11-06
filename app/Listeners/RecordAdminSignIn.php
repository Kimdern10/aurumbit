<?php

namespace App\Listeners;

use App\Models\AdminSignIn;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Keeps a record of every admin sign-in, so the owner can see who logged
 * into the admin panel, when, and from where.
 */
class RecordAdminSignIn
{
    public function __construct(private Request $request) {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! $event->user->is_admin) {
            return;
        }

        AdminSignIn::query()->create([
            'user_id' => $event->user->id,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 490),
        ]);
    }
}
