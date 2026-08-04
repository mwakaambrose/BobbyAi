<?php

use App\Agent\Reviser;
use App\Ai\Agents\EditorAgent;
use App\Models\BrandProfile;
use App\Models\User;
use Laravel\Ai\Prompts\AgentPrompt;

test('the reviser returns only the replacement for the span', function (): void {
    EditorAgent::fake(['Four features. Zero all-nighters.']);

    $replacement = app(Reviser::class)->streamSpan(
        before: 'Last quarter we shipped four features. ',
        selection: 'It was a big deal for the team.',
        after: ' Here is what we learned.',
        instruction: 'make this land harder',
        brand: null,
        onDelta: fn (string $delta) => null,
    );

    expect($replacement)->toBe('Four features. Zero all-nighters.');
});

test('each delta is handed to the caller as it arrives', function (): void {
    EditorAgent::fake(['Three word replacement']);

    $deltas = [];

    app(Reviser::class)->streamSpan(
        before: '',
        selection: 'the old sentence',
        after: '',
        instruction: 'tighten it',
        brand: null,
        onDelta: function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        },
    );

    expect($deltas)->toHaveCount(3)
        ->and($deltas[0])->toBe('Three');
});

test('the brand profile conditions the rewrite', function (): void {
    EditorAgent::fake(['A replacement.']);

    $user = User::factory()->create();
    $brand = BrandProfile::factory()->for($user->currentTeam)->create([
        'voice' => 'Plain and direct',
        'banned_terms' => ['synergy'],
    ]);

    app(Reviser::class)->streamSpan(
        before: '',
        selection: 'the old sentence',
        after: '',
        instruction: 'tighten it',
        brand: $brand,
        onDelta: fn (string $delta) => null,
    );

    EditorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Plain and direct')
        && $prompt->contains('synergy'));
});
