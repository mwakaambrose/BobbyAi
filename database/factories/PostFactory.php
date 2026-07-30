<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\SocialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'social_account_id' => SocialAccount::factory(),
            'author_id' => User::factory(),
            'brief' => 'Announce Growth Savings in a warm, jargon-free tone.',
            'status' => PostStatus::Brief,
        ];
    }

    /**
     * Indicate that the post has approved copy ready to publish.
     */
    public function approved(string $body = 'We built Growth Savings because members asked for it.'): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Approved,
            'approved_body' => $body,
            'approved_at' => now(),
        ]);
    }
}
