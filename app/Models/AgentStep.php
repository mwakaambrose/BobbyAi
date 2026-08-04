<?php

namespace App\Models;

use App\Enums\GateVerdict;
use App\Enums\StepPhase;
use Database\Factories\AgentStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $agent_run_id
 * @property int $index
 * @property StepPhase $phase
 * @property array{action: string, ref: int|null, text: string|null, key: string|null, why: string}|null $action
 * @property string|null $reasoning
 * @property int|null $ref
 * @property string|null $target
 * @property string|null $screenshot_path
 * @property GateVerdict|null $gate_decision
 * @property string|null $gate_reason
 * @property int|null $latency_ms
 * @property string|null $error
 * @property-read AgentRun $run
 */
class AgentStep extends Model
{
    /** @use HasFactory<AgentStepFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the run the step belongs to.
     *
     * @return BelongsTo<AgentRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => StepPhase::class,
            'gate_decision' => GateVerdict::class,
            'action' => 'array',
        ];
    }
}
