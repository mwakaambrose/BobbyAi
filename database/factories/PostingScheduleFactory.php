<?php

namespace Database\Factories;

use App\Models\PostingSchedule;
use App\Models\SocialAccount;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostingSchedule>
 */
class PostingScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'social_account_id' => SocialAccount::factory()->connected(),
            'days' => ['monday', 'wednesday', 'friday'],
            'time_of_day' => '06:00',
            'timezone' => 'Africa/Kampala',
            'posts_per_run' => 1,
            'notify_emails' => ['owner@example.com'],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the schedule is switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Indicate which days the schedule drafts on.
     *
     * @param  array<int, string>  $days
     */
    public function onDays(array $days): static
    {
        return $this->state(fn (): array => ['days' => $days]);
    }

    /**
     * Indicate how many posts each run drafts.
     */
    public function postsPerRun(int $count): static
    {
        return $this->state(fn (): array => ['posts_per_run' => $count]);
    }
}
