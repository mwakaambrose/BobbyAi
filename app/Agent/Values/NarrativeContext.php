<?php

namespace App\Agent\Values;

/**
 * What the account has said recently, so a new draft can build on it instead of
 * arriving as though nothing came before.
 */
final class NarrativeContext
{
    /**
     * @param  array<int, string>  $recent  Excerpts of recent published posts, newest first.
     */
    public function __construct(public readonly array $recent = []) {}

    /**
     * Build an empty context, for a team with nothing published yet.
     */
    public static function empty(): self
    {
        return new self;
    }

    /**
     * Determine whether there is any history to condition on.
     */
    public function isEmpty(): bool
    {
        return $this->recent === [];
    }

    /**
     * Render the context as a prompt fragment, or null when there is none.
     */
    public function toPrompt(): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        return "RECENTLY PUBLISHED, NEWEST FIRST:\n"
            .implode("\n\n", $this->recent)
            ."\n\nBuild on these where it is natural, and do not reuse their angles or openings.";
    }
}
