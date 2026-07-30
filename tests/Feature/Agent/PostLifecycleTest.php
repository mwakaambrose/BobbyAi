<?php

use App\Ai\Agents\CopywriterAgent;
use App\Enums\MessageRole;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
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

test('a brief creates a post with three drafts and a thread', function (): void {
    [$user, $team] = teamUser();
    fakeDrafts();
    $account = SocialAccount::factory()->connected()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post(route('posts.store', $team->slug), [
        'brief' => 'Announce Growth Savings in a warm tone.',
        'social_account_id' => $account->id,
    ]);

    $post = Post::sole();

    $response->assertRedirect(route('posts.show', [$team->slug, $post]));

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->drafts)->toHaveCount(3)
        ->and($post->messages()->where('role', MessageRole::User)->count())->toBe(1)
        ->and($post->messages()->where('role', MessageRole::Agent)->count())->toBe(1);
});

test('a revision request regenerates the drafts', function (): void {
    [$user, $team] = teamUser();
    fakeDrafts();
    $post = Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Draft]);

    $this->actingAs($user)
        ->post(route('posts.messages.store', [$team->slug, $post]), ['body' => 'Make it shorter.'])
        ->assertRedirect();

    expect($post->fresh()->drafts)->toHaveCount(3)
        ->and($post->fresh()->messages()->count())->toBe(2);
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
