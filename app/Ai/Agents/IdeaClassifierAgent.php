<?php

namespace App\Ai\Agents;

use App\Enums\IdeaKind;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Labels a backlog idea so the sequencer can vary the kind of post it picks
 * next. Classification only affects ordering, never whether an idea is used.
 */
class IdeaClassifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You classify a one line idea for a LinkedIn post into exactly one kind.

        - announcement: something shipped, launched, hired, hit or released.
        - lesson: something learned, a decision explained, advice from experience.
        - story: a narrative about people or events, told over time.
        - opinion: a stance, prediction or argument about the wider industry.

        Choose the closest fit. Return the kind and nothing else.
        TEXT;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->enum(IdeaKind::values())
                ->description('The kind of post this idea would become.')
                ->required(),
        ];
    }
}
