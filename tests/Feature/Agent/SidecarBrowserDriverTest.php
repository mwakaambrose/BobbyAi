<?php

use App\Agent\Browser\SessionHandle;
use App\Agent\Browser\SidecarBrowserDriver;
use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Action;
use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('the contract resolves to the sidecar driver', function (): void {
    expect(app(BrowserDriver::class))->toBeInstanceOf(SidecarBrowserDriver::class);
});

test('it opens a session on the account profile directory', function (): void {
    Http::fake(['*/sessions' => Http::response(['sessionId' => 's1-9', 'url' => '', 'title' => ''], 201)]);

    $account = SocialAccount::factory()->create();
    $session = app(BrowserDriver::class)->openSession($account, headed: true);

    expect($session->id)->toBe('s1-9');

    Http::assertSent(fn ($request): bool => $request['profileDir'] === $account->profileDirectory()
        && $request['headed'] === true
        && $request['accountId'] === $account->id);
});

test('it maps an observation response into the value object', function (): void {
    Http::fake(['*/observation*' => Http::response([
        'url' => 'https://www.linkedin.com/feed/',
        'title' => 'Feed | LinkedIn',
        'screenshot' => 'ZmFrZQ==',
        'elements' => [['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []]],
    ])]);

    $observation = app(BrowserDriver::class)->observe(new SessionHandle('s1'));

    expect($observation->host())->toBe('www.linkedin.com')
        ->and($observation->element(7)->name)->toBe('Start a post')
        ->and($observation->screenshot)->toBe('ZmFrZQ==');
});

test('it posts the action payload and reports success', function (): void {
    Http::fake(['*/actions' => Http::response(['ok' => true, 'url' => '', 'title' => ''])]);

    $result = app(BrowserDriver::class)->act(
        new SessionHandle('s1'),
        Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']),
    );

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request): bool => $request['action'] === 'click' && $request['ref'] === 7);
});

test('it converts a sidecar error into a failed result', function (): void {
    Http::fake(['*/actions' => Http::response(['ok' => false, 'error' => 'element detached'], 500)]);

    $result = app(BrowserDriver::class)->act(
        new SessionHandle('s1'),
        Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toBe('element detached');
});

test('it converts an unreachable sidecar into a failed result', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $result = app(BrowserDriver::class)->act(
        new SessionHandle('s1'),
        Action::fromArray(['action' => 'wait', 'why' => 'settle']),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('Connection refused');
});
