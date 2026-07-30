<?php

namespace Database\Factories;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PostMessage>
 */
class PostMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => fake()->sentence(),
        ];
    }
}
