<?php

namespace App\Jobs;

use App\Ai\Agents\IdeaClassifierAgent;
use App\Enums\IdeaKind;
use App\Enums\KindSource;
use App\Models\PostIdea;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Labels a backlog idea in the background, so adding an idea never waits on
 * inference and never fails because the model is unreachable.
 */
class ClassifyPostIdea implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly PostIdea $idea) {}

    public function handle(IdeaClassifierAgent $agent): void
    {
        if (! $this->idea->acceptsClassification()) {
            return;
        }

        $kind = $this->classify($agent);

        if ($kind === null) {
            return;
        }

        $this->idea->forceFill([
            'kind' => $kind,
            'kind_source' => KindSource::Agent,
        ])->save();
    }

    /**
     * Ask the model which kind the idea is, or null when it cannot say.
     */
    private function classify(IdeaClassifierAgent $agent): ?IdeaKind
    {
        $response = $agent->prompt(
            'IDEA: '.$this->idea->body,
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );

        if (! $response instanceof StructuredAgentResponse) {
            return null;
        }

        return IdeaKind::tryFrom($response->toArray()['kind'] ?? '');
    }
}
