<?php

namespace App\Models;

use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory;

    public const TYPE_ADULT = 'adult';

    public const TYPE_CHILD = 'child';

    public const TYPE_INFANT = 'infant';

    public const TYPE_ENTOURAGE = 'entourage';

    public const TYPE_PRINCIPAL_SPONSOR = 'principal_sponsor';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_ADULT,
        self::TYPE_CHILD,
        self::TYPE_INFANT,
        self::TYPE_ENTOURAGE,
        self::TYPE_PRINCIPAL_SPONSOR,
        self::TYPE_OTHER,
    ];

    public const ATTENDANCE_PENDING = 'pending';

    public const ATTENDANCE_ATTENDING = 'attending';

    public const ATTENDANCE_DECLINED = 'declined';

    public const ATTENDANCE_STATUSES = [
        self::ATTENDANCE_PENDING,
        self::ATTENDANCE_ATTENDING,
        self::ATTENDANCE_DECLINED,
    ];

    protected $fillable = [
        'invitation_id',
        'full_name',
        'guest_type',
        'attendance_status',
        'dietary_requirements',
        'accessibility_requirements',
        'meal_choice',
        'sort_order',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
