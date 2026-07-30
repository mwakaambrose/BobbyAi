<?php

namespace App\Jobs;

use App\Agent\PublishRunner;
use App\Models\AgentRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class PublishPostRun implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(public AgentRun $run) {}

    /**
     * Execute the publishing run.
     */
    public function handle(PublishRunner $runner): void
    {
        $runner->run($this->run);
    }

    /**
     * Ensure only one run is active per social account.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->run->social_account_id))->dontRelease()];
    }

    /**
     * Get the number of seconds the job may run for.
     */
    public function timeout(): int
    {
        return config('agent.run_timeout');
    }
}
