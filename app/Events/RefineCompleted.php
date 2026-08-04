<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The settled replacement. The client trusts this over whatever it accumulated
 * from the deltas, so a dropped chunk cannot leave a hole in the text the
 * writer accepts.
 */
class RefineCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $postId,
        public readonly string $replacement,
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
        return 'refine.completed';
    }
}
