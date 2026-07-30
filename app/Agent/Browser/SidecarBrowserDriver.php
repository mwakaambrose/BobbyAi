<?php

namespace App\Agent\Browser;

use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Action;
use App\Agent\Values\ActionResult;
use App\Agent\Values\Observation;
use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Speaks the browser sidecar's HTTP contract. Action failures are returned as
 * failed results rather than thrown, so the runner can record them as steps.
 */
final class SidecarBrowserDriver implements BrowserDriver
{
    public function openSession(SocialAccount $account, bool $headed = false): SessionHandle
    {
        $response = $this->request()->post('/sessions', [
            'accountId' => $account->id,
            'profileDir' => $account->profileDirectory(),
            'headed' => $headed,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('The browser sidecar could not open a session: '.$response->json('error', $response->status()));
        }

        return new SessionHandle($response->json('sessionId'));
    }

    public function observe(SessionHandle $session): Observation
    {
        $response = $this->request()->get('/sessions/'.$session->id.'/observation', [
            'marks' => config('agent.perception') === 'marks' ? 1 : 0,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('The browser sidecar could not observe the page: '.$response->json('error', $response->status()));
        }

        return Observation::fromArray($response->json());
    }

    public function act(SessionHandle $session, Action $action): ActionResult
    {
        try {
            $response = $this->request()->post('/sessions/'.$session->id.'/actions', $action->toArray());
        } catch (ConnectionException $exception) {
            return ActionResult::failed($exception->getMessage());
        }

        return $response->successful()
            ? ActionResult::ok()
            : ActionResult::failed((string) $response->json('error', 'sidecar returned '.$response->status()));
    }

    public function navigate(SessionHandle $session, string $url): ActionResult
    {
        try {
            $response = $this->request()->post('/sessions/'.$session->id.'/navigate', ['url' => $url]);
        } catch (ConnectionException $exception) {
            return ActionResult::failed($exception->getMessage());
        }

        return $response->successful()
            ? ActionResult::ok()
            : ActionResult::failed((string) $response->json('error', 'sidecar returned '.$response->status()));
    }

    public function closeSession(SessionHandle $session): void
    {
        rescue(fn () => $this->request()->delete('/sessions/'.$session->id), report: false);
    }

    /**
     * Build a request bound to the configured sidecar.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl(config('agent.sidecar_url'))
            ->timeout(config('agent.browser_timeout') + 15)
            ->acceptJson()
            ->asJson();
    }
}
