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
 * @property int $amount_micros
 * @property int $target_return_bps
 * @property int $duration_days
 * @property int $earned_micros
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
#[Fillable(['user_id', 'plan_id', 'amount_micros', 'target_return_bps', 'duration_days', 'gold_mg', 'starts_at', 'ends_at'])]
class Investment extends Model
{
    /** @use HasFactory<InvestmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'active', 'earned_micros' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_micros' => 'integer',
            'target_return_bps' => 'integer',
            'duration_days' => 'integer',
            'earned_micros' => 'integer',
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
     * Earnings for the days elapsed: the plan rate spread evenly
     * over the term, rounded down to whole micro-USDT. Integer maths only.
     */
    public function earningsAt(?CarbonInterface $at = null): int
    {
        return intdiv($this->amount_micros * $this->target_return_bps * $this->elapsedDays($at), 10000 * $this->duration_days);
    }

    /**
     * What the customer has earned so far. For a running plan this is worked
     * out from the days that have passed, so it is right even before the
     * scheduled job has saved it.
     */
    public function earnedSoFar(): int
    {
        return $this->status === InvestmentStatus::Active
            ? max($this->earned_micros, $this->earningsAt())
            : $this->earned_micros;
    }

    /**
     * What one full day of the term earns.
     */
    public function dailyEarning(): int
    {
        return intdiv($this->amount_micros * $this->target_return_bps, 10000 * max(1, $this->duration_days));
    }

    /**
     * The profit for the whole term, paid with the capital at the end.
     */
    public function totalProfit(): int
    {
        return intdiv($this->amount_micros * $this->target_return_bps, 10000);
    }

    /**
     * When the next day of earnings is added, or null once the term is over.
     */
    public function nextEarningAt(): ?CarbonInterface
    {
        if ($this->status !== InvestmentStatus::Active || $this->elapsedDays() >= $this->duration_days) {
            return null;
        }

        return $this->starts_at->copy()->addDays($this->elapsedDays() + 1);
    }

    /**
     * Progress through the term from 0 to 100.
     */
    public function progressPercent(): int
    {
        return $this->duration_days > 0 ? (int) floor($this->elapsedDays() * 100 / $this->duration_days) : 100;
    }
}
