<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A notice the admin shows on every user's dashboard.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string $level
 * @property bool $is_published
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['title', 'body', 'level', 'is_published'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    public const array LEVELS = ['info' => 'Information', 'warning' => 'Important', 'success' => 'Good news'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)->latest();
    }

    /**
     * The Flux callout colour for this level.
     */
    public function calloutColor(): string
    {
        return match ($this->level) {
            'warning' => 'amber',
            'success' => 'green',
            default => 'sky',
        };
    }
}
