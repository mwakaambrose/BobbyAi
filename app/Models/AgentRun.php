<?php

namespace App\Models;

use App\Enums\RunMode;
use App\Enums\RunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentRun extends Model
{
    /** @use HasFactory<\Database\Factories\AgentRunFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the post being published.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the account the run publishes to.
     *
     * @return BelongsTo<SocialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Get the recorded steps for the run, in execution order.
     *
     * @return HasMany<AgentStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class)->orderBy('index');
    }

    /**
     * Determine whether the run is still in progress.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'mode' => RunMode::class,
            'contract' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
