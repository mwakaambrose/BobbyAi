<?php

namespace App\Http\Controllers\Agent;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StorePostMessageRequest;
use App\Jobs\DraftPostContent;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class PostMessageController extends Controller
{
    /**
     * Record a revision request and queue a redraft.
     */
    public function store(StorePostMessageRequest $request, string $currentTeam, Post $post): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($post->team_id === $team?->id, 404);

        $post->messages()->create([
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => $request->validated('body'),
        ]);

        DraftPostContent::dispatch(
            $post,
            $request->validated('body'),
            revisionOf: $post->drafts()->first()?->body,
        );

        return back();
    }
}
