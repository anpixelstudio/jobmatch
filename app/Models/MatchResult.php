<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int|string, mixed>|null $reasons_json
 * @property-read Candidate|null $candidate
 */
class MatchResult extends Model
{
    protected $fillable = [
        'chat_message_id',
        'candidate_id',
        'score',
        'rank',
        'reasons_json',
    ];

    protected function casts(): array
    {
        return [
            'reasons_json' => 'array',
        ];
    }

    /** @return BelongsTo<ChatMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
