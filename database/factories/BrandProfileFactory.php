<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BrandProfile>
 */
class BrandProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'voice' => 'Warm, direct, jargon-free.',
            'audience' => 'Members and prospective members of a savings cooperative.',
            'topics' => ['savings', 'financial literacy'],
            'banned_terms' => ['synergy', 'leverage'],
            'examples' => ['We built Growth Savings because members asked for it.'],
        ];
    }
}
