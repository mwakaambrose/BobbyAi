<?php

namespace App\Agent;

use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Finds brand pillars the team has not written about lately.
 *
 * Shared by the Home suggestions and the autopilot backlog fallback so both
 * agree on what counts as a neglected topic.
 */
final class PillarSelector
{
    private const WINDOW_DAYS = 14;

    /**
     * Get the team's brand pillars not covered by a recently published post.
     *
     * @return array<int, string>
     */
    public function unusedFor(Team $team): array
    {
        $brand = $team->brandProfile;
        $topics = $brand === null ? [] : $brand->topics;

        if ($topics === []) {
            return [];
        }

        $recent = mb_strtolower($team->posts()
            ->where('published_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->pluck('brief')
            ->implode(' '));

        return (new Collection($topics))
            ->reject(fn (string $topic): bool => str_contains($recent, mb_strtolower($topic)))
            ->values()
            ->all();
    }
}
