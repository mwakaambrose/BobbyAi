<?php

use App\Ai\Agents\ComposerAgent;
use App\Ai\Agents\CopywriterAgent;
use App\Enums\MessageRole;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Jobs\DraftPostContent;
use App\Jobs\PublishPostRun;
use App\Models\AgentRun;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Queue;

function fakeDrafts(): void
{
    CopywriterAgent::fake([
        ['drafts' => [
            ['body' => 'Hook led draft.', 'rationale' => 'Hook.'],
            ['body' => 'Story led draft.', 'rationale' => 'Story.'],
            ['body' => 'Question led draft.', 'rationale' => 'Question.'],
        ]],
    ]);
}

test('a brief creates a post with a draft and a thread', function (): void {
    [$user, $team] = teamUser();
    ComposerAgent::fake(['A streamed draft.']);
    $account = SocialAccount::factory()->connected()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post(route('posts.store', $team->slug), [
        'brief' => 'Announce Growth Savings in a warm tone.',
        'social_account_id' => $account->id,
    ]);

    $post = Post::sole();

    $response->assertRedirect(route('posts.show', [$team->slug, $post]));

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->drafts)->toHaveCount(1)
        ->and($post->drafts->first()->body)->toBe('A streamed draft.')
        ->and($post->messages()->where('role', MessageRole::User)->count())->toBe(1)
        ->and($post->messages()->where('role', MessageRole::Agent)->count())->toBe(1);
});

test('creating a post queues the drafting work instead of blocking the request', function (): void {
    Queue::fake();

    [$user, $team] = teamUser();
    SocialAccount::factory()->connected()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('posts.store', $team->slug), ['brief' => 'Announce the Series A'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $post = Post::sole();

    expect($post->drafts()->count())->toBe(0)
        ->and($post->messages()->where('role', MessageRole::User)->count())->toBe(1);

    Queue::assertPushed(DraftPostContent::class, fn (DraftPostContent $job): bool => $job->post->is($post)
        && $job->brief === 'Announce the Series A');
});

test('a brief without an account uses the connected one', function (): void {
    Queue::fake();

    [$user, $team] = teamUser();
    SocialAccount::factory()->create(['team_id' => $team->id]);
    $connected = SocialAccount::factory()->connected()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('posts.store', $team->slug), ['brief' => 'Announce the Series A'])
        ->assertSessionHasNoErrors();

    expect(Post::sole()->social_account_id)->toBe($connected->id);
});

test('a brief is refused when no account is connected', function (): void {
    Queue::fake();

    [$user, $team] = teamUser();

    $this->actingAs($user)
        ->post(route('posts.store', $team->slug), ['brief' => 'Announce the Series A'])
        ->assertSessionHasErrors('social_account_id');

    expect(Post::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a revision request regenerates the draft', function (): void {
    [$user, $team] = teamUser();
    ComposerAgent::fake(['A shorter draft.']);
    $post = Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Draft]);

    $this->actingAs($user)
        ->post(route('posts.messages.store', [$team->slug, $post]), ['body' => 'Make it shorter.'])
        ->assertRedirect();

    expect($post->fresh()->drafts)->toHaveCount(1)
        ->and($post->fresh()->messages()->count())->toBe(2);
});

test('a revision request queues a redraft against the current draft', function (): void {
    Queue::fake();

    [$user, $team] = teamUser();

    $post = Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Draft]);
    $post->drafts()->create([
        'variant' => 'A',
        'body' => 'The current draft.',
        'character_count' => 18,
    ]);

    $this->actingAs($user)
        ->post(route('posts.messages.store', [$team->slug, $post]), ['body' => 'Make it punchier'])
        ->assertRedirect();

    Queue::assertPushed(DraftPostContent::class, fn (DraftPostContent $job): bool => $job->brief === 'Make it punchier'
        && $job->revisionOf === 'The current draft.');
});

test('approving snapshots the body and queues a run', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->connected()->create(['team_id' => $team->id]);
    $post = Post::factory()->create([
        'team_id' => $team->id,
        'social_account_id' => $account->id,
        'status' => PostStatus::Draft,
    ]);

    $this->actingAs($user)
        ->post(route('posts.approve', [$team->slug, $post]), ['body' => 'The exact approved copy.'])
        ->assertRedirect();

    $post->refresh();
    $run = AgentRun::sole();

    expect($post->status)->toBe(PostStatus::Publishing)
        ->and($post->approved_body)->toBe('The exact approved copy.')
        ->and($post->approved_by)->toBe($user->id)
        ->and($run->contract['approved_body'])->toBe('The exact approved copy.')
        ->and($run->status)->toBe(RunStatus::Queued);

    Queue::assertPushed(PublishPostRun::class);
});

test('approving is rejected when the account is not connected', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->create(['team_id' => $team->id]);
    $post = Post::factory()->create([
        'team_id' => $team->id,
        'social_account_id' => $account->id,
        'status' => PostStatus::Draft,
    ]);

    $this->actingAs($user)
        ->post(route('posts.approve', [$team->slug, $post]), ['body' => 'Copy.'])
        ->assertSessionHasErrors('social_account_id');

    Queue::assertNothingPushed();
});

test('an active run can be aborted', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Publishing]);
    $run = AgentRun::factory()->create(['post_id' => $post->id, 'status' => RunStatus::Running]);

    $this->actingAs($user)
        ->post(route('runs.abort', [$team->slug, $run]))
        ->assertRedirect();

    expect($run->fresh()->status)->toBe(RunStatus::Aborted)
        ->and($post->fresh()->status)->toBe(PostStatus::Failed);
});

test('posts belonging to another team are not reachable', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create();

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]))
        ->assertNotFound();
});
