<?php

use App\Agent\Reviser;
use App\Ai\Agents\EditorAgent;
use App\Events\RefineChunk;
use App\Events\RefineCompleted;
use App\Events\RefineFailed;
use App\Jobs\RefineDraftSpan;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function refineJob(Post $post): RefineDraftSpan
{
    return new RefineDraftSpan(
        post: $post,
        before: 'Last quarter we shipped four features. ',
        selection: 'It was a big deal for the team.',
        after: '',
        instruction: 'make this land harder',
    );
}

test('the replacement is broadcast as it is written', function (): void {
    Event::fake([RefineChunk::class, RefineCompleted::class]);
    EditorAgent::fake(['Three word replacement']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    refineJob($post)->handle(app(Reviser::class));

    Event::assertDispatchedTimes(RefineChunk::class, 3);
    Event::assertDispatched(RefineChunk::class, fn (RefineChunk $event): bool => $event->postId === $post->id
        && $event->delta === 'Three');
});

test('completion carries the settled replacement', function (): void {
    Event::fake([RefineCompleted::class]);
    EditorAgent::fake(['Four features. Zero all-nighters.']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    refineJob($post)->handle(app(Reviser::class));

    Event::assertDispatched(RefineCompleted::class, fn (RefineCompleted $event): bool => $event->postId === $post->id
        && $event->replacement === 'Four features. Zero all-nighters.');
});

test('nothing is persisted by a refinement', function (): void {
    Event::fake();
    EditorAgent::fake(['A replacement.']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    refineJob($post)->handle(app(Reviser::class));

    expect($post->drafts()->count())->toBe(0)
        ->and($post->messages()->count())->toBe(0);
});

test('a failure is announced without leaving a thread turn behind', function (): void {
    Event::fake([RefineFailed::class]);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    refineJob($post)->failed(new RuntimeException('provider down'));

    expect($post->messages()->count())->toBe(0);

    Event::assertDispatched(RefineFailed::class, fn (RefineFailed $event): bool => $event->postId === $post->id
        && $event->reason === 'provider down');
});
