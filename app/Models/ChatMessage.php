<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $meta_json
 */
class ChatMessage extends Model
{
    protected $fillable = [
        'chat_thread_id',
        'role',
        'content',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
        ];
    }

    /** @return BelongsTo<ChatThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }

    /** @return HasMany<MatchResult, $this> */
    public function matchResults(): HasMany
    {
        return $this->hasMany(MatchResult::class)->orderBy('rank');
    }
}
