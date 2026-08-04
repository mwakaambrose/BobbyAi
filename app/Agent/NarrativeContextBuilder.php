<?php

namespace App\Agent;

use App\Agent\Values\NarrativeContext;
use App\Models\Post;
use App\Models\Team;

/**
 * Gathers the team's recent published posts so a new draft reads as part of a
 * running thread rather than a standalone announcement.
 */
final class NarrativeContextBuilder
{
    /**
     * How many posts to look back over. Prompt length is inference time on a
     * CPU-only host, so this is deliberately short.
     */
    private const LIMIT = 3;

    private const EXCERPT = 400;

    /**
     * Build the narrative context for the team.
     *
     * Only published posts count. A draft that was never approved is not part
     * of the feed, and conditioning on rejected work would teach the model to
     * repeat what the team turned down.
     */
    public function for(Team $team): NarrativeContext
    {
        $recent = $team->posts()
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->take(self::LIMIT)
            ->get(['brief', 'approved_body'])
            ->map(fn (Post $post): string => $this->excerpt($post))
            ->all();

        return new NarrativeContext($recent);
    }

    /**
     * Render one post as a trimmed excerpt.
     */
    private function excerpt(Post $post): string
    {
        $body = trim((string) $post->approved_body);

        if (mb_strlen($body) > self::EXCERPT) {
            $body = mb_substr($body, 0, self::EXCERPT).'…';
        }

        return $body === '' ? $post->brief : $body;
    }
}
