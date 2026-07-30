<?php

use App\Agent\Copywriter;
use App\Ai\Agents\CopywriterAgent;
use App\Models\BrandProfile;
use App\Models\Post;
use Laravel\Ai\Prompts\AgentPrompt;

function draftPayload(string $suffix = ''): array
{
    return ['drafts' => [
        ['body' => 'Hook led draft.'.$suffix, 'rationale' => 'Opens with a hook.'],
        ['body' => 'Story led draft.'.$suffix, 'rationale' => 'Opens with a member story.'],
        ['body' => 'Question led draft.'.$suffix, 'rationale' => 'Opens with a question.'],
    ]];
}

test('it persists three drafts with character counts', function (): void {
    CopywriterAgent::fake([draftPayload()]);

    $post = Post::factory()->create();

    $drafts = app(Copywriter::class)->draftsFor($post, $post->brief, null);

    expect($drafts)->toHaveCount(3)
        ->and($drafts->pluck('variant')->all())->toBe(['A', 'B', 'C'])
        ->and($drafts->first()->character_count)->toBe(mb_strlen('Hook led draft.'))
        ->and($drafts->first()->model)->toBe(config('agent.model'));
});

test('it conditions the prompt on the brand profile', function (): void {
    CopywriterAgent::fake([draftPayload()]);

    $post = Post::factory()->create();
    $brand = BrandProfile::factory()->create([
        'team_id' => $post->team_id,
        'voice' => 'Warm and direct.',
        'banned_terms' => ['synergy'],
    ]);

    app(Copywriter::class)->draftsFor($post, 'Announce Growth Savings.', $brand);

    CopywriterAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Warm and direct.')
        && $prompt->contains('synergy')
        && $prompt->contains('Announce Growth Savings.'));
});

test('it regenerates once when a draft uses a banned term', function (): void {
    CopywriterAgent::fake([
        ['drafts' => [
            ['body' => 'Real synergy here.', 'rationale' => 'Bad.'],
            ['body' => 'Fine copy.', 'rationale' => 'Good.'],
            ['body' => 'Also fine.', 'rationale' => 'Good.'],
        ]],
        draftPayload(' clean'),
    ]);

    $post = Post::factory()->create();
    $brand = BrandProfile::factory()->create(['team_id' => $post->team_id, 'banned_terms' => ['synergy']]);

    $drafts = app(Copywriter::class)->draftsFor($post, $post->brief, $brand);

    expect($drafts->pluck('body')->implode(' '))->not->toContain('synergy');
});

test('it detects banned terms case insensitively on word boundaries', function (): void {
    $copywriter = app(Copywriter::class);

    expect($copywriter->bannedTermsIn('Real Synergy here.', ['synergy']))->toBe(['synergy'])
        ->and($copywriter->bannedTermsIn('Synergistic thinking.', ['synergy']))->toBe([]);
});

test('it replaces previous drafts when revising', function (): void {
    CopywriterAgent::fake([draftPayload(), draftPayload(' v2')]);

    $post = Post::factory()->create();
    $copywriter = app(Copywriter::class);

    $copywriter->draftsFor($post, $post->brief, null);
    $revised = $copywriter->draftsFor($post, 'Make it shorter.', null, revisionOf: 'Hook led draft.');

    expect($post->fresh()->drafts)->toHaveCount(3)
        ->and($revised->first()->body)->toBe('Hook led draft. v2');
});
