<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | The local Ollama provider and model used for both copywriting and browser
    | action planning. The model must accept image attachments when perception
    | is set to "marks"; run "php artisan agent:doctor" to verify.
    |
    */

    'provider' => env('AGENT_PROVIDER', 'ollama'),
    'model' => env('AGENT_MODEL', 'gemma3:12b'),

    /*
    |--------------------------------------------------------------------------
    | Perception
    |--------------------------------------------------------------------------
    |
    | "marks" sends an annotated screenshot alongside the element list. "text"
    | sends the element list only, for models without reliable vision support.
    | Screenshots are captured and retained for the audit trail either way.
    |
    */

    'perception' => env('AGENT_PERCEPTION', 'marks'),

    /*
    |--------------------------------------------------------------------------
    | Run Limits
    |--------------------------------------------------------------------------
    |
    | The step cap bounds how many actions the agent may take in one run, and
    | the timeouts bound how long any single browser action or model call may
    | take. Local inference is slow, so it is given the most generous budget.
    |
    */

    'step_cap' => (int) env('AGENT_STEP_CAP', 18),
    'run_timeout' => (int) env('AGENT_RUN_TIMEOUT', 300),
    'browser_timeout' => (int) env('AGENT_BROWSER_TIMEOUT', 20),
    'inference_timeout' => (int) env('AGENT_INFERENCE_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Browser Sidecar
    |--------------------------------------------------------------------------
    |
    | The Node service that owns Chromium. Each connected account keeps its own
    | persistent browser profile so that a session survives between runs.
    |
    */

    'sidecar_url' => env('BROWSER_SIDECAR_URL', 'http://127.0.0.1:4571'),
    'profile_path' => storage_path('app/private/browser-profiles'),

    /*
    |--------------------------------------------------------------------------
    | Audit Trail
    |--------------------------------------------------------------------------
    |
    | Every observation the agent acted on is stored so that a run can be
    | replayed. Screenshots are pruned on a schedule; the steps themselves are
    | kept indefinitely.
    |
    */

    'screenshot_disk' => env('AGENT_SCREENSHOT_DISK', 'local'),
    'screenshot_retention_days' => (int) env('AGENT_SCREENSHOT_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | LinkedIn
    |--------------------------------------------------------------------------
    |
    | The only host the agent is ever permitted to act on.
    |
    */

    'linkedin' => [
        'host' => 'www.linkedin.com',
        'feed_url' => 'https://www.linkedin.com/feed/',
    ],

];
