<?php

use App\Agent\ActionPlanner;
use App\Agent\Exceptions\PlannerFailed;
use App\Agent\Values\Observation;
use App\Agent\Values\RunContract;
use App\Ai\Agents\ActionPlannerAgent;
use App\Ai\Agents\PublishVerifierAgent;
use App\Enums\ActionType;
use Laravel\Ai\Prompts\AgentPrompt;

function planningObservation(): Observation
{
    return Observation::fromArray([
        'url' => 'https://www.linkedin.com/feed/',
        'title' => 'Feed | LinkedIn',
        'screenshot' => base64_encode('png-bytes'),
        'elements' => [['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []]],
    ]);
}

function planningContract(): RunContract
{
    return new RunContract('www.linkedin.com', 'Approved copy.', ['button', 'textbox', 'link'], 18);
}

test('it turns model output into an action', function (): void {
    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 7, 'text' => null, 'key' => null, 'why' => 'the composer is not open'],
    ]);

    $action = app(ActionPlanner::class)->next(planningObservation(), planningContract(), []);

    expect($action->type)->toBe(ActionType::Click)
        ->and($action->ref)->toBe(7)
        ->and($action->why)->toBe('the composer is not open');
});

test('it sends the element list and the step history in the prompt', function (): void {
    ActionPlannerAgent::fake([
        ['action' => 'wait', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'page is loading'],
    ]);

    app(ActionPlanner::class)->next(planningObservation(), planningContract(), ['1. click #7 -> ok']);

    ActionPlannerAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('[7] button "Start a post"')
        && $prompt->contains('1. click #7 -> ok'));
});

test('it retries once when the model returns an unusable action', function (): void {
    ActionPlannerAgent::fake([
        ['action' => 'teleport', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'nonsense'],
        ['action' => 'wait', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'settle'],
    ]);

    $action = app(ActionPlanner::class)->next(planningObservation(), planningContract(), []);

    expect($action->type)->toBe(ActionType::Wait);
});

test('it fails the run when both attempts are unusable', function (): void {
    ActionPlannerAgent::fake([
        ['action' => 'teleport', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'nonsense'],
        ['action' => 'teleport', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'still nonsense'],
    ]);

    app(ActionPlanner::class)->next(planningObservation(), planningContract(), []);
})->throws(PlannerFailed::class);

test('it verifies whether the post went live', function (): void {
    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'The post appears at the top of the feed.', 'url' => 'https://www.linkedin.com/feed/update/123'],
    ]);

    $verification = app(ActionPlanner::class)->verify(planningObservation(), planningContract());

    expect($verification['published'])->toBeTrue()
        ->and($verification['url'])->toBe('https://www.linkedin.com/feed/update/123');
});

test('it attaches the screenshot only in marks perception mode', function (): void {
    config()->set('agent.perception', 'text');

    ActionPlannerAgent::fake([
        ['action' => 'wait', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'settle'],
    ]);

    app(ActionPlanner::class)->next(planningObservation(), planningContract(), []);

    ActionPlannerAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->isEmpty());
});
