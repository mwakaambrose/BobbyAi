<?php

use App\Models\Post;
use App\Models\Team;
use App\Models\User;

/**
 * The suite runs on the null broadcaster, which authorises every channel
 * unconditionally, so these tests pin a real one.
 *
 * Broadcast::channel() registers against whichever driver is default at the
 * moment it runs, so the channels file has to be loaded after the swap or the
 * new broadcaster has no channels at all and denies everything - which looks
 * exactly like a passing authorisation test.
 */
beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');

    require base_path('routes/channels.php');
});

test('a team member may listen to their post channel', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-posts.{$post->id}",
            'socket_id' => '1234.5678',
        ])
        ->assertOk();
});

test('a user from another team may not listen to a post channel', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for(Team::factory()->create())->create(['author_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-posts.{$post->id}",
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

test('a guest may not listen to a post channel', function () {
    $post = Post::factory()->for(Team::factory()->create())
        ->create(['author_id' => User::factory()->create()->id]);

    $this->postJson('/broadcasting/auth', [
        'channel_name' => "private-posts.{$post->id}",
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
