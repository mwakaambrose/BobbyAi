<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PostDraft>
 */
class PostDraftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->sentence(12);

        return [
            'post_id' => Post::factory(),
            'variant' => fake()->randomElement(['A', 'B', 'C']),
            'body' => $body,
            'rationale' => 'Hook-led opening.',
            'character_count' => mb_strlen($body),
            'model' => 'gemma3:12b',
        ];
    }
}
