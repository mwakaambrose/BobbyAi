<?php

namespace App\Agent;

use App\Ai\Agents\CopywriterAgent;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\PostDraft;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Generates brand conditioned drafts, enforcing banned terms deterministically
 * rather than trusting the model to remember them.
 */
final class Copywriter
{
    /** @var string[] */
    private const VARIANTS = ['A', 'B', 'C'];

    public function __construct(private readonly CopywriterAgent $agent) {}

    /**
     * Generate and persist three drafts for the post, replacing any earlier set.
     *
     * @return Collection<int, PostDraft>
     */
    public function draftsFor(Post $post, string $brief, ?BrandProfile $brand, ?string $revisionOf = null): Collection
    {
        $banned = $brand->banned_terms ?? [];

        $drafts = $this->generate($brief, $brand, $revisionOf);

        if ($this->containsBannedTerm($drafts, $banned)) {
            $drafts = $this->generate($brief, $brand, $revisionOf, avoid: $banned);
        }

        $post->drafts()->delete();

        return (new Collection($drafts))
            ->take(count(self::VARIANTS))
            ->values()
            ->map(fn (array $draft, int $index): PostDraft => $post->drafts()->create([
                'variant' => self::VARIANTS[$index],
                'body' => $draft['body'],
                'rationale' => $draft['rationale'] ?? null,
                'character_count' => mb_strlen($draft['body']),
                'model' => config('agent.model'),
            ]));
    }

    /**
     * Find which of the given banned terms appear in the body.
     *
     * @param  string[]  $terms
     * @return string[]
     */
    public function bannedTermsIn(string $body, array $terms): array
    {
        return array_values(array_filter(
            $terms,
            fn (string $term): bool => preg_match('/\b'.preg_quote($term, '/').'\b/i', $body) === 1,
        ));
    }

    /**
     * Prompt the model for a set of drafts.
     *
     * @param  string[]  $avoid
     * @return array<int, array{body: string, rationale?: string}>
     */
    private function generate(string $brief, ?BrandProfile $brand, ?string $revisionOf, array $avoid = []): array
    {
        $response = $this->agent->prompt(
            $this->prompt($brief, $brand, $revisionOf, $avoid),
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );

        return $response instanceof StructuredAgentResponse ? ($response->toArray()['drafts'] ?? []) : [];
    }

    /**
     * Build the brand conditioned prompt.
     *
     * @param  string[]  $avoid
     */
    private function prompt(string $brief, ?BrandProfile $brand, ?string $revisionOf, array $avoid): string
    {
        return implode("\n\n", array_filter([
            'BRIEF: '.$brief,
            $brand?->voice ? 'VOICE: '.$brand->voice : null,
            $brand?->audience ? 'AUDIENCE: '.$brand->audience : null,
            $brand?->topics ? 'TOPICS: '.implode(', ', $brand->topics) : null,
            $brand?->banned_terms ? 'NEVER USE: '.implode(', ', $brand->banned_terms) : null,
            $brand?->examples ? "EXAMPLES OF OUR VOICE:\n".implode("\n", $brand->examples) : null,
            $revisionOf ? "REVISE THIS DRAFT:\n".$revisionOf : null,
            $avoid === [] ? null : 'Your previous attempt used these forbidden terms: '.implode(', ', $avoid).'. Do not use them.',
        ]));
    }

    /**
     * Determine whether any draft uses a banned term.
     *
     * @param  array<int, array{body: string}>  $drafts
     * @param  string[]  $banned
     */
    private function containsBannedTerm(array $drafts, array $banned): bool
    {
        foreach ($drafts as $draft) {
            if ($this->bannedTermsIn($draft['body'], $banned) !== []) {
                return true;
            }
        }

        return false;
    }
}
