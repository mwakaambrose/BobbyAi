<?php

namespace App\Models;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMessage extends Model
{
    /** @use HasFactory<\Database\Factories\PostMessageFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the post the message belongs to.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'kind' => MessageKind::class,
            'payload' => 'array',
        ];
    }
}
