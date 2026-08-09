<?php

namespace App\Models;

use Database\Factories\WeddingEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingEvent extends Model
{
    /** @use HasFactory<WeddingEventFactory> */
    use HasFactory;

    public const TYPES = ['ceremony', 'reception', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['event_date' => 'date', 'is_published' => 'boolean'];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }
}
