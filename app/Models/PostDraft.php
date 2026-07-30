<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PostDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property string $variant
 * @property string $body
 * @property string|null $rationale
 * @property int $character_count
 * @property string|null $model
 * @property CarbonInterface|null $selected_at
 * @property-read Post $post
 */
class PostDraft extends Model
{
    /** @use HasFactory<PostDraftFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the post the draft belongs to.
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
        return ['selected_at' => 'datetime'];
    }
}
