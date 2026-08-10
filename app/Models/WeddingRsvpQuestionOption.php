<?php

namespace App\Models;

use App\Enums\RsvpQuestionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingRsvpQuestionOption extends Model
{
    protected $fillable = [
        'wedding_id',
        'question_key',
        'label',
        'value',
        'sort_order',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'question_key' => RsvpQuestionKey::class,
            'sort_order' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }
}
