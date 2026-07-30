<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Values\RunContract;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\PostStatus;
use App\Enums\RunMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ApprovePostRequest;
use App\Jobs\PublishPostRun;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PostApprovalController extends Controller
{
    /**
     * Snapshot the approved copy, build the run contract and queue the run.
     */
    public function store(ApprovePostRequest $request, string $currentTeam, Post $post): RedirectResponse
    {
        abort_unless($post->team_id === $request->user()->currentTeam?->id, 404);

        $post->forceFill([
            'approved_body' => $request->validated('body'),
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'status' => PostStatus::Publishing,
        ])->save();

        $run = $post->runs()->create([
            'social_account_id' => $post->social_account_id,
            'mode' => RunMode::Autonomous,
            'contract' => RunContract::forPost($post)->toArray(),
        ]);

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Run,
            'body' => __('Publishing started.'),
            'payload' => ['run_id' => $run->id],
        ]);

        PublishPostRun::dispatch($run);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approved. The agent is publishing now.')]);

        return back();
    }
}
