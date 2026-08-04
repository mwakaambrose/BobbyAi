<?php

use App\Agent\BacklogSequencer;
use App\Enums\IdeaKind;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\PostIdea;
use App\Models\Team;

beforeEach(function (): void {
    $this->sequencer = app(BacklogSequencer::class);
    $this->team = Team::factory()->create();
});

test('it takes the first unused idea in position order', function (): void {
    PostIdea::factory()->for($this->team)->atPosition(1)->create(['body' => 'Second']);
    PostIdea::factory()->for($this->team)->atPosition(0)->create(['body' => 'First']);

    $selection = $this->sequencer->next($this->team);

    expect($selection->brief)->toBe('First')
        ->and($selection->idea)->not->toBeNull();
});

test('it rejects an idea of the same kind as the most recent post', function (): void {
    $post = Post::factory()->for($this->team)->create();

    PostIdea::factory()->for($this->team)->kind(IdeaKind::Announcement)->used($post)->create();

    PostIdea::factory()->for($this->team)->kind(IdeaKind::Announcement)->atPosition(0)
        ->create(['body' => 'Another announcement']);
    PostIdea::factory()->for($this->team)->kind(IdeaKind::Lesson)->atPosition(1)
        ->create(['body' => 'A lesson']);

    expect($this->sequencer->next($this->team)->brief)->toBe('A lesson');
});

test('it prefers the kind that has gone longest without being drafted', function (): void {
    PostIdea::factory()->for($this->team)->kind(IdeaKind::Lesson)->create([
        'used_at' => now()->subDays(1),
        'post_id' => Post::factory()->for($this->team)->create()->id,
    ]);

    PostIdea::factory()->for($this->team)->kind(IdeaKind::Lesson)->atPosition(0)
        ->create(['body' => 'Recently used kind']);
    PostIdea::factory()->for($this->team)->kind(IdeaKind::Story)->atPosition(1)
        ->create(['body' => 'Never used kind']);

    expect($this->sequencer->next($this->team)->brief)->toBe('Never used kind');
});

test('it still drafts when every remaining idea is the kind just used', function (): void {
    $post = Post::factory()->for($this->team)->create();

    PostIdea::factory()->for($this->team)->kind(IdeaKind::Announcement)->used($post)->create();
    PostIdea::factory()->for($this->team)->kind(IdeaKind::Announcement)->atPosition(0)
        ->create(['body' => 'The only thing left']);

    expect($this->sequencer->next($this->team)->brief)->toBe('The only thing left');
});

test('it treats an unclassified idea as a wildcard rather than skipping it', function (): void {
    $post = Post::factory()->for($this->team)->create();

    PostIdea::factory()->for($this->team)->kind(IdeaKind::Announcement)->used($post)->create();

    PostIdea::factory()->for($this->team)->unclassified()->atPosition(0)
        ->create(['body' => 'Not classified yet']);

    expect($this->sequencer->next($this->team)->brief)->toBe('Not classified yet');
});

test('it falls back to a neglected brand pillar when the backlog is empty', function (): void {
    BrandProfile::factory()->for($this->team)->create(['topics' => ['local inference']]);

    $selection = $this->sequencer->next($this->team);

    expect($selection->brief)->toContain('local inference')
        ->and($selection->idea)->toBeNull();
});

test('it prefers the backlog over brand pillars', function (): void {
    BrandProfile::factory()->for($this->team)->create(['topics' => ['local inference']]);
    PostIdea::factory()->for($this->team)->create(['body' => 'From the backlog']);

    expect($this->sequencer->next($this->team)->brief)->toBe('From the backlog');
});

test('it returns nothing when the backlog and the pillars are both exhausted', function (): void {
    expect($this->sequencer->next($this->team))->toBeNull();
});

test('it ignores ideas that have already been drafted', function (): void {
    PostIdea::factory()->for($this->team)->used()->create();

    expect($this->sequencer->next($this->team))->toBeNull();
});

test('it ignores another team\'s backlog', function (): void {
    PostIdea::factory()->create(['body' => 'Someone else\'s idea']);

    expect($this->sequencer->next($this->team))->toBeNull();
});
