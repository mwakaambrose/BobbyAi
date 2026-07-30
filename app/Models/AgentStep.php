<?php

namespace App\Models;

use App\Enums\GateVerdict;
use App\Enums\StepPhase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStep extends Model
{
    /** @use HasFactory<\Database\Factories\AgentStepFactory> */
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
