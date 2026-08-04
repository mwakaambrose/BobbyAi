<?php

namespace App\Agent;

use App\Ai\Agents\ComposerAgent;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\PostDraft;
use Closure;
use Laravel\Ai\Streaming\Events\TextDelta;

/**
 * Streams a single brand conditioned draft, handing each delta to the caller so
 * it can be broadcast while the model is still writing.
 */
final class Composer
{
    public function __construct(
        private readonly ComposerAgent $agent,
        private readonly Copywriter $copywriter,
    ) {}

    /**
     * Stream a draft for the post and persist it as variant A.
     *
     * The retry after a banned term is generated without streaming. The client
     * replaces whatever it streamed with server state on completion, so the
     * discarded first attempt never survives on screen.
     *
     * @param  Closure(string): void  $onDelta
     */
    public function streamFor(
        Post $post,
        string $brief,
        ?BrandProfile $brand,
        ?string $revisionOf,
        Closure $onDelta,
    ): PostDraft {
        $banned = $brand->banned_terms ?? [];

        $body = $this->stream($this->prompt($brief, $brand, $revisionOf, []), $onDelta);

        if ($this->copywriter->bannedTermsIn($body, $banned) !== []) {
            $body = $this->generate($this->prompt($brief, $brand, $revisionOf, $banned));
        }

        $post->drafts()->delete();

        return $post->drafts()->create([
            'variant' => 'A',
            'body' => $body,
            'character_count' => mb_strlen($body),
            'model' => config('agent.model'),
        ]);
    }

    /**
     * Stream the agent's response, emitting each delta as it arrives.
     *
     * @param  Closure(string): void  $onDelta
     */
    private function stream(string $prompt, Closure $onDelta): string
    {
        $body = '';

        $stream = $this->agent->stream(
            $prompt,
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );

        foreach ($stream as $event) {
            if (! $event instanceof TextDelta) {
                continue;
            }

            $body .= $event->delta;

            $onDelta($event->delta);
        }

        return trim($body);
    }

    /**
     * Generate without streaming, used for the banned term retry.
     */
    private function generate(string $prompt): string
    {
        $response = $this->agent->prompt(
            $prompt,
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );

        return trim((string) $response->text);
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
}
