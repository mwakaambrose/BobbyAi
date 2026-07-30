<?php

use App\Enums\GateVerdict;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Post;
use App\Models\PostDraft;

test('a post exposes its drafts, messages and runs', function (): void {
    $post = Post::factory()->create();
    PostDraft::factory()->count(3)->create(['post_id' => $post->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);

    expect($post->status)->toBe(PostStatus::Brief)
        ->and($post->drafts)->toHaveCount(3)
        ->and($post->latestRun()->id)->toBe($run->id);
});

test('a run casts its contract and reports whether it is active', function (): void {
    $run = AgentRun::factory()->create([
        'contract' => ['allowed_host' => 'www.linkedin.com', 'step_budget' => 18],
    ]);

    expect($run->contract['allowed_host'])->toBe('www.linkedin.com')
        ->and($run->status)->toBe(RunStatus::Queued)
        ->and($run->isActive())->toBeTrue();
});

test('a step records the action and the gate verdict', function (): void {
    $step = AgentStep::factory()->create([
        'action' => ['action' => 'click', 'ref' => 7, 'why' => 'open the composer'],
        'gate_decision' => GateVerdict::Allowed,
    ]);

    expect($step->action['ref'])->toBe(7)
        ->and($step->gate_decision)->toBe(GateVerdict::Allowed)
        ->and($step->run)->toBeInstanceOf(AgentRun::class);
});

test('deleting a post cascades to its runs and steps', function (): void {
    $step = AgentStep::factory()->create();
    $post = $step->run->post;

    $post->delete();

    expect(AgentRun::count())->toBe(0)
        ->and(AgentStep::count())->toBe(0);
});
