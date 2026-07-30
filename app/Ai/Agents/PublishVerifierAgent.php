<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class PublishVerifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You are shown the state of a LinkedIn page immediately after a post was
        submitted. Decide whether the post is actually live.

        Only answer published=true if you can point to concrete evidence, such as
        the post visible in the feed or a confirmation message. If the composer is
        still open, an error is shown, or you cannot tell, answer published=false
        and say what you saw. Never guess.
        TEXT;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'published' => $schema->boolean()
                ->description('Whether the post is confirmed live.')
                ->required(),
            'evidence' => $schema->string()
                ->description('What on the page supports the answer.')
                ->required(),
            'url' => $schema->string()
                ->description('The permalink of the published post, if visible.'),
        ];
    }
}
