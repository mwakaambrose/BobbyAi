<?php

namespace App\Http\Controllers;

use App\Agent\SuggestionBuilder;
use App\Enums\AccountStatus;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, SuggestionBuilder $suggestions): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('home', [
            'pendingInvitations' => $this->pendingInvitations($request),
            'suggestions' => $suggestions->for($team),
            'status' => $this->status($team),
        ]);
    }

    /**
     * The one-line pipeline summary shown beneath the composer.
     *
     * @return array{activeRuns: int, awaitingPublish: int, accountsConnected: int, accountsTotal: int, teamSlug: string}
     */
    private function status(Team $team): array
    {
        $accounts = $team->socialAccounts()->get(['id', 'status']);

        return [
            'activeRuns' => AgentRun::query()
                ->whereIn('post_id', $team->posts()->select('id'))
                ->whereIn('status', [RunStatus::Queued, RunStatus::Running])
                ->count(),
            'awaitingPublish' => $team->posts()->where('status', PostStatus::Approved)->count(),
            'accountsConnected' => $accounts
                ->where('status', AccountStatus::Connected)
                ->count(),
            'accountsTotal' => $accounts->count(),
            'teamSlug' => $team->slug,
        ];
    }

    /**
     * Invitations addressed to the authenticated user that are still open.
     *
     * @return Collection<int, array{code: string, inviterName: string, team: array{name: string, slug: string}}>
     */
    private function pendingInvitations(Request $request): Collection
    {
        return TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [strtolower($request->user()->email)])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation): array => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);
    }
}
