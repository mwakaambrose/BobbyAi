<?php

namespace App\Agent\Browser;

use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Action;
use App\Agent\Values\ActionResult;
use App\Agent\Values\Observation;
use App\Models\SocialAccount;

/**
 * An in memory browser driver used by tests. Observations are replayed in the
 * order they were scripted; the final observation repeats indefinitely so a
 * runner that loops longer than expected still terminates deterministically.
 */
final class FakeBrowserDriver implements BrowserDriver
{
    /** @var Observation[] */
    private array $observations = [];

    /** @var Action[] */
    private array $actions = [];

    private int $cursor = 0;

    private bool $closed = false;

    private ?string $nextError = null;

    /**
     * Script the observations returned by successive observe calls.
     *
     * @param  Observation[]  $observations
     */
    public function script(array $observations): self
    {
        $this->observations = $observations;

        return $this;
    }

    /**
     * Make the next action fail with the given error.
     */
    public function failNextAction(string $error): self
    {
        $this->nextError = $error;

        return $this;
    }

    /**
     * Get every action the runner asked for, in order.
     *
     * @return Action[]
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * Determine whether the session was released.
     */
    public function wasClosed(): bool
    {
        return $this->closed;
    }

    public function openSession(SocialAccount $account, bool $headed = false): SessionHandle
    {
        return new SessionHandle('fake-session');
    }

    public function observe(SessionHandle $session): Observation
    {
        if ($this->observations === []) {
            return Observation::fromArray(['url' => '', 'title' => '', 'elements' => []]);
        }

        $observation = $this->observations[min($this->cursor, count($this->observations) - 1)];

        $this->cursor++;

        return $observation;
    }

    public function act(SessionHandle $session, Action $action): ActionResult
    {
        $this->actions[] = $action;

        if ($this->nextError !== null) {
            $error = $this->nextError;
            $this->nextError = null;

            return ActionResult::failed($error);
        }

        return ActionResult::ok();
    }

    public function navigate(SessionHandle $session, string $url): ActionResult
    {
        return ActionResult::ok();
    }

    public function closeSession(SessionHandle $session): void
    {
        $this->closed = true;
    }
}
