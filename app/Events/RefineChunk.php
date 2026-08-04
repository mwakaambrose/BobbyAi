<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A slice of the replacement as the model writes it. Broadcast immediately
 * rather than queued, since putting each delta behind the queue would defeat
 * the point.
 */
class RefineChunk implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $postId,
        public readonly string $delta,
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
        return 'refine.chunk';
    }
}
