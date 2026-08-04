<?php

namespace App\Console\Commands;

use App\Agent\BacklogSequencer;
use App\Enums\PostStatus;
use App\Jobs\DraftPostContent;
use App\Models\Post;
use App\Models\PostingSchedule;
use App\Models\Team;
use App\Notifications\Agent\DraftsReadyForReview;
use Carbon\CarbonInterface;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Drafts the posts a team's cadence calls for, leaving each one at Draft for a
 * person to edit and approve. Nothing here can publish.
 */
class DraftScheduledPosts extends Command
{
    protected $signature = 'agent:draft-scheduled';

    protected $description = 'Draft posts for any team whose posting cadence is due';

    public function handle(BacklogSequencer $sequencer): int
    {
        $now = now();

        $schedules = PostingSchedule::query()
            ->where('is_active', true)
            ->with(['team', 'account'])
            ->get();

        foreach ($schedules as $schedule) {
            if (! $schedule->isDueAt($now)) {
                continue;
            }

            $this->runSchedule($schedule, $sequencer, $now);
        }

        return self::SUCCESS;
    }

    /**
     * Draft this schedule's slot.
     */
    private function runSchedule(PostingSchedule $schedule, BacklogSequencer $sequencer, CarbonInterface $now): void
    {
        if (! $schedule->account?->status->isPublishable()) {
            $this->skip($schedule, 'no connected account');

            return;
        }

        $posts = DB::transaction(function () use ($schedule, $sequencer, $now): Collection {
            $posts = $this->draftSlot($schedule, $sequencer);

            $schedule->markRanAt($now);

            return $posts;
        });

        if ($posts->isEmpty()) {
            $this->skip($schedule, 'nothing left in the backlog or brand pillars');

            return;
        }

        $this->dispatchDrafting($schedule, $posts);

        $this->info("Drafted {$posts->count()} post(s) for team [{$schedule->team->slug}].");
    }

    /**
     * Create the slot's posts, leaving each one waiting to be written.
     *
     * @return Collection<int, Post>
     */
    private function draftSlot(PostingSchedule $schedule, BacklogSequencer $sequencer): Collection
    {
        $posts = new Collection;

        for ($i = 0; $i < $schedule->posts_per_run; $i++) {
            $selection = $sequencer->next($schedule->team);

            if ($selection === null) {
                break;
            }

            $post = Post::create([
                'team_id' => $schedule->team_id,
                'social_account_id' => $schedule->social_account_id,
                'author_id' => null,
                'brief' => $selection->brief,
                'status' => PostStatus::Draft,
            ]);

            $selection->idea?->markUsedFor($post);

            $posts->push($post);
        }

        return $posts;
    }

    /**
     * Queue the drafting, notifying the team once the batch settles.
     *
     * @param  Collection<int, Post>  $posts
     */
    private function dispatchDrafting(PostingSchedule $schedule, Collection $posts): void
    {
        $teamId = $schedule->team_id;
        $postIds = $posts->pluck('id')->all();
        $recipients = $this->recipients($schedule);

        Bus::batch($posts->map(fn (Post $post): DraftPostContent => new DraftPostContent($post, $post->brief))->all())
            ->name("autopilot:{$schedule->team->slug}")
            ->allowFailures()
            ->then(function () use ($teamId, $postIds, $recipients): void {
                $drafted = Post::query()->whereIn('id', $postIds)->whereHas('drafts')->get();

                if ($drafted->isEmpty() || $recipients === []) {
                    return;
                }

                Notification::route('mail', $recipients)->notify(
                    new DraftsReadyForReview(Team::findOrFail($teamId), $drafted)
                );
            })
            ->dispatch();
    }

    /**
     * Get the addresses to tell, falling back to the team owner.
     *
     * @return array<int, string>
     */
    private function recipients(PostingSchedule $schedule): array
    {
        $emails = array_filter($schedule->notify_emails ?? []);

        if ($emails !== []) {
            return array_values($emails);
        }

        $owner = $schedule->team->owner();

        return $owner === null ? [] : [(string) $owner->getAttribute('email')];
    }

    /**
     * Record why a due schedule drafted nothing.
     *
     * A skip is not a failure: the cadence simply had nothing to say, or the
     * account it writes for is not connected.
     */
    private function skip(PostingSchedule $schedule, string $reason): void
    {
        Log::info('Autopilot skipped a slot.', [
            'team' => $schedule->team->slug,
            'reason' => $reason,
        ]);

        $this->line("Skipped team [{$schedule->team->slug}]: {$reason}.");
    }
}
