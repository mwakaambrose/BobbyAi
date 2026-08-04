<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return (int) $user->id === (int) $id;
});

/**
 * A post's draft stream is readable by anyone on the team that owns it. This is
 * the one place the drafting feature can leak another team's content.
 */
Broadcast::channel('posts.{post}', function (User $user, Post $post): bool {
    return $user->teams()->whereKey($post->team_id)->exists();
});
