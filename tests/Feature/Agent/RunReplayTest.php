<?php

use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('the runs index lists the team runs', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    AgentRun::factory()->count(2)->create(['post_id' => $post->id]);
    AgentRun::factory()->create();

    $this->actingAs($user)
        ->get(route('runs.index', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('runs/index')
            ->has('runs.data', 2)
            ->etc());
});

test('a run can be replayed step by step', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);
    AgentStep::factory()->create(['agent_run_id' => $run->id, 'index' => 0]);

    partialReload($this->actingAs($user), route('runs.show', [$team->slug, $run]), 'runs/show', 'steps')
        ->assertOk()
        ->assertJsonCount(1, 'props.steps')
        ->assertJsonPath('props.steps.0.index', 0);
});

test('screenshots are streamed from the private disk', function (): void {
    Storage::fake('local');
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);
    $step = AgentStep::factory()->create([
        'agent_run_id' => $run->id,
        'screenshot_path' => 'agent-screenshots/1/000.png',
    ]);
    Storage::disk('local')->put('agent-screenshots/1/000.png', 'png-bytes');

    $this->actingAs($user)
        ->get(route('runs.screenshot', [$team->slug, $run, $step]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('another team cannot read a run or its screenshots', function (): void {
    [$user, $team] = teamUser();
    $run = AgentRun::factory()->create();
    $step = AgentStep::factory()->create(['agent_run_id' => $run->id]);

    $this->actingAs($user)->get(route('runs.show', [$team->slug, $run]))->assertNotFound();
    $this->actingAs($user)->get(route('runs.screenshot', [$team->slug, $run, $step]))->assertNotFound();
});
