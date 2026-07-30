<?php

namespace App\Http\Controllers\Agent;

use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RunController extends Controller
{
    /**
     * List the team's publishing runs.
     */
    public function index(Request $request): Response
    {
        $runs = AgentRun::query()
            ->whereRelation('post', 'team_id', $request->user()->currentTeam->id)
            ->with('post:id,brief')
            ->latest('id')
            ->paginate(20)
            ->through(fn (AgentRun $run): array => [
                'id' => $run->id,
                'status' => $run->status,
                'stepCount' => $run->step_count,
                'failureReason' => $run->failure_reason,
                'startedAt' => $run->started_at?->diffForHumans(),
                'brief' => $run->post->brief,
            ]);

        return Inertia::render('runs/index', ['runs' => $runs]);
    }

    /**
     * Replay a single run step by step.
     */
    public function show(Request $request, string $currentTeam, AgentRun $run): Response
    {
        $this->authorizeRun($request, $run);

        return Inertia::render('runs/show', [
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'failureReason' => $run->failure_reason,
                'contract' => $run->contract,
                'postId' => $run->post_id,
            ],
            'steps' => Inertia::defer(fn (): array => $run->steps()->get([
                'id', 'index', 'phase', 'action', 'reasoning', 'gate_decision', 'gate_reason', 'latency_ms', 'error', 'screenshot_path',
            ])->all()),
        ]);
    }

    /**
     * Abort an in flight run.
     */
    public function abort(Request $request, string $currentTeam, AgentRun $run): RedirectResponse
    {
        $this->authorizeRun($request, $run);

        abort_unless($run->isActive(), 422);

        $run->forceFill([
            'status' => RunStatus::Aborted,
            'failure_reason' => __('Aborted by :name.', ['name' => $request->user()->name]),
            'finished_at' => now(),
        ])->save();

        $run->post->forceFill(['status' => PostStatus::Failed])->save();

        return back();
    }

    /**
     * Abort with a 404 when the run belongs to another team.
     */
    protected function authorizeRun(Request $request, AgentRun $run): void
    {
        abort_unless($run->post->team_id === $request->user()->currentTeam?->id, 404);
    }
}
