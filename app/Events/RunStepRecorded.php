<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One step of a publishing run, sent the moment it is persisted. It rides the
 * post's channel rather than one of its own: the workspace is already there.
 */
class RunStepRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array{id: int, index: int, phase: string, action: array<string, mixed>|null, narration: string, reasoning: string|null, gate_decision: string|null, gate_reason: string|null, latency_ms: int|null, error: string|null, screenshot_path: string|null}  $step
     */
    public function __construct(
        public readonly int $postId,
        public readonly int $runId,
        public readonly string $runStatus,
        public readonly array $step,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('posts.'.$this->postId)];
    }

    public function broadcastAs(): string
    {
        return 'run.step';
    }
}
