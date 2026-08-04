<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\Post;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = $request->user()->currentTeam;

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'overview' => $this->overview($team),
            'recentPosts' => $this->recentPosts($team),
            'recentRuns' => $this->recentRuns($team),
        ]);
    }

    /**
     * The posts the team touched most recently, whatever stage they sit at.
     *
     * @return array<int, array{id: int, brief: string, status: string, updatedAt: string}>
     */
    private function recentPosts(Team $team): array
    {
        return $team->posts()
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'brief' => $post->brief,
                'status' => $post->status->value,
                'updatedAt' => $post->updated_at->diffForHumans(short: true),
            ])
            ->all();
    }

    /**
     * The team's most recent publishing attempts, newest first.
     *
     * @return array<int, array{id: int, status: string, stepCount: int, brief: string, startedAt: string|null}>
     */
    private function recentRuns(Team $team): array
    {
        return AgentRun::query()
            ->with('post')
            ->whereIn('post_id', $team->posts()->select('id'))
            ->latest('id')
            ->take(5)
            ->get()
            ->map(fn (AgentRun $run) => [
                'id' => $run->id,
                'status' => $run->status->value,
                'stepCount' => $run->step_count,
                'brief' => $run->post->brief,
                'startedAt' => $run->started_at?->diffForHumans(short: true),
            ])
            ->all();
    }

    /**
     * Summarise the team's publishing pipeline.
     *
     * @return array{awaitingApproval: int, published: int, activeRuns: int, accounts: array{connected: int, total: int}, teamSlug: string}
     */
    private function overview(Team $team): array
    {
        return [
            'awaitingApproval' => $team->posts()->where('status', PostStatus::Approved)->count(),
            'published' => $team->posts()->where('status', PostStatus::Published)->count(),
            'activeRuns' => AgentRun::query()
                ->whereIn('post_id', $team->posts()->select('id'))
                ->whereIn('status', [RunStatus::Queued, RunStatus::Running])
                ->count(),
            'accounts' => [
                'connected' => $team->socialAccounts()->connected()->count(),
                'total' => $team->socialAccounts()->count(),
            ],
            'teamSlug' => $team->slug,
        ];
    }
}
