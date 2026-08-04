<?php

namespace Database\Factories;

use App\Enums\IdeaKind;
use App\Enums\KindSource;
use App\Models\Post;
use App\Models\PostIdea;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostIdea>
 */
class PostIdeaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'body' => 'Why we run exactly one queue worker.',
            'kind' => IdeaKind::Lesson,
            'kind_source' => KindSource::Agent,
            'position' => 0,
        ];
    }

    /**
     * Indicate the kind the idea was classified as.
     */
    public function kind(IdeaKind $kind): static
    {
        return $this->state(fn (): array => ['kind' => $kind]);
    }

    /**
     * Indicate that the idea has not been classified yet.
     */
    public function unclassified(): static
    {
        return $this->state(fn (): array => ['kind' => null, 'kind_source' => null]);
    }

    /**
     * Indicate that a person set the kind by hand.
     */
    public function correctedByUser(IdeaKind $kind): static
    {
        return $this->state(fn (): array => ['kind' => $kind, 'kind_source' => KindSource::User]);
    }

    /**
     * Indicate that the idea has already been drafted.
     */
    public function used(?Post $post = null): static
    {
        return $this->state(fn (): array => [
            'used_at' => now(),
            'post_id' => $post instanceof Post ? $post->id : Post::factory(),
        ]);
    }

    /**
     * Indicate the idea's manual position in the backlog.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (): array => ['position' => $position]);
    }
}
