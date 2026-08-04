<?php

namespace App\Agent;

use App\Agent\Browser\SessionHandle;
use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Action;
use App\Agent\Values\Observation;
use App\Agent\Values\RunContract;
use App\Enums\ActionType;
use App\Enums\GateVerdict;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Enums\StepPhase;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drives one publishing run: observe, think, gate, act, record — one action at
 * a time — until the post is verified live or the run terminates.
 */
final class PublishRunner
{
    private int $stepIndex = 0;

    /** @var list<string> */
    private array $history = [];

    private ?string $lastDenial = null;

    private int $repeatedDenials = 0;

    public function __construct(
        private readonly BrowserDriver $browser,
        private readonly ActionPlanner $planner,
        private readonly PublishPolicy $policy,
    ) {}

    /**
     * Execute the run to completion.
     */
    public function run(AgentRun $run): AgentRun
    {
        $contract = RunContract::fromArray($run->contract);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        $session = null;

        try {
            $session = $this->browser->openSession($run->account);
            $this->browser->navigate($session, config('agent.linkedin.feed_url'));

            $this->loop($run, $contract, $session);
        } catch (Throwable $exception) {
            $this->fail($run, $exception->getMessage());
        } finally {
            if ($session instanceof SessionHandle) {
                $this->browser->closeSession($session);
            }
        }

        return $run->refresh();
    }

    /**
     * Run the observe, think, gate, act cycle until termination.
     */
    private function loop(AgentRun $run, RunContract $contract, SessionHandle $session): void
    {
        while (true) {
            if ($this->stepIndex >= $contract->stepBudget) {
                $this->fail($run, 'step budget of '.$contract->stepBudget.' exhausted');

                return;
            }

            $observation = $this->browser->observe($session);

            $startedAt = hrtime(true);
            $action = $this->planner->next($observation, $contract, $this->history);
            $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            $decision = $this->policy->evaluate($action, $observation, $contract, $this->stepIndex);

            if (! $decision->isAllowed()) {
                $this->history[] = $this->stepIndex.'. '.$action->describe().' -> denied: '.$decision->reason;

                $this->record($run, StepPhase::Gate, $observation, $action, $latency, GateVerdict::Denied, $decision->reason);

                if ($this->tooManyDenials($decision->reason)) {
                    $this->fail($run, 'the same action was denied twice: '.$decision->reason);

                    return;
                }

                continue;
            }

            if ($action->type === ActionType::Abort) {
                $this->record($run, StepPhase::Act, $observation, $action, $latency, GateVerdict::Allowed);
                $this->abort($run, $action->why);

                return;
            }

            if ($action->type === ActionType::Finish) {
                $this->record($run, StepPhase::Act, $observation, $action, $latency, GateVerdict::Allowed);
                $this->finish($run, $contract, $session);

                return;
            }

            $result = $this->browser->act($session, $action);

            $this->record($run, StepPhase::Act, $observation, $action, $latency, GateVerdict::Allowed, error: $result->error);

            $this->history[] = $this->stepIndex.'. '.$action->describe().' -> '.($result->ok ? 'ok' : 'error: '.$result->error);
        }
    }

    /**
     * Observe once more and ask the verifier whether the post is live.
     */
    private function finish(AgentRun $run, RunContract $contract, SessionHandle $session): void
    {
        $observation = $this->browser->observe($session);
        $verification = $this->planner->verify($observation, $contract);

        $this->record(
            $run,
            StepPhase::Verify,
            $observation,
            null,
            null,
            GateVerdict::Allowed,
            reasoning: $verification['evidence'],
        );

        if (! $verification['published']) {
            $this->fail($run, 'unverified: '.$verification['evidence']);

            return;
        }

        $run->forceFill([
            'status' => RunStatus::Published,
            'finished_at' => now(),
            'step_count' => $this->stepIndex,
        ])->save();

        $run->post->forceFill([
            'status' => PostStatus::Published,
            'published_url' => $verification['url'],
            'published_at' => now(),
        ])->save();
    }

    /**
     * Record a step, persisting the screenshot that produced it.
     */
    private function record(
        AgentRun $run,
        StepPhase $phase,
        Observation $observation,
        ?Action $action,
        ?int $latency,
        GateVerdict $verdict,
        ?string $gateReason = null,
        ?string $error = null,
        ?string $reasoning = null,
    ): void {
        $run->steps()->create([
            'index' => $this->stepIndex,
            'phase' => $phase,
            'action' => $action?->toArray(),
            'reasoning' => $reasoning ?? $action?->why,
            'ref' => $action?->ref,
            'target' => $action?->ref === null
                ? null
                : $observation->element($action->ref)?->name,
            'screenshot_path' => $this->storeScreenshot($run, $observation),
            'gate_decision' => $verdict,
            'gate_reason' => $gateReason,
            'latency_ms' => $latency,
            'error' => $error,
        ]);

        $this->stepIndex++;

        $run->forceFill(['step_count' => $this->stepIndex])->save();
    }

    /**
     * Persist the observation screenshot to the private disk.
     */
    private function storeScreenshot(AgentRun $run, Observation $observation): ?string
    {
        if ($observation->screenshot === null) {
            return null;
        }

        $path = 'agent-screenshots/'.$run->id.'/'.Str::padLeft((string) $this->stepIndex, 3, '0').'.png';

        Storage::disk(config('agent.screenshot_disk'))->put($path, base64_decode($observation->screenshot));

        return $path;
    }

    /**
     * Track consecutive identical denials.
     */
    private function tooManyDenials(string $reason): bool
    {
        $this->repeatedDenials = $reason === $this->lastDenial ? $this->repeatedDenials + 1 : 0;
        $this->lastDenial = $reason;

        return $this->repeatedDenials >= 1;
    }

    /**
     * Mark the run and its post as failed.
     */
    private function fail(AgentRun $run, string $reason): void
    {
        $run->forceFill([
            'status' => RunStatus::Failed,
            'failure_reason' => $reason,
            'finished_at' => now(),
            'step_count' => $this->stepIndex,
        ])->save();

        $run->post->forceFill(['status' => PostStatus::Failed])->save();
    }

    /**
     * Mark the run as deliberately aborted.
     */
    private function abort(AgentRun $run, string $reason): void
    {
        $run->forceFill([
            'status' => RunStatus::Aborted,
            'failure_reason' => $reason,
            'finished_at' => now(),
            'step_count' => $this->stepIndex,
        ])->save();

        $run->post->forceFill(['status' => PostStatus::Failed])->save();
    }
}
