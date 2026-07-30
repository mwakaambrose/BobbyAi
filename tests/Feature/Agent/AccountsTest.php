<?php

use App\Agent\Browser\FakeBrowserDriver;
use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Observation;
use App\Enums\AccountStatus;
use App\Models\SocialAccount;
use Inertia\Testing\AssertableInertia as Assert;

function fakeObservation(string $url, array $elements = []): void
{
    app()->instance(BrowserDriver::class, (new FakeBrowserDriver)->script([
        Observation::fromArray(['url' => $url, 'title' => 'LinkedIn', 'elements' => $elements]),
    ]));
}

test('the accounts page lists the team accounts', function (): void {
    [$user, $team] = teamUser();
    SocialAccount::factory()->connected()->create(['team_id' => $team->id, 'handle' => 'zimba']);

    $this->actingAs($user)
        ->get(route('accounts.index', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('accounts/index')
            ->has('accounts', 1)
            ->where('accounts.0.handle', 'zimba')
            ->etc());
});

test('connecting opens a headed browser session', function (): void {
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->create(['team_id' => $team->id]);
    fakeObservation('https://www.linkedin.com/login');

    $this->actingAs($user)
        ->post(route('accounts.connect', [$team->slug, $account]))
        ->assertRedirect();

    expect($account->fresh()->status)->toBe(AccountStatus::Disconnected);
});

test('verifying a signed in session marks the account connected', function (): void {
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->create(['team_id' => $team->id]);
    fakeObservation('https://www.linkedin.com/feed/', [
        ['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []],
    ]);

    $this->actingAs($user)
        ->post(route('accounts.verify', [$team->slug, $account]))
        ->assertRedirect();

    expect($account->fresh()->status)->toBe(AccountStatus::Connected)
        ->and($account->fresh()->last_verified_at)->not->toBeNull();
});

test('verifying a logged out session marks the account expired', function (): void {
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->connected()->create(['team_id' => $team->id]);
    fakeObservation('https://www.linkedin.com/login', [
        ['ref' => 1, 'role' => 'textbox', 'name' => 'Email or phone', 'state' => []],
    ]);

    $this->actingAs($user)->post(route('accounts.verify', [$team->slug, $account]));

    expect($account->fresh()->status)->toBe(AccountStatus::Expired);
});

test('accounts from another team are not reachable', function (): void {
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->create();
    fakeObservation('https://www.linkedin.com/feed/');

    $this->actingAs($user)
        ->post(route('accounts.verify', [$team->slug, $account]))
        ->assertNotFound();
});
