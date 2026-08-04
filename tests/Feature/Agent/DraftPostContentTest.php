<?php

use App\Agent\Composer;
use App\Ai\Agents\ComposerAgent;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Events\DraftChunk;
use App\Events\DraftCompleted;
use App\Events\DraftFailed;
use App\Jobs\DraftPostContent;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('the job persists a single draft and announces completion', function () {
    Event::fake([DraftChunk::class, DraftCompleted::class, DraftFailed::class]);
    ComposerAgent::fake(['We shipped something worth talking about.']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create([
        'author_id' => $user->id,
        'brief' => 'Announce the Series A',
    ]);

    (new DraftPostContent($post, $post->brief))->handle(app(Composer::class));

    $draft = $post->drafts()->sole();

    expect($draft->variant)->toBe('A')
        ->and($draft->body)->toBe('We shipped something worth talking about.')
        ->and($draft->character_count)->toBe(41);

    Event::assertDispatched(DraftCompleted::class, fn (DraftCompleted $event): bool => $event->postId === $post->id
        && $event->draftId === $draft->id);
});

test('the draft is broadcast as it is written', function () {
    Event::fake([DraftChunk::class]);
    ComposerAgent::fake(['Three word draft']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    (new DraftPostContent($post, 'Announce the Series A'))->handle(app(Composer::class));

    Event::assertDispatchedTimes(DraftChunk::class, 3);
    Event::assertDispatched(DraftChunk::class, fn (DraftChunk $event): bool => $event->postId === $post->id
        && $event->delta === 'Three');
});

test('the job records the agent turn in the thread', function () {
    Event::fake();
    ComposerAgent::fake(['A draft.']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    (new DraftPostContent($post, 'Announce the Series A'))->handle(app(Composer::class));

    $message = $post->messages()->where('role', MessageRole::Agent)->sole();

    expect($message->kind)->toBe(MessageKind::Drafts);
});

test('a draft using a banned term is regenerated before it is persisted', function () {
    Event::fake();
    ComposerAgent::fake(['Our synergy is unmatched.', 'Our team is unmatched.']);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    BrandProfile::factory()->for($team)->create(['banned_terms' => ['synergy']]);

    $post = Post::factory()->for($team)->create(['author_id' => $user->id]);

    (new DraftPostContent($post, 'Announce the Series A'))->handle(app(Composer::class));

    expect($post->drafts()->sole()->body)->toBe('Our team is unmatched.');
});

test('a provider error is not swallowed', function () {
    ComposerAgent::fake(fn () => throw new RuntimeException('provider down'));

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    $job = new DraftPostContent($post, 'Announce the Series A');

    expect(fn () => $job->handle(app(Composer::class)))->toThrow(RuntimeException::class);
    expect($post->drafts()->count())->toBe(0);
});

test('a failure leaves an error in the thread rather than a silent stall', function () {
    Event::fake([DraftFailed::class]);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    (new DraftPostContent($post, 'Announce the Series A'))
        ->failed(new RuntimeException('provider down'));

    $message = $post->messages()->where('kind', MessageKind::Error)->sole();

    expect($message->role)->toBe(MessageRole::Agent)
        ->and($message->payload['reason'])->toBe('provider down');

    Event::assertDispatched(DraftFailed::class, fn (DraftFailed $event): bool => $event->postId === $post->id
        && $event->reason === 'provider down');
});
