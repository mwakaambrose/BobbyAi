<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Contracts\BrowserDriver;
use App\Enums\AccountStatus;
use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SocialAccountController extends Controller
{
    /**
     * List the team's social accounts, creating the LinkedIn row on first visit.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        $team->socialAccounts()->firstOrCreate(['platform' => SocialPlatform::LinkedIn]);

        return Inertia::render('accounts/index', [
            'accounts' => $team->socialAccounts()->get()->map(fn (SocialAccount $account): array => [
                'id' => $account->id,
                'handle' => $account->handle,
                'status' => $account->status,
                'lastVerifiedAt' => $account->last_verified_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Open a visible browser on the account profile so the user can sign in.
     */
    public function connect(Request $request, string $currentTeam, SocialAccount $account, BrowserDriver $browser): RedirectResponse
    {
        $this->authorizeAccount($request, $account);

        try {
            $session = $browser->openSession($account, headed: true);
            $browser->navigate($session, config('agent.linkedin.feed_url'));
        } catch (Throwable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Could not open the browser: :error', ['error' => $exception->getMessage()])]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sign in and clear MFA in the browser window, then press Verify.')]);

        return back();
    }

    /**
     * Observe the feed and record whether the session is signed in.
     */
    public function verify(Request $request, string $currentTeam, SocialAccount $account, BrowserDriver $browser): RedirectResponse
    {
        $this->authorizeAccount($request, $account);

        try {
            $session = $browser->openSession($account);
            $browser->navigate($session, config('agent.linkedin.feed_url'));
            $observation = $browser->observe($session);
            $browser->closeSession($session);
        } catch (Throwable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $signedIn = str_contains($observation->url, '/feed');

        $account->forceFill([
            'status' => $signedIn ? AccountStatus::Connected : AccountStatus::Expired,
            'last_verified_at' => $signedIn ? now() : $account->last_verified_at,
        ])->save();

        Inertia::flash('toast', $signedIn
            ? ['type' => 'success', 'message' => __('Account connected.')]
            : ['type' => 'error', 'message' => __('Not signed in. Press Connect and complete the login.')]);

        return back();
    }

    /**
     * Abort with a 404 when the account belongs to another team.
     */
    protected function authorizeAccount(Request $request, SocialAccount $account): void
    {
        abort_unless($account->team_id === $request->user()->currentTeam?->id, 404);
    }
}
