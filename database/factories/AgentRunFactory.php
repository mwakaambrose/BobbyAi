<?php

namespace Database\Factories;

use App\Enums\RunMode;
use App\Enums\RunStatus;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AgentRun>
 */
class AgentRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'social_account_id' => SocialAccount::factory()->connected(),
            'status' => RunStatus::Queued,
            'mode' => RunMode::Autonomous,
            'contract' => [
                'allowed_host' => 'www.linkedin.com',
                'approved_body' => 'We built Growth Savings because members asked for it.',
                'allowed_roles' => ['button', 'textbox', 'link'],
                'step_budget' => 18,
            ],
        ];
    }
}
