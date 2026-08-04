<?php

use App\Ai\Agents\CopywriterAgent;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;

test('asking for other angles produces three variants', function () {
    CopywriterAgent::fake([[
        'drafts' => [
            ['body' => 'Hook led.', 'rationale' => 'Opens on tension.'],
            ['body' => 'Story led.', 'rationale' => 'Opens on a person.'],
            ['body' => 'Question led.', 'rationale' => 'Opens on a question.'],
        ],
    ]]);

    [$user, $team] = teamUser();

    $post = Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Announce the Series A',
    ]);

    $this->actingAs($user)
        ->post(route('posts.alternatives.store', [$team->slug, $post]))
        ->assertRedirect();

    expect($post->drafts()->orderBy('variant')->pluck('variant')->all())->toBe(['A', 'B', 'C'])
        ->and($post->messages()->count())->toBe(1);
});

test('another team cannot ask for alternatives', function () {
    [$user, $team] = teamUser();

    $post = Post::factory()->for(Team::factory()->create())
        ->create(['author_id' => User::factory()->create()->id]);

    $this->actingAs($user)
        ->post(route('posts.alternatives.store', [$team->slug, $post]))
        ->assertNotFound();
});
