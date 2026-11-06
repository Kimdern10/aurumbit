<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Six-digit codes that prove a user owns their email address.
 *
 * Only a hash of the code is kept (in the cache), it expires after 15 minutes,
 * and it stops working after 5 wrong tries, so it cannot be guessed. Sending a
 * new code replaces the old one.
 */
class EmailVerificationCode
{
    public const int MINUTES_VALID = 15;

    public const int MAX_ATTEMPTS = 5;

    /**
     * Make a new code, store its hash and email it to the user.
     */
    public static function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::key($user), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::MINUTES_VALID));

        $user->notify(new VerifyEmailCode($code, self::MINUTES_VALID));
    }

    /**
     * Check a typed code. On success the email is marked as verified and the code is used up.
     */
    public static function verify(User $user, string $code): bool
    {
        $stored = Cache::get(self::key($user));

        if (! is_array($stored) || $stored['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check(preg_replace('/\D/', '', $code), $stored['hash'])) {
            Cache::put(self::key($user), [...$stored, 'attempts' => $stored['attempts'] + 1], now()->addMinutes(self::MINUTES_VALID));

            return false;
        }

        Cache::forget(self::key($user));

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return true;
    }

    /**
     * Whether the user still has a code they can try (sent, not expired, tries left).
     */
    public static function isUsable(User $user): bool
    {
        $stored = Cache::get(self::key($user));

        return is_array($stored) && $stored['attempts'] < self::MAX_ATTEMPTS;
    }

    private static function key(User $user): string
    {
        return 'email-verification-code:'.$user->id;
    }
}
