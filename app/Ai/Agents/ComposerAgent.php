<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes a single post in plain text so it can be streamed into the canvas as
 * it is composed. The three variant CopywriterAgent still handles alternatives,
 * where structured output is worth the loss of a readable stream.
 */
class ComposerAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You write LinkedIn posts for a brand. Given a brief and a brand profile,
        return exactly one post.

        Constraints:
        - Plain text only. No markdown, no bullet characters, no emoji spam.
        - Under 1,200 characters; shorter is usually stronger.
        - At most two hashtags, at the end, only if they earn their place.
        - Never use a banned term you are given.
        - Do not invent facts, statistics, product features or testimonials.
        - Return the post copy and nothing else. No preamble, no explanation.
        TEXT;
    }
}
