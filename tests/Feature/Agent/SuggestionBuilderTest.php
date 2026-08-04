<?php

use App\Agent\SuggestionBuilder;
use App\Enums\PostStatus;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\User;

test('an unfinished recent draft is offered first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $post = Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Announce the Series A',
        'status' => PostStatus::Draft,
        'updated_at' => now()->subDay(),
    ]);

    $suggestions = app(SuggestionBuilder::class)->for($team);

    expect($suggestions[0]['label'])->toBe('Finish: Announce the Series A')
        ->and($suggestions[0]['hint'])->toBe('Draft in progress')
        ->and($suggestions[0]['href'])->toContain("/posts/{$post->id}")
        ->and($suggestions[0]['prompt'])->toBeNull();
});

test('a draft untouched for more than a week is not offered', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'status' => PostStatus::Draft,
        'updated_at' => now()->subDays(8),
    ]);

    $labels = collect(app(SuggestionBuilder::class)->for($team))->pluck('label');

    expect($labels->filter(fn (string $label): bool => str_starts_with($label, 'Finish:')))->toBeEmpty();
});

test('an approved post is offered for publishing', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Hiring two engineers',
        'status' => PostStatus::Approved,
    ]);

    $suggestions = app(SuggestionBuilder::class)->for($team);

    expect($suggestions[0]['label'])->toBe('Approve and publish')
        ->and($suggestions[0]['hint'])->toBe('Hiring two engineers');
});

test('an unused brand pillar becomes a prompt suggestion', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    BrandProfile::factory()->for($team)->create(['topics' => ['developer tooling']]);

    $suggestions = app(SuggestionBuilder::class)->for($team);

    expect($suggestions[0]['label'])->toBe('Write about developer tooling')
        ->and($suggestions[0]['prompt'])->toBe('Write a post about developer tooling')
        ->and($suggestions[0]['href'])->toBeNull();
});

test('a pillar covered in the last fortnight is not suggested', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    BrandProfile::factory()->for($team)->create(['topics' => ['developer tooling']]);

    Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Why developer tooling matters',
        'status' => PostStatus::Published,
        'published_at' => now()->subDays(3),
    ]);

    $labels = collect(app(SuggestionBuilder::class)->for($team))->pluck('label');

    expect($labels)->not->toContain('Write about developer tooling');
});

test('the usual posting weekday becomes a cadence suggestion', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    foreach ([7, 14, 21] as $daysAgo) {
        Post::factory()->for($team)->create([
            'author_id' => $user->id,
            'status' => PostStatus::Published,
            'published_at' => now()->startOfWeek()->addDay()->subDays($daysAgo),
        ]);
    }

    $labels = collect(app(SuggestionBuilder::class)->for($team))->pluck('label');

    expect($labels)->toContain('Your weekly Tuesday update');
});

test('a brand new team gets generic starters', function () {
    $user = User::factory()->create();

    $suggestions = app(SuggestionBuilder::class)->for($user->currentTeam);

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions[0]['label'])->toBe('Announce a milestone')
        ->and($suggestions[0]['prompt'])->not->toBeNull();
});

test('no more than three suggestions are returned', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    BrandProfile::factory()->for($team)->create([
        'topics' => ['one', 'two', 'three', 'four'],
    ]);

    Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'status' => PostStatus::Draft,
        'updated_at' => now(),
    ]);

    expect(app(SuggestionBuilder::class)->for($team))->toHaveCount(3);
});

test("another team's work is never suggested", function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $other = User::factory()->create()->currentTeam;

    Post::factory()->for($other)->create([
        'author_id' => $user->id,
        'brief' => 'Not yours',
        'status' => PostStatus::Draft,
        'updated_at' => now(),
    ]);

    $labels = collect(app(SuggestionBuilder::class)->for($team))->pluck('label');

    expect($labels)->not->toContain('Finish: Not yours');
});
