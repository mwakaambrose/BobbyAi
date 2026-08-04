<?php

use App\Agent\NarrativeContextBuilder;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Team;

beforeEach(function (): void {
    $this->builder = app(NarrativeContextBuilder::class);
    $this->team = Team::factory()->create();
});

/**
 * Create a published post for the team.
 */
function published(Team $team, string $body, int $daysAgo = 1): Post
{
    return Post::factory()->for($team)->create([
        'status' => PostStatus::Published,
        'approved_body' => $body,
        'published_at' => now()->subDays($daysAgo),
    ]);
}

test('it is empty for a team with nothing published', function (): void {
    expect($this->builder->for($this->team)->isEmpty())->toBeTrue()
        ->and($this->builder->for($this->team)->toPrompt())->toBeNull();
});

test('it returns the three most recent published posts, newest first', function (): void {
    published($this->team, 'Oldest', 10);
    published($this->team, 'Older', 5);
    published($this->team, 'Newer', 2);
    published($this->team, 'Newest', 1);

    $context = $this->builder->for($this->team);

    expect($context->recent)->toBe(['Newest', 'Newer', 'Older']);
});

test('it excludes drafts that were never approved', function (): void {
    published($this->team, 'Published post');
    Post::factory()->for($this->team)->create([
        'status' => PostStatus::Draft,
        'approved_body' => 'Rejected draft',
    ]);

    expect($this->builder->for($this->team)->recent)->toBe(['Published post']);
});

test('it trims a long post to an excerpt', function (): void {
    published($this->team, str_repeat('a', 900));

    $excerpt = $this->builder->for($this->team)->recent[0];

    expect(mb_strlen($excerpt))->toBe(401)
        ->and($excerpt)->toEndWith('…');
});

test('it falls back to the brief when a published post has no body', function (): void {
    Post::factory()->for($this->team)->create([
        'status' => PostStatus::Published,
        'brief' => 'The brief we used',
        'approved_body' => null,
        'published_at' => now(),
    ]);

    expect($this->builder->for($this->team)->recent)->toBe(['The brief we used']);
});

test('it renders a prompt fragment that asks the model to build on the history', function (): void {
    published($this->team, 'What we shipped last week');

    expect($this->builder->for($this->team)->toPrompt())
        ->toContain('RECENTLY PUBLISHED')
        ->toContain('What we shipped last week')
        ->toContain('do not reuse their angles');
});

test('it ignores another team\'s published posts', function (): void {
    published(Team::factory()->create(), 'Someone else\'s post');

    expect($this->builder->for($this->team)->isEmpty())->toBeTrue();
});
