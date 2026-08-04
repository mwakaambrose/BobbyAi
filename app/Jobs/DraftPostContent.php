<?php

namespace App\Jobs;

use App\Agent\Composer;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Events\DraftChunk;
use App\Events\DraftCompleted;
use App\Events\DraftFailed;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writes a draft for the post, broadcasting it as it is composed.
 */
class DraftPostContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly Post $post,
        public readonly string $brief,
        public readonly ?string $revisionOf = null,
    ) {}

    public function handle(Composer $composer): void
    {
        $draft = $composer->streamFor(
            $this->post,
            $this->brief,
            $this->post->team->brandProfile,
            $this->revisionOf,
            fn (string $delta) => DraftChunk::dispatch($this->post->id, $delta),
        );

        $this->post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Drafts,
            'body' => __('Drafted'),
            'payload' => ['draft_ids' => [$draft->id]],
        ]);

        DraftCompleted::dispatch($this->post->id, $draft->id);
    }

    /**
     * Leave the failure visible in the thread rather than a canvas that appears
     * to still be thinking.
     */
    public function failed(Throwable $exception): void
    {
        $this->post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Error,
            'body' => __('Bobby could not finish this draft.'),
            'payload' => ['reason' => $exception->getMessage()],
        ]);

        DraftFailed::dispatch($this->post->id, $exception->getMessage());
    }
}
