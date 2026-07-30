<?php

namespace App\Models;

use App\Enums\PostStatus;
use Carbon\CarbonInterface;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $social_account_id
 * @property int $author_id
 * @property string $brief
 * @property PostStatus $status
 * @property string|null $approved_body
 * @property CarbonInterface|null $approved_at
 * @property int|null $approved_by
 * @property string|null $published_url
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface $updated_at
 * @property-read Team $team
 * @property-read SocialAccount|null $account
 * @property-read Collection<int, PostDraft> $drafts
 * @property-read Collection<int, PostMessage> $messages
 * @property-read Collection<int, AgentRun> $runs
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
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
