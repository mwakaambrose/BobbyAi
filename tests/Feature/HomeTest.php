<?php

use App\Enums\AccountStatus;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Enums\TeamRole;
use App\Models\AgentRun;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $team = Team::factory()->create();

    $this->get(route('dashboard', $team->slug))->assertRedirect(route('login'));
});

test('the landing route renders the home screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', $user->currentTeam->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('home'));
});

test('home reports the pipeline in its status strip', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Post::factory()->count(2)->for($team)->create([
        'author_id' => $user->id,
        'status' => PostStatus::Approved,
    ]);
    $running = Post::factory()->for($team)->create(['author_id' => $user->id]);
    AgentRun::factory()->for($running, 'post')->create(['status' => RunStatus::Running]);
    SocialAccount::factory()->for($team)->create(['status' => AccountStatus::Connected]);
    SocialAccount::factory()->for($team)->create(['status' => AccountStatus::Expired]);

    $this->actingAs($user)
        ->get(route('dashboard', $team->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('status.awaitingPublish', 2)
            ->where('status.activeRuns', 1)
            ->where('status.accountsConnected', 1)
            ->where('status.accountsTotal', 2)
            ->where('status.teamSlug', $team->slug),
        );
});

test('home status ignores other teams', function () {
    $user = User::factory()->create();
    $other = Team::factory()->create();

    Post::factory()->count(3)->for($other)->create([
        'author_id' => $user->id,
        'status' => PostStatus::Approved,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', $user->currentTeam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('status.awaitingPublish', 0));
});

test('home passes suggestions through', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', $user->currentTeam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('suggestions', 2)
            ->where('suggestions.0.label', 'Announce a milestone'),
        );
});

test('home includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invited = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invited)
        ->get(route('dashboard', $invited->currentTeam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.code', $invitation->code)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.team.slug', $team->slug),
        );
});

test('home excludes accepted and expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invited = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);
    $expired = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invited)
        ->get(route('dashboard', $invited->currentTeam->slug))
        ->assertInertia(fn (Assert $page) => $page->has('pendingInvitations', 0));

    $this->assertDatabaseHas('team_invitations', ['id' => $expired->id]);
});

test("home does not include another user's invitations", function () {
    $owner = User::factory()->create();
    $invited = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invited)
        ->get(route('dashboard', $invited->currentTeam->slug))
        ->assertInertia(fn (Assert $page) => $page->has('pendingInvitations', 0));
});
