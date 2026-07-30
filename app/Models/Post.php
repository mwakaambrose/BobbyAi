<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the team that owns the post.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the social account the post publishes to.
     *
     * @return BelongsTo<SocialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Get the generated drafts for the post.
     *
     * @return HasMany<PostDraft, $this>
     */
    public function drafts(): HasMany
    {
        return $this->hasMany(PostDraft::class)->orderBy('variant');
    }

    /**
     * Get the workspace thread messages for the post.
     *
     * @return HasMany<PostMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(PostMessage::class)->oldest();
    }

    /**
     * Get the publishing runs for the post.
     *
     * @return HasMany<AgentRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * Get the most recent publishing run for the post.
     */
    public function latestRun(): ?AgentRun
    {
        return $this->runs()->latest('id')->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
