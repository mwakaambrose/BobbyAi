<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Rewrites one fragment of a post in place. Plain text so it can be streamed
 * into the proposal as it is written, and scoped to the fragment so the text
 * around the writer's selection never moves.
 */
class EditorAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You revise one fragment of a LinkedIn post. You are given the text before
        the fragment, the fragment itself, the text after it, and an instruction
        describing what is wrong with the fragment.

        Constraints:
        - Return a replacement for the fragment and nothing else. No preamble, no
          explanation, no quotation marks around it.
        - Do not rewrite, repeat or extend the text before or after the fragment.
          It is context only.
        - Match the surrounding voice, tense and person.
        - Plain text only. No markdown, no bullet characters, no emoji spam.
        - Never use a banned term you are given.
        - Do not invent facts, statistics, product features or testimonials.
        - If the instruction cannot be honoured, return the fragment unchanged.
        TEXT;
    }
}
