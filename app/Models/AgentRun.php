<?php

namespace App\Models;

use App\Enums\RunMode;
use App\Enums\RunStatus;
use Carbon\CarbonInterface;
use Database\Factories\AgentRunFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $social_account_id
 * @property RunStatus $status
 * @property RunMode $mode
 * @property array{allowed_host: string, approved_body: string, allowed_roles: array<string>, step_budget: int} $contract
 * @property int $step_count
 * @property string|null $failure_reason
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 * @property-read Post $post
 * @property-read SocialAccount|null $account
 * @property-read Collection<int, AgentStep> $steps
 */
class AgentRun extends Model
{
    /** @use HasFactory<AgentRunFactory> */
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
