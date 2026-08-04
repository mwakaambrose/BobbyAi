<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PostingScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $social_account_id
 * @property array<int, string> $days
 * @property string $time_of_day
 * @property string $timezone
 * @property int $posts_per_run
 * @property array<int, string> $notify_emails
 * @property bool $is_active
 * @property CarbonInterface|null $last_ran_at
 * @property-read Team $team
 * @property-read SocialAccount|null $account
 */
class PostingSchedule extends Model
{
    /** @use HasFactory<PostingScheduleFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the team the schedule drafts for.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the account drafts are written for.
     *
     * @return BelongsTo<SocialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Determine whether the schedule should draft at the given moment.
     *
     * The comparison is made in the schedule's own timezone, because the
     * application runs in UTC and a bare "06:00" would otherwise fire three
     * hours early. The local time must have *passed* the cadence time rather
     * than equal it, so a tick missed while the box was down still drafts
     * later the same day. A missed day is skipped rather than backfilled.
     */
    public function isDueAt(CarbonInterface $moment): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $local = $moment->copy()->setTimezone($this->timezone);

        if (! in_array(mb_strtolower($local->englishDayOfWeek), $this->days ?? [], true)) {
            return false;
        }

        if ($local->format('H:i') < $this->time_of_day) {
            return false;
        }

        return ! $this->hasRunOn($local);
    }

    /**
     * Record that the schedule drafted at the given moment.
     */
    public function markRanAt(CarbonInterface $moment): void
    {
        $this->forceFill(['last_ran_at' => $moment])->save();
    }

    /**
     * Determine whether the schedule already ran on the given local date.
     */
    private function hasRunOn(CarbonInterface $local): bool
    {
        if ($this->last_ran_at === null) {
            return false;
        }

        return Carbon::parse($this->last_ran_at)
            ->setTimezone($this->timezone)
            ->isSameDay($local);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days' => 'array',
            'notify_emails' => 'array',
            'is_active' => 'boolean',
            'last_ran_at' => 'datetime',
            'posts_per_run' => 'integer',
        ];
    }
}
