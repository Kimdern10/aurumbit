<?php

namespace App\Models;

use App\Enums\PlanCategory;
use Carbon\CarbonInterface;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-defined investment plan. The target return is indicative only and
 * is used to simulate earnings in demo mode; it is never a promise.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property PlanCategory $category
 * @property string $summary
 * @property int $min_sats
 * @property int|null $max_sats
 * @property int $duration_days
 * @property int $target_return_bps
 * @property string $risk_level
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'slug', 'category', 'summary', 'min_sats', 'max_sats', 'duration_days', 'target_return_bps', 'risk_level', 'is_active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    public const array RISK_LEVELS = ['medium' => 'Medium', 'high' => 'High', 'very_high' => 'Very high'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PlanCategory::class,
            'min_sats' => 'integer',
            'max_sats' => 'integer',
            'duration_days' => 'integer',
            'target_return_bps' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Investment, $this>
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * Only plans users can choose, in the admin's order.
     *
     * @param  Builder<Plan>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The indicative target return as a percentage string, e.g. "4.5".
     */
    public function targetPercent(): string
    {
        return rtrim(rtrim(number_format($this->target_return_bps / 100, 2, '.', ''), '0'), '.');
    }

    public function riskLabel(): string
    {
        return self::RISK_LEVELS[$this->risk_level] ?? ucfirst($this->risk_level);
    }
}
