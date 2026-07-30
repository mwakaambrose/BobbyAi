<?php

namespace App\Agent\Contracts;

use App\Agent\Browser\SessionHandle;
use App\Agent\Values\Action;
use App\Agent\Values\ActionResult;
use App\Agent\Values\Observation;
use App\Models\SocialAccount;

interface BrowserDriver
{
    /**
     * Open a browser session on the account's persistent profile.
     */
    public function openSession(SocialAccount $account, bool $headed = false): SessionHandle;

    /**
     * Capture the current page state, including the annotated screenshot.
     */
    public function observe(SessionHandle $session): Observation;

    /**
     * Execute a single action against the element it references.
     */
    public function act(SessionHandle $session, Action $action): ActionResult;

    /**
     * Navigate the session to the given URL.
     */
    public function navigate(SessionHandle $session, string $url): ActionResult;

    /**
     * Release the session, leaving the persistent profile intact.
     */
    public function closeSession(SessionHandle $session): void;
}
