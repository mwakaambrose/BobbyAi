<?php

namespace App\Agent;

use App\Agent\Values\BacklogSelection;
use App\Enums\IdeaKind;
use App\Models\PostIdea;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Chooses what the autopilot writes next, ordering the backlog so consecutive
 * posts vary in kind rather than arriving as a run of announcements.
 *
 * Deliberately free of model calls: sequencing is the one part of autopilot
 * that must stay predictable and cheap on a CPU-only host.
 */
final class BacklogSequencer
{
    public function __construct(private readonly PillarSelector $pillars) {}

    /**
     * Choose the next thing to draft for the team, or null when nothing is left.
     */
    public function next(Team $team): ?BacklogSelection
    {
        $idea = $this->nextIdea($team);

        if ($idea !== null) {
            return BacklogSelection::fromIdea($idea);
        }

        $pillar = $this->pillars->unusedFor($team)[0] ?? null;

        return $pillar === null ? null : BacklogSelection::fromPillar($pillar);
    }

    /**
     * Pick the best unused idea from the backlog.
     */
    private function nextIdea(Team $team): ?PostIdea
    {
        $candidates = $team->postIdeas()->unused()->orderBy('position')->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $previous = $this->previousKind($team);
        $lastUsed = $this->lastUsedByKind($team);

        $varied = $candidates->reject(
            fn (PostIdea $idea): bool => $idea->kind !== null && $idea->kind === $previous
        );

        // Variety is a preference, not a veto. A backlog of a single kind still
        // gets drafted rather than starving the run.
        return ($varied->isEmpty() ? $candidates : $varied)
            ->sortBy([
                fn (PostIdea $a, PostIdea $b): int => $this->staleness($a, $lastUsed) <=> $this->staleness($b, $lastUsed),
                fn (PostIdea $a, PostIdea $b): int => $a->position <=> $b->position,
                fn (PostIdea $a, PostIdea $b): int => $a->id <=> $b->id,
            ])
            ->first();
    }

    /**
     * Get the kind of the most recently created post, so it is not repeated.
     *
     * A post with no linked idea is pillar sourced and has no kind, in which
     * case nothing is rejected.
     */
    private function previousKind(Team $team): ?IdeaKind
    {
        $post = $team->posts()->latest('id')->first(['id']);

        if ($post === null) {
            return null;
        }

        return $team->postIdeas()->where('post_id', $post->id)->value('kind');
    }

    /**
     * Get when each kind was last drafted.
     *
     * @return array<string, int>
     */
    private function lastUsedByKind(Team $team): array
    {
        return $team->postIdeas()
            ->whereNotNull('used_at')
            ->whereNotNull('kind')
            ->get(['kind', 'used_at'])
            ->groupBy(fn (PostIdea $idea): string => $idea->kind->value)
            ->map(fn (Collection $ideas): int => $ideas->max(fn (PostIdea $idea): int => $idea->used_at->getTimestamp()))
            ->all();
    }

    /**
     * Rank an idea by how long its kind has gone unused.
     *
     * A kind never drafted, and an idea not yet classified, both rank as
     * maximally stale so they sort first and fall back to position order.
     *
     * @param  array<string, int>  $lastUsed
     */
    private function staleness(PostIdea $idea, array $lastUsed): int
    {
        return $idea->kind === null ? 0 : ($lastUsed[$idea->kind->value] ?? 0);
    }
}
