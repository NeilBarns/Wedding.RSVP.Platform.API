<?php

namespace App\Models;

use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_READY,
        self::STATUS_SUBMITTED,
        self::STATUS_LOCKED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'wedding_id',
        'display_name',
        'contact_person_name',
        'contact_number',
        'email',
        'token_hash',
        'status',
        'internal_notes',
        'first_opened_at',
        'last_opened_at',
        'submitted_at',
        'locked_at',
        'response_contact_number',
        'response_email',
        'message_to_couple',
    ];

    protected function casts(): array
    {
        return [
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'submitted_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function rsvpRevisions(): HasMany
    {
        return $this->hasMany(RsvpRevision::class);
    }

    public function isLockedForResponse(): bool
    {
        return $this->status === self::STATUS_LOCKED || $this->locked_at !== null;
    }

    public function deadlineAllowsResponse(): bool
    {
        return $this->wedding->rsvp_deadline === null
            || today(config('app.timezone'))->lte($this->wedding->rsvp_deadline);
    }

    public function canRespond(): bool
    {
        return in_array($this->status, [
            self::STATUS_READY,
            self::STATUS_SUBMITTED,
        ], true) && ! $this->isLockedForResponse() && $this->deadlineAllowsResponse();
    }
}
