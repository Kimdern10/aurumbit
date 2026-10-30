<?php

namespace App\Models;

use App\Enums\InvestmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\InvestmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money a user has placed in a plan. The plan's terms are copied on creation so
 * later edits to the plan never change a running investment.
 *
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property int $amount_sats
 * @property int $target_return_bps
 * @property int $duration_days
 * @property int $earned_sats
 * @property int|null $gold_mg
 * @property InvestmentStatus $status
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property CarbonInterface|null $settled_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $user
 * @property-read Plan $plan
 */
#[Fillable(['user_id', 'plan_id', 'amount_sats', 'target_return_bps', 'duration_days', 'gold_mg', 'starts_at', 'ends_at'])]
class Investment extends Model
{
    /** @use HasFactory<InvestmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active', 'earned_sats' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_sats' => 'integer',
            'target_return_bps' => 'integer',
            'duration_days' => 'integer',
            'earned_sats' => 'integer',
            'gold_mg' => 'integer',
            'status' => InvestmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Whole days that have passed in the term, capped at the term length.
     */
    public function elapsedDays(?CarbonInterface $at = null): int
    {
        $at ??= now();

        if ($at->lessThanOrEqualTo($this->starts_at)) {
            return 0;
        }

        return min($this->duration_days, (int) floor($this->starts_at->diffInDays($at)));
    }

    /**
     * Simulated earnings for the days elapsed: the indicative target spread evenly
     * over the term, rounded down to whole satoshis. Integer maths only.
     */
    public function simulatedEarningsAt(?CarbonInterface $at = null): int
    {
        return intdiv($this->amount_sats * $this->target_return_bps * $this->elapsedDays($at), 10000 * $this->duration_days);
    }

    /**
     * Progress through the term from 0 to 100.
     */
    public function progressPercent(): int
    {
        return $this->duration_days > 0 ? (int) floor($this->elapsedDays() * 100 / $this->duration_days) : 100;
    }
}
