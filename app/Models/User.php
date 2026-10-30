<?php

namespace App\Models;

use App\Enums\InvestmentStatus;
use App\Enums\KycStatus;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property bool $is_suspended
 * @property int $balance_sats
 * @property string $referral_code
 * @property int|null $referred_by_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonInterface|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User|null $referrer
 * @property-read KycSubmission|null $latestKyc
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_admin' => false, 'is_suspended' => false, 'balance_sats' => 0];

    /**
     * Give every new user a referral code.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->referral_code)) {
                do {
                    $code = Str::upper(Str::random(8));
                } while (static::query()->where('referral_code', $code)->exists());

                $user->referral_code = $code;
            }
        });

        // Identity documents are personal data: remove them with the account.
        static::deleting(function (User $user): void {
            Storage::disk('local')->deleteDirectory('kyc/'.$user->id);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
            'balance_sats' => 'integer',
        ];
    }

    /**
     * @return HasMany<Deposit, $this>
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    /**
     * @return HasMany<Withdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * @return HasMany<Investment, $this>
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * @return HasMany<KycSubmission, $this>
     */
    public function kycSubmissions(): HasMany
    {
        return $this->hasMany(KycSubmission::class);
    }

    /**
     * @return HasOne<KycSubmission, $this>
     */
    public function latestKyc(): HasOne
    {
        return $this->hasOne(KycSubmission::class)->latestOfMany();
    }

    /**
     * The user who invited this one.
     *
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    /**
     * Users this one invited.
     *
     * @return HasMany<User, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_id');
    }

    /**
     * The status of the user's most recent identity check, if any.
     */
    public function kycStatus(): ?KycStatus
    {
        return $this->latestKyc?->status;
    }

    public function isKycVerified(): bool
    {
        return $this->kycStatus() === KycStatus::Approved;
    }

    /**
     * Total satoshis currently placed in active plans.
     */
    public function investedSats(): int
    {
        return (int) $this->investments()->where('status', InvestmentStatus::Active)->sum('amount_sats');
    }

    /**
     * The link a user shares to invite others.
     */
    public function referralLink(): string
    {
        return route('register', ['ref' => $this->referral_code]);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
