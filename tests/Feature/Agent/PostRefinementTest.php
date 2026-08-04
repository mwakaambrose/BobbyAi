<?php

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Jobs\RefineDraftSpan;
use App\Models\Post;
use Illuminate\Support\Facades\Queue;

test('asking for a fragment rewrite queues the job', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('posts.refinements.store', [$team->slug, $post]), [
            'before' => 'Last quarter we shipped four features. ',
            'selection' => 'It was a big deal for the team.',
            'after' => '',
            'instruction' => 'make this land harder',
        ])
        ->assertRedirect();

    Queue::assertPushed(RefineDraftSpan::class, fn (RefineDraftSpan $job): bool => $job->post->is($post)
        && $job->selection === 'It was a big deal for the team.'
        && $job->instruction === 'make this land harder');
});

test('an empty selection is refused before anything is queued', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('posts.refinements.store', [$team->slug, $post]), [
            'before' => 'Some text.',
            'selection' => '',
            'after' => '',
            'instruction' => 'make this land harder',
        ])
        ->assertSessionHasErrors('selection');

    Queue::assertNothingPushed();
});

test('accepting a rewrite records both sides of the exchange', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('posts.refinements.accept', [$team->slug, $post]), [
            'instruction' => 'make this land harder',
            'before' => 'It was a big deal for the team.',
            'after' => 'Four features. Zero all-nighters.',
        ])
        ->assertRedirect();

    $messages = $post->messages()->orderBy('id')->get();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->role)->toBe(MessageRole::User)
        ->and($messages[0]->kind)->toBe(MessageKind::Text)
        ->and($messages[0]->body)->toBe('make this land harder')
        ->and($messages[1]->role)->toBe(MessageRole::Agent)
        ->and($messages[1]->kind)->toBe(MessageKind::Edit)
        ->and($messages[1]->payload['before'])->toBe('It was a big deal for the team.')
        ->and($messages[1]->payload['after'])->toBe('Four features. Zero all-nighters.');
});

test('another team cannot refine a post', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $post = Post::factory()->create();

    $this->actingAs($user)
        ->post(route('posts.refinements.store', [$team->slug, $post]), [
            'before' => '',
            'selection' => 'a fragment',
            'after' => '',
            'instruction' => 'tighten it',
        ])
        ->assertNotFound();

    Queue::assertNothingPushed();
});
