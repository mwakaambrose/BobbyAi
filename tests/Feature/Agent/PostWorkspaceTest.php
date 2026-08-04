<?php

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Post;
use App\Models\PostDraft;
use Inertia\Testing\AssertableInertia as Assert;

test('the workspace renders the thread, drafts and run', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Draft]);
    PostDraft::factory()->count(3)->create(['post_id' => $post->id]);
    AgentRun::factory()->create(['post_id' => $post->id, 'status' => RunStatus::Running]);

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('posts/show')
            ->where('post.id', $post->id)
            ->has('drafts', 3)
            ->where('run.status', 'running')
            ->etc());
});

test('the steps prop is deferred', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]))
        ->assertInertia(fn (Assert $page): Assert => $page->missing('steps')->etc());
});

test('the deferred steps load on request', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);
    AgentStep::factory()->create(['agent_run_id' => $run->id, 'index' => 0]);
    AgentStep::factory()->create(['agent_run_id' => $run->id, 'index' => 1]);

    partialReload($this->actingAs($user), route('posts.show', [$team->slug, $post]), 'posts/show', 'steps')
        ->assertOk()
        ->assertJsonCount(2, 'props.steps')
        ->assertJsonPath('props.steps.0.action.action', 'click');
});

test('an accepted edit reaches the workspace as a thread turn', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $post->messages()->create([
        'role' => MessageRole::Agent,
        'kind' => MessageKind::Edit,
        'body' => 'Edited one fragment.',
        'payload' => ['before' => 'the old line', 'after' => 'the new line'],
    ]);

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('posts/show')
            ->where('messages.0.kind', 'edit')
            ->where('messages.0.payload.after', 'the new line')
            ->etc());
});
