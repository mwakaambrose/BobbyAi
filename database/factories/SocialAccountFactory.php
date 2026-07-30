<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\SocialPlatform;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'platform' => SocialPlatform::LinkedIn,
            'handle' => fake()->unique()->userName(),
            'status' => AccountStatus::Disconnected,
        ];
    }

    /**
     * Indicate that the account has a verified browser session.
     */
    public function connected(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountStatus::Connected,
            'last_verified_at' => now(),
        ]);
    }
}
