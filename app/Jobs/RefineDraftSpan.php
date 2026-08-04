<?php

namespace App\Jobs;

use App\Agent\Reviser;
use App\Events\RefineChunk;
use App\Events\RefineCompleted;
use App\Events\RefineFailed;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Rewrites one selected fragment of a draft, broadcasting the replacement as it
 * is composed. Nothing is persisted: the proposal only becomes part of the
 * record if the writer accepts it.
 */
class RefineDraftSpan implements ShouldQueue
{
    use Queueable;

    /**
     * A retry would stream a second replacement into a proposal the writer is
     * already reading, so a refinement gets one attempt.
     */
    public int $tries = 1;

    public function __construct(
        public readonly Post $post,
        public readonly string $before,
        public readonly string $selection,
        public readonly string $after,
        public readonly string $instruction,
    ) {}

    public function handle(Reviser $reviser): void
    {
        $replacement = $reviser->streamSpan(
            $this->before,
            $this->selection,
            $this->after,
            $this->instruction,
            $this->post->team->brandProfile,
            fn (string $delta) => RefineChunk::dispatch($this->post->id, $delta),
        );

        RefineCompleted::dispatch($this->post->id, $replacement);
    }

    /**
     * Leave the failure on the canvas rather than a proposal that appears to
     * still be thinking.
     */
    public function failed(Throwable $exception): void
    {
        RefineFailed::dispatch($this->post->id, $exception->getMessage());
    }
}
