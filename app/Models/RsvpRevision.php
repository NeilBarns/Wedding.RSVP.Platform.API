<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsvpRevision extends Model
{
    protected $fillable = [
        'invitation_id',
        'revision_number',
        'response_snapshot',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'response_snapshot' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
