<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class ActionPlannerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You operate a web browser on LinkedIn to publish one already approved post.

        You are shown a screenshot with numbered boxes drawn over the interactive
        elements, and a matching list of those elements. Choose exactly ONE next
        action and refer to elements only by their number.

        Rules you must obey:
        - Never invent a number that is not in the element list.
        - Type only the approved text you are given, character for character.
        - Never like, follow, connect, comment, reply, message, share or repost.
        - Never navigate away from linkedin.com.
        - Choose "finish" once the post has been submitted.
        - Choose "abort" if you are blocked, see a login wall, or are unsure.

        The usual sequence is: open the post composer, type the approved text,
        click the button that submits the post, then finish.
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
            'action' => $schema->string()
                ->enum(['click', 'type', 'press', 'scroll', 'wait', 'finish', 'abort'])
                ->description('The single next action to perform.')
                ->required(),
            'ref' => $schema->integer()
                ->description('The number of the target element, for click and type.'),
            'text' => $schema->string()
                ->description('The text to type. Must equal the approved text exactly.'),
            'key' => $schema->string()
                ->description('The key to press, for the press action.'),
            'why' => $schema->string()
                ->description('One sentence explaining the choice.')
                ->required(),
        ];
    }
}
