<?php

use App\Agent\Browser\FakeBrowserDriver;
use App\Agent\Contracts\BrowserDriver;
use App\Agent\PublishRunner;
use App\Agent\Values\Observation;
use App\Ai\Agents\ActionPlannerAgent;
use App\Ai\Agents\PublishVerifierAgent;
use App\Enums\GateVerdict;
use App\Events\RunStepRecorded;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

const RUNNER_BODY = 'We built Growth Savings because members asked for it.';

function composerPage(bool $composerOpen = false): Observation
{
    $elements = [['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []]];

    if ($composerOpen) {
        $elements[] = ['ref' => 12, 'role' => 'textbox', 'name' => 'What do you want to talk about?', 'state' => []];
        $elements[] = ['ref' => 19, 'role' => 'button', 'name' => 'Post', 'state' => []];
    }

    return Observation::fromArray([
        'url' => 'https://www.linkedin.com/feed/',
        'title' => 'Feed | LinkedIn',
        'screenshot' => base64_encode('png'),
        'elements' => $elements,
    ]);
}

function runnableRun(): AgentRun
{
    $account = SocialAccount::factory()->connected()->create();

    $post = Post::factory()->approved(RUNNER_BODY)->create([
        'team_id' => $account->team_id,
        'social_account_id' => $account->id,
        'status' => PostStatus::Publishing,
    ]);

    return AgentRun::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'contract' => [
            'allowed_host' => 'www.linkedin.com',
            'approved_body' => RUNNER_BODY,
            'allowed_roles' => ['button', 'textbox', 'link'],
            'step_budget' => 18,
        ],
    ]);
}

function fakeDriver(): FakeBrowserDriver
{
    $driver = (new FakeBrowserDriver)->script([
        composerPage(),
        composerPage(composerOpen: true),
        composerPage(composerOpen: true),
        composerPage(composerOpen: true),
        composerPage(),
    ]);

    app()->instance(BrowserDriver::class, $driver);

    return $driver;
}

beforeEach(function (): void {
    Storage::fake('local');
});

test('it publishes a post through the full loop', function (): void {
    $driver = fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 7, 'text' => null, 'key' => null, 'why' => 'open the composer'],
        ['action' => 'type', 'ref' => 12, 'text' => RUNNER_BODY, 'key' => null, 'why' => 'write the approved copy'],
        ['action' => 'click', 'ref' => 19, 'text' => null, 'key' => null, 'why' => 'submit the post'],
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'submitted'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'The post is at the top of the feed.', 'url' => 'https://www.linkedin.com/feed/update/123'],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($run->status)->toBe(RunStatus::Published)
        ->and($run->post->fresh()->status)->toBe(PostStatus::Published)
        ->and($run->post->fresh()->published_url)->toBe('https://www.linkedin.com/feed/update/123')
        ->and($driver->actions())->toHaveCount(3)
        ->and($driver->wasClosed())->toBeTrue();
});

test('it records a step for every phase with screenshots and reasoning', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'nothing to do'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'Visible in feed.', 'url' => null],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    $step = $run->steps()->first();

    expect($run->steps()->count())->toBe(2)
        ->and($step->reasoning)->toBe('nothing to do')
        ->and($step->screenshot_path)->not->toBeNull()
        ->and($step->latency_ms)->not->toBeNull();
});

test('it blocks an off contract action and records the denial', function (): void {
    $driver = fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'type', 'ref' => 12, 'text' => RUNNER_BODY.' #growth', 'key' => null, 'why' => 'add a hashtag'],
        ['action' => 'abort', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'blocked'],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($driver->actions())->toBeEmpty()
        ->and($run->steps()->where('gate_decision', GateVerdict::Denied)->count())->toBe(1)
        ->and($run->status)->toBe(RunStatus::Aborted)
        ->and($run->post->fresh()->status)->toBe(PostStatus::Failed);
});

test('it aborts after two identical denials', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 404, 'text' => null, 'key' => null, 'why' => 'stale ref'],
        ['action' => 'click', 'ref' => 404, 'text' => null, 'key' => null, 'why' => 'stale ref again'],
        ['action' => 'click', 'ref' => 404, 'text' => null, 'key' => null, 'why' => 'and again'],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toContain('denied');
});

test('it fails the run when the step budget is exhausted', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake(fn (): array => [
        'action' => 'scroll', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'looking around',
    ]);

    $run = runnableRun();
    $run->update(['contract' => [...$run->contract, 'step_budget' => 3]]);

    $run = app(PublishRunner::class)->run($run);

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toContain('budget')
        ->and($run->steps()->count())->toBe(3);
});

test('it fails the run as unverified when the post cannot be confirmed', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'submitted'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => false, 'evidence' => 'The composer is still open.', 'url' => null],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toContain('unverified')
        ->and($run->post->fresh()->status)->toBe(PostStatus::Failed);
});

test('a step remembers the element the action targeted', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 7, 'text' => null, 'key' => null, 'why' => 'open the composer'],
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'submitted'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'Visible in feed.', 'url' => null],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($run->steps()->first()->target)->toBe('Start a post')
        ->and($run->steps()->whereNull('action')->first()?->target)->toBeNull();
});

test('every step is broadcast as it is recorded', function (): void {
    Event::fake([RunStepRecorded::class]);
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 7, 'text' => null, 'key' => null, 'why' => 'open the composer'],
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'submitted'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'Visible in feed.', 'url' => null],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    Event::assertDispatchedTimes(RunStepRecorded::class, $run->steps()->count());
    Event::assertDispatched(RunStepRecorded::class, fn (RunStepRecorded $event): bool => $event->postId === $run->post_id
        && $event->runId === $run->id
        && $event->step['narration'] === 'Clicked “Start a post”');
});
