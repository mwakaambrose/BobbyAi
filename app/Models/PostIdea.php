<?php

namespace App\Models;

use App\Enums\IdeaKind;
use App\Enums\KindSource;
use Carbon\CarbonInterface;
use Database\Factories\PostIdeaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string $body
 * @property IdeaKind|null $kind
 * @property KindSource|null $kind_source
 * @property int $position
 * @property CarbonInterface|null $used_at
 * @property int|null $post_id
 * @property-read Team $team
 * @property-read Post|null $post
 */
class PostIdea extends Model
{
    /** @use HasFactory<PostIdeaFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the team that owns the idea.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the post the idea was drafted into.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Scope the query to ideas that have not been drafted yet.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeUnused(Builder $query): void
    {
        $query->whereNull('used_at');
    }

    /**
     * Determine whether the classifier may set the kind on this idea.
     */
    public function acceptsClassification(): bool
    {
        return $this->kind_source === null || $this->kind_source->isOverwritable();
    }

    /**
     * Mark the idea as drafted into the given post.
     */
    public function markUsedFor(Post $post): void
    {
        $this->forceFill(['used_at' => now(), 'post_id' => $post->id])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => IdeaKind::class,
            'kind_source' => KindSource::class,
            'used_at' => 'datetime',
        ];
    }
}
