<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Copywriter;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StorePostMessageRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class PostMessageController extends Controller
{
    /**
     * Record a revision request and regenerate the drafts.
     */
    public function store(StorePostMessageRequest $request, string $currentTeam, Post $post, Copywriter $copywriter): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($post->team_id === $team?->id, 404);

        $post->messages()->create([
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => $request->validated('body'),
        ]);

        $drafts = $copywriter->draftsFor(
            $post,
            $request->validated('body'),
            $team->brandProfile,
            revisionOf: $post->drafts()->first()?->body,
        );

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Drafts,
            'body' => __('Updated drafts'),
            'payload' => ['draft_ids' => $drafts->pluck('id')->all()],
        ]);

        return back();
    }
}
