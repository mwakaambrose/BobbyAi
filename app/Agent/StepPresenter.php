<?php

namespace App\Agent;

use App\Models\AgentStep;

/**
 * The one shape a step takes on the way to the client.
 *
 * The live theatre gets it from a broadcast and the replay gets it from a
 * controller, so it is defined here rather than three times over: the two
 * surfaces render the same component and must be given the same thing.
 */
final class StepPresenter
{
    public function __construct(private readonly StepNarrator $narrator) {}

    /**
     * @return array{id: int, index: int, phase: string, action: array<string, mixed>|null, narration: string, reasoning: string|null, gate_decision: string|null, gate_reason: string|null, latency_ms: int|null, error: string|null, screenshot_path: string|null}
     */
    public function toArray(AgentStep $step): array
    {
        return [
            'id' => $step->id,
            'index' => $step->index,
            'phase' => $step->phase->value,
            'action' => $step->action,
            'narration' => $this->narrator->describe($step),
            'reasoning' => $step->reasoning,
            'gate_decision' => $step->gate_decision?->value,
            'gate_reason' => $step->gate_reason,
            'latency_ms' => $step->latency_ms,
            'error' => $step->error,
            'screenshot_path' => $step->screenshot_path,
        ];
    }
}
