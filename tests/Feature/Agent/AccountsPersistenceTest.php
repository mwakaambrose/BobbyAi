<?php

use App\Enums\AccountStatus;
use App\Enums\SocialPlatform;
use App\Models\BrandProfile;
use App\Models\SocialAccount;
use App\Models\Team;

test('a social account belongs to a team and casts its status', function (): void {
    $account = SocialAccount::factory()->create();

    expect($account->team)->toBeInstanceOf(Team::class)
        ->and($account->platform)->toBe(SocialPlatform::LinkedIn)
        ->and($account->status)->toBe(AccountStatus::Disconnected);
});

test('the connected scope only returns connected accounts', function (): void {
    SocialAccount::factory()->create();
    $connected = SocialAccount::factory()->connected()->create();

    expect(SocialAccount::connected()->pluck('id')->all())->toBe([$connected->id]);
});

test('each account has an isolated browser profile directory', function (): void {
    $account = SocialAccount::factory()->create();

    expect($account->profileDirectory())
        ->toBe(config('agent.profile_path').'/'.$account->id);
});

test('a brand profile casts its json columns to arrays', function (): void {
    $brand = BrandProfile::factory()->create([
        'topics' => ['savings', 'fintech'],
        'banned_terms' => ['synergy'],
        'examples' => ['We built this for members.'],
    ]);

    expect($brand->topics)->toBe(['savings', 'fintech'])
        ->and($brand->banned_terms)->toBe(['synergy'])
        ->and($brand->examples)->toBe(['We built this for members.']);
});
