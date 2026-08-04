<?php

namespace App\Agent;

use App\Enums\ActionType;
use App\Enums\StepPhase;
use App\Models\AgentStep;

/**
 * Turns one recorded step into a line a person can read.
 *
 * It lives on the server rather than in the components so the live theatre and
 * the replay say exactly the same thing, from one tested function. It describes
 * the attempt only: a gate reason or an error is rendered beside it.
 */
final class StepNarrator
{
    /**
     * Describe what the agent did at this step.
     */
    public function describe(AgentStep $step): string
    {
        $action = $step->action;

        if ($action === null) {
            return $this->describePhase($step->phase);
        }

        $type = ActionType::tryFrom($action['action']);
        $target = $step->target;

        return match ($type) {
            ActionType::Click => $target === null
                ? 'Clicked the page'
                : 'Clicked “'.$target.'”',
            ActionType::Type => $target === null
                ? 'Typed the approved text'
                : 'Typed the approved text into “'.$target.'”',
            ActionType::Press => 'Pressed '.($action['key'] ?? 'a key'),
            ActionType::Scroll => 'Scrolled the page',
            ActionType::Wait => 'Waited for the page to settle',
            ActionType::Finish => 'Finished',
            ActionType::Abort => 'Stopped',
            default => 'Took an action nobody recognises',
        };
    }

    /**
     * Describe a step that carries no action of its own.
     */
    private function describePhase(StepPhase $phase): string
    {
        return match ($phase) {
            StepPhase::Observe => 'Looked at the page',
            StepPhase::Think => 'Worked out what to do next',
            StepPhase::Gate => 'Checked the action against the contract',
            StepPhase::Act => 'Acted on the page',
            StepPhase::Verify => 'Checked the post is live',
        };
    }
}
