<?php

namespace Database\Seeders;

use App\Enums\IdeaKind;
use App\Enums\KindSource;
use App\Enums\TeamRole;
use App\Models\BrandProfile;
use App\Models\PostIdea;
use App\Models\PostingSchedule;
use App\Models\SocialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a working local account: a user, their team, a brand voice, an
     * autopilot cadence and a backlog to draw from.
     *
     * The LinkedIn account is left disconnected on purpose. Connecting one
     * drives a real browser session, which is yours to do from the Accounts
     * screen rather than something a seeder should fake.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Mwaka Ambrose',
            'email' => 'ambrosemwaka@gmail.com',
        ]);

        $team = Team::factory()->create(['name' => 'Zzzimba', 'slug' => 'zzzimba']);

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        SocialAccount::factory()->for($team)->create();

        BrandProfile::factory()->for($team)->create([
            'voice' => 'Plain, concrete and unhurried. Explain the decision, not the outcome.',
            'audience' => 'Engineers and founders building small, self-hosted systems.',
            'topics' => ['local inference', 'running lean infrastructure', 'agent design'],
            'banned_terms' => ['leverage', 'synergy', 'game-changer'],
            'examples' => [],
        ]);

        PostingSchedule::factory()->for($team)->create([
            'social_account_id' => null,
            'days' => ['monday', 'wednesday', 'friday'],
            'time_of_day' => '06:00',
            'timezone' => 'Africa/Kampala',
            'notify_emails' => ['ambrose@zzzimba.com', 'ambrosemwaka@gmail.com', 'help@zzzimba.com'],
            'is_active' => false,
        ]);

        $backlog = [
            ['Why we run exactly one queue worker.', IdeaKind::Lesson],
            ['The SQLite WAL decision, and what it cost.', IdeaKind::Lesson],
            ['Bobby now drafts on its own overnight.', IdeaKind::Announcement],
            ['What a 12B model taught us about an 11GB box.', IdeaKind::Story],
            ['Local inference is underrated for small teams.', IdeaKind::Opinion],
        ];

        foreach ($backlog as $position => [$body, $kind]) {
            PostIdea::factory()->for($team)->create([
                'body' => $body,
                'kind' => $kind,
                'kind_source' => KindSource::Agent,
                'position' => $position,
            ]);
        }
    }
}
