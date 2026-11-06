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
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read SupportTicket $ticket
 */
#[Fillable(['user_id', 'from_staff', 'body', 'attachment_path', 'attachment_name'])]
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

    /**
     * Where to open the attached file, or null when there is none.
     */
    public function attachmentUrl(bool $forAdmin = false): ?string
    {
        if ($this->attachment_path === null) {
            return null;
        }

        return route($forAdmin ? 'admin.tickets.attachment' : 'support.attachment', $this);
    }
}
