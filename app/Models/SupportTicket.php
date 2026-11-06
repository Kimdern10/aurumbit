<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A help request from a user, answered by staff in the admin area.
 *
 * Status: "open" (waiting for staff), "answered" (waiting for the user) or "closed".
 *
 * @property int $id
 * @property int $user_id
 * @property string $subject
 * @property string $status
 * @property CarbonInterface|null $last_reply_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'subject'])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    public const array STATUSES = ['open' => 'Waiting for support', 'answered' => 'Answered', 'closed' => 'Closed'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'open'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
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
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest('id');
    }

    /**
     * Add a message, with an optional file, and move the ticket to the right
     * status. Files are kept privately under the customer's folder so they
     * are removed with the account.
     */
    public function reply(User $author, string $body, ?UploadedFile $attachment = null): TicketMessage
    {
        $fromStaff = $author->is_admin && $author->id !== $this->user_id;

        $message = $this->messages()->create([
            'user_id' => $author->id,
            'from_staff' => $fromStaff,
            'body' => $body,
            'attachment_path' => $attachment?->store('support/'.$this->user_id, 'local') ?: null,
            'attachment_name' => $attachment ? Str::limit($attachment->getClientOriginalName(), 120) : null,
        ]);

        $this->forceFill(['status' => $fromStaff ? 'answered' : 'open', 'last_reply_at' => now()])->save();

        return $message;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'open' => 'amber',
            'answered' => 'green',
            default => 'zinc',
        };
    }
}
