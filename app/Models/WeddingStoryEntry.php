<?php

namespace App\Models;

use Database\Factories\WeddingStoryEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingStoryEntry extends Model
{
    /** @use HasFactory<WeddingStoryEntryFactory> */
    use HasFactory;

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
