<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
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

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'overview' => $this->overview($request->user()->currentTeam),
        ]);
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
