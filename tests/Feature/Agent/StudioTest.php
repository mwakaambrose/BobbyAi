<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\SocialAccount;
use Inertia\Testing\AssertableInertia as Assert;

test('the board groups posts into every column', function (): void {
    [$user, $team] = teamUser();
    Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Draft]);
    Post::factory()->count(2)->create(['team_id' => $team->id, 'status' => PostStatus::Published]);

    $this->actingAs($user)
        ->get(route('studio', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('studio/index')
            ->has('columns', 6)
            ->where('columns.1.status', 'draft')
            ->has('columns.1.posts', 1)
            ->has('columns.4.posts', 2)
            ->etc());
});

test('the board never shows another team posts', function (): void {
    [$user, $team] = teamUser();
    Post::factory()->create();

    $this->actingAs($user)
        ->get(route('studio', $team->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->where('totalPosts', 0)->etc());
});

test('the board exposes the connected accounts for the composer', function (): void {
    [$user, $team] = teamUser();
    SocialAccount::factory()->connected()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('studio', $team->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->has('accounts', 1)->etc());
});

test('the board counts the drafts the autopilot is waiting on', function (): void {
    [$user, $team] = teamUser();

    Post::factory()->for($team)->create(['status' => PostStatus::Draft, 'author_id' => null]);
    Post::factory()->for($team)->create(['status' => PostStatus::Draft, 'author_id' => $user->id]);
    Post::factory()->for($team)->create(['status' => PostStatus::Published, 'author_id' => null]);

    $this->actingAs($user)
        ->get(route('studio', $team->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->where('awaitingReview', 1)->etc());
});

test('the board marks the posts Bobby wrote unprompted', function (): void {
    [$user, $team] = teamUser();

    Post::factory()->for($team)->create(['status' => PostStatus::Draft, 'author_id' => null]);
    Post::factory()->for($team)->create(['status' => PostStatus::Draft, 'author_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('studio', $team->slug))
        ->assertInertia(fn (Assert $page): Assert => $page
            // The board lists newest first, so the post written by hand leads.
            ->where('columns.1.posts.0.byBobby', false)
            ->where('columns.1.posts.1.byBobby', true)
            ->etc());
});
