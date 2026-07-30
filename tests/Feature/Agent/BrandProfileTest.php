<?php

use App\Models\BrandProfile;
use Inertia\Testing\AssertableInertia as Assert;

test('the brand page renders the current profile', function (): void {
    [$user, $team] = teamUser();
    BrandProfile::factory()->create(['team_id' => $team->id, 'voice' => 'Warm and direct.']);

    $this->actingAs($user)
        ->get(route('brand.edit', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('brand/edit')
            ->where('brand.voice', 'Warm and direct.')
            ->etc());
});

test('the brand profile can be created and updated', function (): void {
    [$user, $team] = teamUser();

    $this->actingAs($user)->put(route('brand.update', $team->slug), [
        'voice' => 'Warm, direct, jargon free.',
        'audience' => 'Members of a savings cooperative.',
        'topics' => ['savings', 'literacy'],
        'banned_terms' => ['synergy'],
        'examples' => ['We built this for members.'],
    ])->assertRedirect();

    $brand = $team->fresh()->brandProfile;

    expect($brand->voice)->toBe('Warm, direct, jargon free.')
        ->and($brand->topics)->toBe(['savings', 'literacy']);
});

test('another team brand profile is never exposed', function (): void {
    [$user, $team] = teamUser();
    BrandProfile::factory()->create(['voice' => 'Someone else.']);

    $this->actingAs($user)
        ->get(route('brand.edit', $team->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->where('brand.voice', null)->etc());
});
