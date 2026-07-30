<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\SocialPlatform;
use Carbon\CarbonInterface;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property SocialPlatform $platform
 * @property string|null $handle
 * @property AccountStatus $status
 * @property CarbonInterface|null $last_verified_at
 * @property-read Team $team
 */
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the team that owns the account.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the filesystem path of the account's persistent browser profile.
     */
    public function profileDirectory(): string
    {
        return config('agent.profile_path').'/'.$this->id;
    }

    /**
     * Scope the query to accounts with a live browser session.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeConnected(Builder $query): void
    {
        $query->where('status', AccountStatus::Connected);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => AccountStatus::class,
            'last_verified_at' => 'datetime',
        ];
    }
}
