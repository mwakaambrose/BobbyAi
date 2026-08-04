<?php

use App\Enums\IdeaKind;
use App\Enums\KindSource;
use App\Jobs\ClassifyPostIdea;
use App\Models\PostIdea;
use App\Models\SocialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Queue::fake();

    [$this->user, $this->team] = teamUser();
    $this->account = SocialAccount::factory()->for($this->team)->connected()->create();
});

test('it shows the cadence and the backlog', function (): void {
    PostIdea::factory()->for($this->team)->create(['body' => 'Why we run one worker']);

    $this->actingAs($this->user)
        ->get(route('autopilot.index', $this->team->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('autopilot/index')
            ->has('ideas', 1)
            ->where('ideas.0.body', 'Why we run one worker')
            ->where('schedule.timezone', 'Africa/Kampala')
            ->where('schedule.isActive', false));
});

test('it defaults the notify address to the team owner before a cadence is saved', function (): void {
    $this->actingAs($this->user)
        ->get(route('autopilot.index', $this->team->slug))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('schedule.notifyEmails', [$this->user->email]));
});

test('it saves the cadence', function (): void {
    $this->actingAs($this->user)
        ->put(route('autopilot.schedule.update', $this->team->slug), [
            'is_active' => true,
            'days' => ['tuesday', 'thursday'],
            'time_of_day' => '07:30',
            'timezone' => 'Africa/Kampala',
            'posts_per_run' => 2,
            'social_account_id' => $this->account->id,
            'notify_emails' => ['ambrose@zzzimba.com'],
        ])
        ->assertRedirect();

    $schedule = $this->team->postingSchedule()->sole();

    expect($schedule->is_active)->toBeTrue()
        ->and($schedule->days)->toBe(['tuesday', 'thursday'])
        ->and($schedule->time_of_day)->toBe('07:30')
        ->and($schedule->posts_per_run)->toBe(2)
        ->and($schedule->notify_emails)->toBe(['ambrose@zzzimba.com']);
});

test('it will not switch autopilot on without days or an account', function (): void {
    $this->actingAs($this->user)
        ->put(route('autopilot.schedule.update', $this->team->slug), [
            'is_active' => true,
            'days' => [],
            'time_of_day' => '06:00',
            'timezone' => 'Africa/Kampala',
            'posts_per_run' => 1,
            'social_account_id' => null,
        ])
        ->assertSessionHasErrors(['days', 'social_account_id']);
});

test('it rejects an account belonging to another team', function (): void {
    $stranger = SocialAccount::factory()->for(Team::factory())->connected()->create();

    $this->actingAs($this->user)
        ->put(route('autopilot.schedule.update', $this->team->slug), [
            'is_active' => true,
            'days' => ['monday'],
            'time_of_day' => '06:00',
            'timezone' => 'Africa/Kampala',
            'posts_per_run' => 1,
            'social_account_id' => $stranger->id,
        ])
        ->assertSessionHasErrors('social_account_id');
});

test('it adds an idea and queues its classification', function (): void {
    $this->actingAs($this->user)
        ->post(route('autopilot.ideas.store', $this->team->slug), [
            'body' => 'The SQLite WAL decision',
        ])
        ->assertRedirect();

    $idea = $this->team->postIdeas()->sole();

    expect($idea->body)->toBe('The SQLite WAL decision')
        ->and($idea->kind)->toBeNull();

    Queue::assertPushed(ClassifyPostIdea::class, fn (ClassifyPostIdea $job): bool => $job->idea->is($idea));
});

test('it appends each new idea to the end of the backlog', function (): void {
    PostIdea::factory()->for($this->team)->atPosition(4)->create();

    $this->actingAs($this->user)
        ->post(route('autopilot.ideas.store', $this->team->slug), ['body' => 'A later idea']);

    expect($this->team->postIdeas()->latest('id')->first()->position)->toBe(5);
});

test('correcting the kind locks it against the classifier', function (): void {
    $idea = PostIdea::factory()->for($this->team)->kind(IdeaKind::Lesson)->create();

    $this->actingAs($this->user)
        ->put(route('autopilot.ideas.update', [$this->team->slug, $idea]), ['kind' => 'story'])
        ->assertRedirect();

    $idea->refresh();

    expect($idea->kind)->toBe(IdeaKind::Story)
        ->and($idea->kind_source)->toBe(KindSource::User)
        ->and($idea->acceptsClassification())->toBeFalse();
});

test('it reorders the backlog', function (): void {
    $first = PostIdea::factory()->for($this->team)->atPosition(0)->create();
    $second = PostIdea::factory()->for($this->team)->atPosition(1)->create();

    $this->actingAs($this->user)
        ->put(route('autopilot.ideas.reorder', $this->team->slug), ['ids' => [$second->id, $first->id]])
        ->assertRedirect();

    expect($second->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1);
});

test('it deletes an idea', function (): void {
    $idea = PostIdea::factory()->for($this->team)->create();

    $this->actingAs($this->user)
        ->delete(route('autopilot.ideas.destroy', [$this->team->slug, $idea]))
        ->assertRedirect();

    expect(PostIdea::query()->count())->toBe(0);
});

test('it will not touch another team\'s idea', function (): void {
    $stranger = PostIdea::factory()->create(['body' => 'Not yours']);

    $this->actingAs($this->user)
        ->put(route('autopilot.ideas.update', [$this->team->slug, $stranger]), ['kind' => 'story'])
        ->assertNotFound();

    $this->actingAs($this->user)
        ->delete(route('autopilot.ideas.destroy', [$this->team->slug, $stranger]))
        ->assertNotFound();

    expect($stranger->fresh())->not->toBeNull();
});

test('it will not reorder another team\'s idea', function (): void {
    $stranger = PostIdea::factory()->atPosition(7)->create();

    $this->actingAs($this->user)
        ->put(route('autopilot.ideas.reorder', $this->team->slug), ['ids' => [$stranger->id]])
        ->assertRedirect();

    expect($stranger->fresh()->position)->toBe(7);
});

test('it keeps drafted ideas out of the backlog but shows them as history', function (): void {
    PostIdea::factory()->for($this->team)->used()->create(['body' => 'Already written']);
    PostIdea::factory()->for($this->team)->create(['body' => 'Still waiting']);

    $this->actingAs($this->user)
        ->get(route('autopilot.index', $this->team->slug))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('ideas', 1)
            ->where('ideas.0.body', 'Still waiting')
            ->has('drafted', 1)
            ->where('drafted.0.body', 'Already written'));
});

test('it requires membership of the team', function (): void {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('autopilot.index', $this->team->slug))
        ->assertForbidden();
});

test('it requires signing in', function (): void {
    $this->get(route('autopilot.index', $this->team->slug))->assertRedirect(route('login'));
});
