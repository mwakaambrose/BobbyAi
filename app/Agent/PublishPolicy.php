<?php

namespace App\Agent;

use App\Agent\Values\Action;
use App\Agent\Values\GateDecision;
use App\Agent\Values\Observation;
use App\Agent\Values\PageElement;
use App\Agent\Values\RunContract;
use App\Enums\ActionType;

/**
 * Decides whether a model proposed browser action may be executed.
 *
 * This class is deliberately pure: given the same action, observation and
 * contract it always returns the same decision, which is what makes the
 * safety rules exhaustively testable.
 */
final class PublishPolicy
{
    /**
     * Accessible names matching this pattern are never clickable, whatever their role.
     */
    public const BANNED_NAME_PATTERN = '/\b(like|react|reaction|follow|connect|comment|reply|message|send|share|repost)\b/i';

    /**
     * Keys the agent may press. Submitting keys are excluded so that publishing
     * can only ever happen through an explicit, gated button click.
     *
     * @var string[]
     */
    public const ALLOWED_KEYS = ['Tab', 'Escape'];

    /**
     * Evaluate a proposed action against the run's contract.
     */
    public function evaluate(Action $action, Observation $observation, RunContract $contract, int $stepIndex): GateDecision
    {
        if ($action->type->isTerminal()) {
            return GateDecision::allow();
        }

        if ($stepIndex >= $contract->stepBudget) {
            return GateDecision::deny('step budget of '.$contract->stepBudget.' exhausted');
        }

        if ($observation->host() !== $contract->allowedHost) {
            return GateDecision::deny('page host ['.($observation->host() ?? 'unknown').'] is outside the allowed host ['.$contract->allowedHost.']');
        }

        return match ($action->type) {
            ActionType::Click => $this->evaluateClick($action, $observation, $contract),
            ActionType::Type => $this->evaluateType($action, $observation, $contract),
            ActionType::Press => $this->evaluatePress($action),
            ActionType::Scroll, ActionType::Wait => GateDecision::allow(),
            default => GateDecision::deny('unsupported action ['.$action->type->value.']'),
        };
    }

    /**
     * Evaluate a click against the element allow list and the interaction ban.
     */
    private function evaluateClick(Action $action, Observation $observation, RunContract $contract): GateDecision
    {
        $element = $this->resolve($action, $observation);

        if (! $element instanceof PageElement) {
            return GateDecision::deny('ref ['.$action->ref.'] is not present in the current observation');
        }

        if ($element->isDisabled()) {
            return GateDecision::deny('element ['.$action->ref.'] is disabled');
        }

        if (preg_match(self::BANNED_NAME_PATTERN, $element->name) === 1) {
            return GateDecision::deny('element ["'.$element->name.'"] is a forbidden social interaction');
        }

        if (! in_array($element->role, $contract->allowedRoles, true)) {
            return GateDecision::deny('element role ['.$element->role.'] is not an allowed role');
        }

        return GateDecision::allow();
    }

    /**
     * Evaluate typing, which may only ever reproduce the approved body verbatim.
     */
    private function evaluateType(Action $action, Observation $observation, RunContract $contract): GateDecision
    {
        $element = $this->resolve($action, $observation);

        if (! $element instanceof PageElement) {
            return GateDecision::deny('ref ['.$action->ref.'] is not present in the current observation');
        }

        if ($element->role !== 'textbox') {
            return GateDecision::deny('element role ['.$element->role.'] cannot receive text');
        }

        if ($action->text !== $contract->approvedBody) {
            return GateDecision::deny('typed text does not match the approved body');
        }

        return GateDecision::allow();
    }

    /**
     * Evaluate a key press against the non submitting key allow list.
     */
    private function evaluatePress(Action $action): GateDecision
    {
        return in_array($action->key, self::ALLOWED_KEYS, true)
            ? GateDecision::allow()
            : GateDecision::deny('key ['.$action->key.'] is not permitted');
    }

    /**
     * Resolve the element targeted by the action, if it exists.
     */
    private function resolve(Action $action, Observation $observation): ?PageElement
    {
        return $action->ref === null ? null : $observation->element($action->ref);
    }
}
