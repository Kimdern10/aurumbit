<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\WithdrawalCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * The security code a user must give to withdraw: the code from their
 * authenticator app when two-step login is on, otherwise a six-digit code
 * emailed to them. Emailed codes are stored only as a hash, last 10 minutes
 * and stop working after 5 wrong tries.
 */
class WithdrawalCode
{
    public const int MINUTES_VALID = 10;

    public const int MAX_ATTEMPTS = 5;

    /**
     * Whether this user confirms withdrawals with their authenticator app.
     */
    public static function usesAuthenticator(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && filled($user->two_factor_secret);
    }

    /**
     * Email a new code (for users without two-step login).
     */
    public static function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::key($user), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::MINUTES_VALID));

        $user->notify(new WithdrawalCodeNotification($code, self::MINUTES_VALID));
    }

    /**
     * Check a typed code. An emailed code is used up once it is accepted.
     */
    public static function verify(User $user, string $code): bool
    {
        $code = (string) preg_replace('/\D/', '', $code);

        if (self::usesAuthenticator($user)) {
            return app(TwoFactorAuthenticationProvider::class)->verify(decrypt((string) $user->two_factor_secret), $code);
        }

        $stored = Cache::get(self::key($user));

        if (! is_array($stored) || $stored['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $stored['hash'])) {
            Cache::put(self::key($user), [...$stored, 'attempts' => $stored['attempts'] + 1], now()->addMinutes(self::MINUTES_VALID));

            return false;
        }

        Cache::forget(self::key($user));

        return true;
    }

    private static function key(User $user): string
    {
        return 'withdrawal-code:'.$user->id;
    }
}
