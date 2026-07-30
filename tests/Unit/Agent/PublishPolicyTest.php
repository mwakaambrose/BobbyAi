<?php

use App\Agent\PublishPolicy;
use App\Agent\Values\Action;
use App\Agent\Values\GateDecision;
use App\Agent\Values\Observation;
use App\Agent\Values\RunContract;

const APPROVED_BODY = 'We built Growth Savings because members asked for it.';

function contract(int $budget = 18): RunContract
{
    return new RunContract('www.linkedin.com', APPROVED_BODY, ['button', 'textbox', 'link'], $budget);
}

function feed(string $url = 'https://www.linkedin.com/feed/'): Observation
{
    return Observation::fromArray([
        'url' => $url,
        'title' => 'Feed | LinkedIn',
        'screenshot' => null,
        'elements' => [
            ['ref' => 3, 'role' => 'link', 'name' => 'Home', 'state' => []],
            ['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []],
            ['ref' => 9, 'role' => 'button', 'name' => 'Like', 'state' => []],
            ['ref' => 10, 'role' => 'button', 'name' => 'Send message', 'state' => []],
            ['ref' => 12, 'role' => 'textbox', 'name' => 'What do you want to talk about?', 'state' => []],
            ['ref' => 19, 'role' => 'button', 'name' => 'Post', 'state' => []],
            ['ref' => 20, 'role' => 'button', 'name' => 'Post', 'state' => ['disabled' => true]],
            ['ref' => 21, 'role' => 'img', 'name' => 'Add media', 'state' => []],
        ],
    ]);
}

function gate(Action $action, ?Observation $observation = null, ?RunContract $contract = null, int $step = 0): GateDecision
{
    return (new PublishPolicy)->evaluate($action, $observation ?? feed(), $contract ?? contract(), $step);
}

test('it allows clicking the composer button', function (): void {
    expect(gate(Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']))->isAllowed())->toBeTrue();
});

test('it allows typing the exact approved body', function (): void {
    $action = Action::fromArray(['action' => 'type', 'ref' => 12, 'text' => APPROVED_BODY, 'why' => 'write']);

    expect(gate($action)->isAllowed())->toBeTrue();
});

test('it denies typing anything other than the approved body', function (string $text): void {
    $action = Action::fromArray(['action' => 'type', 'ref' => 12, 'text' => $text, 'why' => 'write']);

    $decision = gate($action);

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('approved');
})->with([
    'appended hashtag' => [APPROVED_BODY.' #fintech'],
    'trailing space' => [APPROVED_BODY.' '],
    'reworded' => ['We built Growth Savings for our members.'],
]);

test('it denies any action while off the allowed host', function (): void {
    $decision = gate(
        Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']),
        feed('https://example.com/feed/'),
    );

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('host');
});

test('it denies social graph interactions regardless of role', function (int $ref): void {
    $decision = gate(Action::fromArray(['action' => 'click', 'ref' => $ref, 'why' => 'engage']));

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('forbidden');
})->with(['like button' => [9], 'message button' => [10]]);

test('it denies clicking an element whose role is not allowed', function (): void {
    $decision = gate(Action::fromArray(['action' => 'click', 'ref' => 21, 'why' => 'add media']));

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('role');
});

test('it denies clicking a ref that is not on the page', function (): void {
    $decision = gate(Action::fromArray(['action' => 'click', 'ref' => 404, 'why' => 'stale']));

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('not present');
});

test('it denies clicking a disabled element', function (): void {
    $decision = gate(Action::fromArray(['action' => 'click', 'ref' => 20, 'why' => 'publish']));

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('disabled');
});

test('it denies typing into an element that is not a textbox', function (): void {
    $action = Action::fromArray(['action' => 'type', 'ref' => 7, 'text' => APPROVED_BODY, 'why' => 'write']);

    expect(gate($action)->isAllowed())->toBeFalse();
});

test('it only allows non submitting key presses', function (string $key, bool $allowed): void {
    $action = Action::fromArray(['action' => 'press', 'key' => $key, 'why' => 'navigate']);

    expect(gate($action)->isAllowed())->toBe($allowed);
})->with([
    'tab' => ['Tab', true],
    'escape' => ['Escape', true],
    'enter' => ['Enter', false],
    'meta enter' => ['Meta+Enter', false],
]);

test('it denies actions once the step budget is exhausted', function (): void {
    $decision = gate(
        Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']),
        contract: contract(budget: 3),
        step: 3,
    );

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('budget');
});

test('it always allows terminal actions even off host and over budget', function (string $type): void {
    $decision = gate(
        Action::fromArray(['action' => $type, 'why' => 'done']),
        feed('https://example.com/'),
        contract(budget: 1),
        step: 99,
    );

    expect($decision->isAllowed())->toBeTrue();
})->with([['finish'], ['abort']]);
