<?php

namespace App\Agent;

use App\Agent\Exceptions\PlannerFailed;
use App\Agent\Values\Action;
use App\Agent\Values\Observation;
use App\Agent\Values\RunContract;
use App\Ai\Agents\ActionPlannerAgent;
use App\Ai\Agents\PublishVerifierAgent;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Turns an observation into the next proposed action, and decides whether a
 * submitted post actually went live.
 */
final class ActionPlanner
{
    public function __construct(
        private readonly ActionPlannerAgent $planner,
        private readonly PublishVerifierAgent $verifier,
    ) {}

    /**
     * Ask the model for the next action, retrying once if the answer is unusable.
     *
     * @param  list<string>  $history
     *
     * @throws PlannerFailed
     */
    public function next(Observation $observation, RunContract $contract, array $history): Action
    {
        $prompt = $this->planningPrompt($observation, $contract, $history);

        $reminder = $prompt."\n\nYour previous answer was rejected. Answer with one valid action, using only element numbers that appear above.";

        $failure = null;

        foreach ([$prompt, $reminder] as $attempt) {
            try {
                return Action::fromArray($this->prompt($this->planner, $attempt, $observation));
            } catch (InvalidArgumentException $exception) {
                $failure = $exception;
            }
        }

        throw new PlannerFailed('The planner returned an unusable action twice: '.$failure->getMessage());
    }

    /**
     * Ask the model whether the post is live.
     *
     * @return array{published: bool, evidence: string, url: string|null}
     */
    public function verify(Observation $observation, RunContract $contract): array
    {
        $response = $this->prompt($this->verifier, implode("\n\n", [
            'The approved post text was:',
            $contract->approvedBody,
            'The page now shows:',
            $observation->toPromptText(),
        ]), $observation);

        return [
            'published' => (bool) ($response['published'] ?? false),
            'evidence' => (string) ($response['evidence'] ?? ''),
            'url' => $response['url'] ?? null,
        ];
    }

    /**
     * Build the planning prompt from the observation, contract and history.
     *
     * @param  list<string>  $history
     */
    private function planningPrompt(Observation $observation, RunContract $contract, array $history): string
    {
        return implode("\n\n", array_filter([
            'GOAL: publish this exact text as a new LinkedIn post:',
            $contract->approvedBody,
            $history === [] ? null : "STEPS SO FAR:\n".implode("\n", $history),
            'CURRENT PAGE:',
            $observation->toPromptText(),
            'What is the single next action?',
        ]));
    }

    /**
     * Prompt an agent, attaching the screenshot when perception allows it.
     *
     * @return array<string, mixed>
     */
    private function prompt(Agent $agent, string $prompt, Observation $observation): array
    {
        $attachments = config('agent.perception') === 'marks' && $observation->screenshot !== null
            ? [new Base64Image($observation->screenshot, 'image/png')]
            : [];

        $response = $agent->prompt(
            $prompt,
            attachments: $attachments,
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );

        return $response instanceof StructuredAgentResponse ? $response->toArray() : [];
    }
}
