<?php

use App\Ai\Agents\IdeaClassifierAgent;
use App\Enums\IdeaKind;
use App\Enums\KindSource;
use App\Jobs\ClassifyPostIdea;
use App\Models\PostIdea;

test('it labels an unclassified idea', function (): void {
    IdeaClassifierAgent::fake([['kind' => 'lesson']]);

    $idea = PostIdea::factory()->unclassified()->create(['body' => 'Why we run one worker']);

    (new ClassifyPostIdea($idea))->handle(app(IdeaClassifierAgent::class));

    $idea->refresh();

    expect($idea->kind)->toBe(IdeaKind::Lesson)
        ->and($idea->kind_source)->toBe(KindSource::Agent);
});

test('it never overwrites a kind a person set by hand', function (): void {
    IdeaClassifierAgent::fake([['kind' => 'lesson']]);

    $idea = PostIdea::factory()->correctedByUser(IdeaKind::Story)->create();

    (new ClassifyPostIdea($idea))->handle(app(IdeaClassifierAgent::class));

    $idea->refresh();

    expect($idea->kind)->toBe(IdeaKind::Story)
        ->and($idea->kind_source)->toBe(KindSource::User);
});

test('it leaves the idea unclassified when the model answers with nonsense', function (): void {
    IdeaClassifierAgent::fake([['kind' => 'interpretive dance']]);

    $idea = PostIdea::factory()->unclassified()->create();

    (new ClassifyPostIdea($idea))->handle(app(IdeaClassifierAgent::class));

    expect($idea->fresh()->kind)->toBeNull();
});
