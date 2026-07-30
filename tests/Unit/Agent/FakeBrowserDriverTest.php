<?php

use App\Agent\Browser\FakeBrowserDriver;
use App\Agent\Values\Action;
use App\Agent\Values\Observation;
use App\Models\SocialAccount;

test('it replays scripted observations in order and repeats the last one', function (): void {
    $driver = (new FakeBrowserDriver)->script([
        Observation::fromArray(['url' => 'https://www.linkedin.com/feed/', 'title' => 'One', 'elements' => []]),
        Observation::fromArray(['url' => 'https://www.linkedin.com/feed/', 'title' => 'Two', 'elements' => []]),
    ]);

    $session = $driver->openSession(SocialAccount::factory()->make());

    expect($driver->observe($session)->title)->toBe('One')
        ->and($driver->observe($session)->title)->toBe('Two')
        ->and($driver->observe($session)->title)->toBe('Two');
});

test('it records every action it is asked to perform', function (): void {
    $driver = (new FakeBrowserDriver)->script([
        Observation::fromArray(['url' => 'https://www.linkedin.com/feed/', 'title' => 'Feed', 'elements' => []]),
    ]);

    $session = $driver->openSession(SocialAccount::factory()->make());
    $driver->act($session, Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']));

    expect($driver->actions())->toHaveCount(1)
        ->and($driver->actions()[0]->ref)->toBe(7);
});

test('it can be told to fail the next action', function (): void {
    $driver = (new FakeBrowserDriver)->script([
        Observation::fromArray(['url' => 'https://www.linkedin.com/feed/', 'title' => 'Feed', 'elements' => []]),
    ]);

    $session = $driver->openSession(SocialAccount::factory()->make());
    $driver->failNextAction('element detached');

    $result = $driver->act($session, Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']));

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toBe('element detached');
});

test('it tracks session closure', function (): void {
    $driver = (new FakeBrowserDriver)->script([]);
    $session = $driver->openSession(SocialAccount::factory()->make());

    $driver->closeSession($session);

    expect($driver->wasClosed())->toBeTrue();
});
