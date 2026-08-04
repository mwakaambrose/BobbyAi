<?php

namespace App\Agent\Values;

use App\Models\PostIdea;

/**
 * What the autopilot decided to write about next, and where it came from.
 */
final class BacklogSelection
{
    private function __construct(
        public readonly string $brief,
        public readonly ?PostIdea $idea = null,
    ) {}

    /**
     * Build a selection taken from the team's backlog.
     */
    public static function fromIdea(PostIdea $idea): self
    {
        return new self($idea->body, $idea);
    }

    /**
     * Build a selection synthesised from a neglected brand pillar.
     *
     * No backlog row is created for it: a pillar sourced post is simply one
     * with no linked idea, so the team's own list stays theirs.
     */
    public static function fromPillar(string $topic): self
    {
        return new self(__('Write a post about :topic', ['topic' => $topic]));
    }
}
