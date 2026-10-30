<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $support_ticket_id
 * @property int|null $user_id
 * @property bool $from_staff
 * @property string $body
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read SupportTicket $ticket
 */
#[Fillable(['user_id', 'from_staff', 'body'])]
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_staff' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
