<?php

namespace App\Http\Controllers\Agent;

use App\Enums\IdeaKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdatePostingScheduleRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutopilotController extends Controller
{
    /**
     * Show the cadence and the idea backlog it draws from.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('autopilot/index', [
            'schedule' => $this->schedule($team),
            'ideas' => $team->postIdeas()
                ->whereNull('used_at')
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'body', 'kind', 'kind_source', 'position']),
            'drafted' => $team->postIdeas()
                ->whereNotNull('used_at')
                ->with('post:id,brief,status')
                ->latest('used_at')
                ->take(10)
                ->get(['id', 'body', 'kind', 'post_id', 'used_at']),
            'accounts' => $team->socialAccounts()->connected()->get(['id', 'handle']),
            'kinds' => array_map(
                fn (IdeaKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
                IdeaKind::cases(),
            ),
        ]);
    }

    /**
     * Save the cadence.
     */
    public function update(UpdatePostingScheduleRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $team->postingSchedule()->updateOrCreate([], [
            ...$request->validated(),
            'notify_emails' => $request->validated('notify_emails', []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Autopilot updated.')]);

        return back();
    }

    /**
     * Get the team's cadence, falling back to sensible defaults before it is set.
     *
     * @return array<string, mixed>
     */
    private function schedule(Team $team): array
    {
        $schedule = $team->postingSchedule;

        if ($schedule === null) {
            return $this->defaults($team);
        }

        return [
            'isActive' => $schedule->is_active,
            'days' => $schedule->days ?? [],
            'timeOfDay' => $schedule->time_of_day,
            'timezone' => $schedule->timezone,
            'postsPerRun' => $schedule->posts_per_run,
            'socialAccountId' => $schedule->social_account_id,
            'notifyEmails' => $schedule->notify_emails ?? [],
            'lastRanAt' => $schedule->last_ran_at?->diffForHumans(),
        ];
    }

    /**
     * Get the cadence a team is offered before it has set one.
     *
     * The account and the owner's address are filled in because they are
     * almost always right, and an autopilot form that opens half empty reads
     * as more work than it is.
     *
     * @return array<string, mixed>
     */
    private function defaults(Team $team): array
    {
        $owner = $team->owner();

        return [
            'isActive' => false,
            'days' => ['monday', 'wednesday', 'friday'],
            'timeOfDay' => '06:00',
            'timezone' => 'Africa/Kampala',
            'postsPerRun' => 1,
            'socialAccountId' => $team->socialAccounts()->connected()->value('id'),
            'notifyEmails' => array_filter([$owner?->getAttribute('email')]),
            'lastRanAt' => null,
        ];
    }
}
