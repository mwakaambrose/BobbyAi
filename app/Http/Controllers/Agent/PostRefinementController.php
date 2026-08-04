<?php

namespace App\Http\Controllers\Agent;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AcceptRefinementRequest;
use App\Http\Requests\Agent\StoreRefinementRequest;
use App\Jobs\RefineDraftSpan;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostRefinementController extends Controller
{
    /**
     * Queue a rewrite of the selected fragment.
     */
    public function store(StoreRefinementRequest $request, string $currentTeam, Post $post): RedirectResponse
    {
        $this->authorizeTeam($request, $post);

        RefineDraftSpan::dispatch(
            $post,
            $request->side('before'),
            $request->validated('selection'),
            $request->side('after'),
            $request->validated('instruction'),
        );

        return back();
    }

    /**
     * Record an accepted rewrite as a turn on each side of the thread, so a
     * later request arrives with the edit history behind it.
     */
    public function accept(AcceptRefinementRequest $request, string $currentTeam, Post $post): RedirectResponse
    {
        $this->authorizeTeam($request, $post);

        $post->messages()->create([
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => $request->validated('instruction'),
        ]);

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Edit,
            'body' => __('Edited one fragment.'),
            'payload' => [
                'before' => $request->validated('before'),
                'after' => $request->validated('after'),
            ],
        ]);

        return back();
    }

    /**
     * Abort with a 404 when the post belongs to another team.
     */
    protected function authorizeTeam(Request $request, Post $post): void
    {
        abort_unless($post->team_id === $request->user()->currentTeam?->id, 404);
    }
}
