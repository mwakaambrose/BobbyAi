<?php

namespace App\Agent;

use App\Ai\Agents\EditorAgent;
use App\Models\BrandProfile;
use Closure;
use Laravel\Ai\Streaming\Events\TextDelta;

/**
 * Streams a replacement for one selected fragment of a draft, handing each
 * delta to the caller so it can be broadcast while the model is still writing.
 */
final class Reviser
{
    public function __construct(private readonly EditorAgent $agent) {}

    /**
     * Stream a replacement for the selected fragment.
     *
     * @param  Closure(string): void  $onDelta
     */
    public function streamSpan(
        string $before,
        string $selection,
        string $after,
        string $instruction,
        ?BrandProfile $brand,
        Closure $onDelta,
    ): string {
        $replacement = '';

        $stream = $this->agent->stream(
            $this->prompt($before, $selection, $after, $instruction, $brand),
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );

        foreach ($stream as $event) {
            if (! $event instanceof TextDelta) {
                continue;
            }

            $replacement .= $event->delta;

            $onDelta($event->delta);
        }

        return trim($replacement);
    }

    /**
     * Build the brand conditioned prompt for a single fragment.
     */
    private function prompt(
        string $before,
        string $selection,
        string $after,
        string $instruction,
        ?BrandProfile $brand,
    ): string {
        return implode("\n\n", array_filter([
            $brand?->voice ? 'VOICE: '.$brand->voice : null,
            $brand?->audience ? 'AUDIENCE: '.$brand->audience : null,
            $brand?->banned_terms ? 'NEVER USE: '.implode(', ', $brand->banned_terms) : null,
            $before === '' ? null : "TEXT BEFORE:\n".$before,
            "FRAGMENT TO REPLACE:\n".$selection,
            $after === '' ? null : "TEXT AFTER:\n".$after,
            'INSTRUCTION: '.$instruction,
        ]));
    }
}
