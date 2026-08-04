<?php

namespace App\Agent;

use App\Enums\PostStatus;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Builds the Home screen's suggestions from what the team already has, so the
 * entry point knows the account without costing a model call.
 *
 * @phpstan-type Suggestion array{label: string, hint: string, prompt: string|null, href: string|null}
 */
final class SuggestionBuilder
{
    private const LIMIT = 3;

    private const RESUME_WINDOW_DAYS = 7;

    private const PILLAR_WINDOW_DAYS = 14;

    /**
     * Get up to three suggestions for the team, most actionable first.
     *
     * @return array<int, Suggestion>
     */
    public function for(Team $team): array
    {
        $suggestions = (new Collection([
            ...$this->resume($team),
            ...$this->awaitingPublish($team),
            ...$this->pillars($team),
            ...$this->cadence($team),
        ]))->take(self::LIMIT)->values();

        return $suggestions->isEmpty()
            ? $this->starters()
            : $suggestions->all();
    }

    /**
     * Offer the most recently touched draft that is still unfinished.
     *
     * @return array<int, Suggestion>
     */
    private function resume(Team $team): array
    {
        $post = $team->posts()
            ->where('status', PostStatus::Draft)
            ->where('updated_at', '>=', now()->subDays(self::RESUME_WINDOW_DAYS))
            ->latest('updated_at')
            ->first();

        return $post === null ? [] : [[
            'label' => 'Finish: '.$post->brief,
            'hint' => 'Draft in progress',
            'prompt' => null,
            'href' => route('posts.show', [$team->slug, $post], absolute: false),
        ]];
    }

    /**
     * Offer a post that is approved but not yet live.
     *
     * @return array<int, Suggestion>
     */
    private function awaitingPublish(Team $team): array
    {
        $post = $team->posts()
            ->where('status', PostStatus::Approved)
            ->latest('updated_at')
            ->first();

        return $post === null ? [] : [[
            'label' => 'Approve and publish',
            'hint' => $post->brief,
            'prompt' => null,
            'href' => route('posts.show', [$team->slug, $post], absolute: false),
        ]];
    }

    /**
     * Offer brand pillars the team has not written about recently.
     *
     * @return array<int, Suggestion>
     */
    private function pillars(Team $team): array
    {
        $topics = $team->brandProfile?->topics ?? [];

        if ($topics === []) {
            return [];
        }

        $recent = mb_strtolower($team->posts()
            ->where('published_at', '>=', now()->subDays(self::PILLAR_WINDOW_DAYS))
            ->pluck('brief')
            ->implode(' '));

        return (new Collection($topics))
            ->reject(fn (string $topic): bool => str_contains($recent, mb_strtolower($topic)))
            ->map(fn (string $topic): array => [
                'label' => 'Write about '.$topic,
                'hint' => 'From your brand profile',
                'prompt' => 'Write a post about '.$topic,
                'href' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * Offer the weekday the team usually publishes on.
     *
     * @return array<int, Suggestion>
     */
    private function cadence(Team $team): array
    {
        $weekday = $team->posts()
            ->whereNotNull('published_at')
            ->pluck('published_at')
            ->map(fn ($date): string => $date->format('l'))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $weekday === null ? [] : [[
            'label' => "Your weekly {$weekday} update",
            'hint' => "You usually post on {$weekday}s",
            'prompt' => 'Write my weekly update',
            'href' => null,
        ]];
    }

    /**
     * Fall back to generic openers for a team with no history and no brand profile.
     *
     * @return array<int, Suggestion>
     */
    private function starters(): array
    {
        return [
            [
                'label' => 'Announce a milestone',
                'hint' => 'Something your team just shipped',
                'prompt' => 'Write a post announcing a milestone we just hit',
                'href' => null,
            ],
            [
                'label' => 'Share a lesson',
                'hint' => 'Something you learned this week',
                'prompt' => 'Write a post about a lesson we learned this week',
                'href' => null,
            ],
        ];
    }
}
