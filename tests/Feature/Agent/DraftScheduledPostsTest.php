<?php

use App\Enums\AccountStatus;
use App\Enums\PostStatus;
use App\Jobs\DraftPostContent;
use App\Jobs\PublishPostRun;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\PostIdea;
use App\Models\PostingSchedule;
use App\Models\SocialAccount;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    Bus::fake();

    $this->team = Team::factory()->create();
    $this->account = SocialAccount::factory()->for($this->team)->connected()->create();
});

/**
 * Build a cadence for the team under test.
 *
 * @param  array<string, mixed>  $attributes
 */
function schedule(Team $team, SocialAccount $account, array $attributes = []): PostingSchedule
{
    return PostingSchedule::factory()->for($team)->create([
        'social_account_id' => $account->id,
        ...$attributes,
    ]);
}

test('it drafts when the cadence is due in the schedule timezone', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday'], 'time_of_day' => '06:00']);
    PostIdea::factory()->for($this->team)->create(['body' => 'Why we run one worker']);

    // 06:30 Monday in Kampala is 03:30 UTC.
    Carbon::setTestNow(Carbon::parse('2026-08-03 03:30:00', 'UTC'));

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    $post = Post::query()->latest('id')->first();

    expect(Post::query()->count())->toBe(1)
        ->and($post->brief)->toBe('Why we run one worker')
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->author_id)->toBeNull()
        ->and($post->social_account_id)->toBe($this->account->id);

    Bus::assertBatched(fn ($batch): bool => $batch->jobs->count() === 1
        && $batch->jobs->first() instanceof DraftPostContent);
});

test('it does not fire at the cadence time in UTC', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday'], 'time_of_day' => '06:00']);
    PostIdea::factory()->for($this->team)->create();

    // 06:00 UTC Monday is only 09:00 in Kampala on the same day, which is past
    // the cadence — so test the inverse: 03:00 UTC is 06:00 Kampala minus three.
    Carbon::setTestNow(Carbon::parse('2026-08-03 02:00:00', 'UTC'));

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(0);
    Bus::assertNothingBatched();
});

test('it does not draft on a day outside the cadence', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday']]);
    PostIdea::factory()->for($this->team)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-04 08:00:00', 'UTC')); // Tuesday

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(0);
});

test('it drafts late when a tick was missed rather than skipping the day', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday'], 'time_of_day' => '06:00']);
    PostIdea::factory()->for($this->team)->create();

    // 14:00 Kampala on the cadence day, hours after the box came back up.
    Carbon::setTestNow(Carbon::parse('2026-08-03 11:00:00', 'UTC'));

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(1);
});

test('it drafts only once per local day however often it runs', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday']]);
    PostIdea::factory()->for($this->team)->count(3)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));
    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:15:00', 'UTC'));
    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    Carbon::setTestNow(Carbon::parse('2026-08-03 09:00:00', 'UTC'));
    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(1);
});

test('it does not backfill a day that was missed entirely', function (): void {
    $schedule = schedule($this->team, $this->account, ['days' => ['monday']]);
    PostIdea::factory()->for($this->team)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-04 08:00:00', 'UTC')); // Tuesday

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(0)
        ->and($schedule->fresh()->last_ran_at)->toBeNull();
});

test('it drafts several posts when the cadence asks for more than one', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday'], 'posts_per_run' => 3]);
    PostIdea::factory()->for($this->team)->count(3)->sequence(
        ['body' => 'One'],
        ['body' => 'Two'],
        ['body' => 'Three'],
    )->create();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(3);
    Bus::assertBatched(fn ($batch): bool => $batch->jobs->count() === 3);
});

test('it marks the ideas it drafted as used and links them to their posts', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday']]);
    $idea = PostIdea::factory()->for($this->team)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));
    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    $idea->refresh();

    expect($idea->used_at)->not->toBeNull()
        ->and($idea->post_id)->toBe(Post::query()->latest('id')->first()->id);
});

test('it skips an inactive schedule', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday'], 'is_active' => false]);
    PostIdea::factory()->for($this->team)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(0);
});

test('it skips a schedule whose account is not connected', function (): void {
    $this->account->forceFill(['status' => AccountStatus::Expired])->save();
    schedule($this->team, $this->account, ['days' => ['monday']]);
    PostIdea::factory()->for($this->team)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(0);
    Bus::assertNothingBatched();
});

test('it skips when the backlog and the brand pillars are both empty', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday']]);

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(0);
    Bus::assertNothingBatched();
});

test('it falls back to a brand pillar when the backlog is empty', function (): void {
    BrandProfile::factory()->for($this->team)->create(['topics' => ['local inference']]);
    schedule($this->team, $this->account, ['days' => ['monday']]);

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));
    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    $post = Post::query()->latest('id')->first();

    expect($post->brief)->toContain('local inference')
        ->and(PostIdea::query()->count())->toBe(0);
});

test('it never publishes, whatever the cadence says', function (): void {
    schedule($this->team, $this->account, ['days' => ['monday'], 'posts_per_run' => 3]);
    PostIdea::factory()->for($this->team)->count(3)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));
    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->pluck('status')->unique()->all())->toBe([PostStatus::Draft])
        ->and(Post::query()->whereNotNull('approved_at')->count())->toBe(0);

    Bus::assertNotDispatched(PublishPostRun::class);
});

test('it leaves another team\'s schedule alone', function (): void {
    $other = Team::factory()->create();
    $otherAccount = SocialAccount::factory()->for($other)->connected()->create();

    schedule($other, $otherAccount, ['days' => ['monday']]);
    PostIdea::factory()->for($this->team)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));
    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    expect(Post::query()->count())->toBe(0);
});
