<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class CopywriterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You write LinkedIn posts for a brand. Given a brief and a brand profile,
        return exactly three distinct drafts: one hook led, one story led, and one
        question led.

        Constraints:
        - Plain text only. No markdown, no bullet characters, no emoji spam.
        - Under 1,200 characters each; shorter is usually stronger.
        - At most two hashtags, at the end, only if they earn their place.
        - Never use a banned term you are given.
        - Do not invent facts, statistics, product features or testimonials.
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
            'drafts' => $schema->array()
                ->items($schema->object(fn ($schema): array => [
                    'body' => $schema->string()->description('The post copy.')->required(),
                    'rationale' => $schema->string()->description('One sentence on the angle taken.')->required(),
                ]))
                ->description('Exactly three drafts.')
                ->required(),
        ];
    }
}
