<?php

namespace App\Models;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use Database\Factories\PostMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property MessageRole $role
 * @property MessageKind $kind
 * @property string|null $body
 * @property array<string, mixed>|null $payload
 * @property-read Post $post
 */
class PostMessage extends Model
{
    /** @use HasFactory<PostMessageFactory> */
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
