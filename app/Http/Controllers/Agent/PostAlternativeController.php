<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Copywriter;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostAlternativeController extends Controller
{
    /**
     * Generate the three angled alternatives for a post on request.
     *
     * This is the structured three variant path, kept off the drafting flow so
     * the canvas has one confident draft to stream rather than a set to choose
     * between before the user has read anything.
     */
    public function store(Request $request, string $currentTeam, Post $post, Copywriter $copywriter): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($post->team_id === $team?->id, 404);

        $drafts = $copywriter->draftsFor($post, $post->brief, $team->brandProfile);

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Drafts,
            'body' => __('Three other angles'),
            'payload' => ['draft_ids' => $drafts->pluck('id')->all()],
        ]);

        return back();
    }
}
