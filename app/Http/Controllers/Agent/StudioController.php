<?php

namespace App\Http\Controllers\Agent;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    /**
     * Show the read only pipeline board.
     */
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        $posts = $team->posts()
            ->with('runs:id,post_id,status,step_count')
            ->latest('id')
            ->get(['id', 'brief', 'status', 'approved_body', 'published_url', 'updated_at', 'author_id']);

        return Inertia::render('studio/index', [
            'columns' => $this->columns($posts),
            'totalPosts' => $posts->count(),
            'awaitingReview' => $posts
                ->where('status', PostStatus::Draft)
                ->whereNull('author_id')
                ->count(),
            'accounts' => $team->socialAccounts()->connected()->get()->map(fn (SocialAccount $account): array => [
                'id' => $account->id,
                'handle' => $account->handle,
                'status' => $account->status,
                'lastVerifiedAt' => $account->last_verified_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Group the posts into the board's fixed columns.
     *
     * @param  Collection<int, Post>  $posts
     * @return array<int, array{status: string, label: string, posts: array<int, array<string, mixed>>}>
     */
    private function columns(Collection $posts): array
    {
        return array_map(fn (PostStatus $status): array => [
            'status' => $status->value,
            'label' => $status->label(),
            'posts' => $posts
                ->where('status', $status)
                ->map(fn (Post $post): array => [
                    'id' => $post->id,
                    'brief' => $post->brief,
                    'body' => $post->approved_body,
                    'publishedUrl' => $post->published_url,
                    'updatedAt' => $post->updated_at->diffForHumans(),
                    'progress' => $this->progress($post),
                    'byBobby' => $post->author_id === null,
                ])
                ->values()
                ->all(),
        ], PostStatus::board());
    }

    /**
     * Get the step progress of the post's active run, if any.
     *
     * @return array{steps: int, budget: int}|null
     */
    private function progress(Post $post): ?array
    {
        $run = $post->runs->last();

        return $run && $run->status->isActive()
            ? ['steps' => $run->step_count, 'budget' => config('agent.step_cap')]
            : null;
    }
}
