<?php

use App\Ai\Agents\ComposerAgent;
use App\Models\Post;
use App\Models\PostIdea;
use App\Models\PostingSchedule;
use App\Models\SocialAccount;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Agent\DraftsReadyForReview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * These tests run the batch for real on the sync queue, because the point is to
 * prove the batch's completion callback sends the mail.
 */
beforeEach(function (): void {
    Notification::fake();
    ComposerAgent::fake(['We shipped something worth talking about.']);

    [$this->user, $this->team] = teamUser();
    $this->account = SocialAccount::factory()->for($this->team)->connected()->create();

    Carbon::setTestNow(Carbon::parse('2026-08-03 04:00:00', 'UTC'));
});

test('it emails the addresses on the schedule once the drafts land', function (): void {
    PostingSchedule::factory()->for($this->team)->create([
        'social_account_id' => $this->account->id,
        'days' => ['monday'],
        'notify_emails' => ['ambrose@zzzimba.com', 'help@zzzimba.com'],
    ]);
    PostIdea::factory()->for($this->team)->create(['body' => 'Why we run one worker']);

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    Notification::assertSentOnDemand(
        DraftsReadyForReview::class,
        function (DraftsReadyForReview $notification, array $channels, object $notifiable): bool {
            return $notifiable->routes['mail'] === ['ambrose@zzzimba.com', 'help@zzzimba.com']
                && $notification->posts->count() === 1;
        }
    );
});

test('it falls back to the team owner when no addresses are set', function (): void {
    PostingSchedule::factory()->for($this->team)->create([
        'social_account_id' => $this->account->id,
        'days' => ['monday'],
        'notify_emails' => [],
    ]);
    PostIdea::factory()->for($this->team)->create();

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    Notification::assertSentOnDemand(
        DraftsReadyForReview::class,
        fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === [$this->user->email]
    );
});

test('it does not email when the cadence drafted nothing', function (): void {
    PostingSchedule::factory()->for($this->team)->create([
        'social_account_id' => $this->account->id,
        'days' => ['monday'],
    ]);

    $this->artisan('agent:draft-scheduled')->assertSuccessful();

    Notification::assertNothingSent();
});

test('the mail names the brief and says nothing publishes without approval', function (): void {
    $team = Team::factory()->create();
    $post = Post::factory()->for($team)->create(['brief' => 'Why we run one worker']);

    $mail = (new DraftsReadyForReview($team, Post::query()->whereKey($post->id)->get()))
        ->toMail(User::factory()->create());

    expect($mail->subject)->toBe('Bobby drafted "Why we run one worker"')
        ->and($mail->introLines)->toContain('Bobby wrote a draft and it is waiting for your review.')
        ->and($mail->outroLines)->toContain('Nothing is published until you approve it.')
        ->and($mail->actionUrl)->toBe(route('posts.show', [$team->slug, $post]));
});

test('the mail counts the drafts when there is more than one', function (): void {
    $team = Team::factory()->create();
    Post::factory()->for($team)->count(2)->create();

    $mail = (new DraftsReadyForReview($team, Post::query()->where('team_id', $team->id)->get()))
        ->toMail(User::factory()->create());

    expect($mail->subject)->toBe('Bobby drafted 2 posts for you');
});
