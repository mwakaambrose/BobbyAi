<?php

namespace Database\Factories;

use App\Enums\GateVerdict;
use App\Enums\StepPhase;
use App\Models\AgentRun;
use App\Models\AgentStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentStep>
 */
class AgentStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_run_id' => AgentRun::factory(),
            'index' => 0,
            'phase' => StepPhase::Act,
            'action' => ['action' => 'click', 'ref' => 7, 'why' => 'open the composer'],
            'target' => 'Start a post',
            'reasoning' => 'The composer is not open yet.',
            'gate_decision' => GateVerdict::Allowed,
            'latency_ms' => 620,
        ];
    }
}
