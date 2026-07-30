<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Copywriter;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StorePostRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Create a post from a brief and generate its first drafts.
     */
    public function store(StorePostRequest $request, Copywriter $copywriter): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $post = Post::create([
            'team_id' => $team->id,
            'social_account_id' => $request->validated('social_account_id'),
            'author_id' => $request->user()->id,
            'brief' => $request->validated('brief'),
            'status' => PostStatus::Draft,
        ]);

        $post->messages()->create([
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => $post->brief,
        ]);

        $drafts = $copywriter->draftsFor($post, $post->brief, $team->brandProfile);

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Drafts,
            'body' => __(':count drafts', ['count' => $drafts->count()]),
            'payload' => ['draft_ids' => $drafts->pluck('id')->all()],
        ]);

        return to_route('posts.show', [$team->slug, $post]);
    }

    /**
     * Show the post workspace.
     */
    public function show(Request $request, string $currentTeam, Post $post): Response
    {
        $this->authorizeTeam($request, $post);

        $run = $post->latestRun();

        return Inertia::render('posts/show', [
            'post' => $post->only(['id', 'brief', 'status', 'approved_body', 'published_url']),
            'drafts' => $post->drafts()->get(['id', 'variant', 'body', 'rationale', 'character_count']),
            'messages' => $post->messages()->get(['id', 'role', 'kind', 'body', 'payload', 'created_at']),
            'run' => $run?->only(['id', 'status', 'mode', 'step_count', 'failure_reason']),
            'steps' => Inertia::defer(fn (): array => $run
                ? $run->steps()->get(['id', 'index', 'phase', 'action', 'reasoning', 'gate_decision', 'gate_reason', 'latency_ms', 'error'])->all()
                : []),
        ]);
    }

    /**
     * Abort with a 404 when the record belongs to another team.
     */
    protected function authorizeTeam(Request $request, Post $post): void
    {
        abort_unless($post->team_id === $request->user()->currentTeam?->id, 404);
    }
}
