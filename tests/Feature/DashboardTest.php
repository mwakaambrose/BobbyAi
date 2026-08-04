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
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.team.name', 'Laravel Team')
        ->where('pendingInvitations.0.team.slug', $team->slug)
        ->missing('pendingInvitations.0.teamName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard summarises the current team pipeline', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Post::factory()->count(2)->for($team)->create(['author_id' => $user->id, 'status' => PostStatus::Approved]);
    Post::factory()->for($team)->create(['author_id' => $user->id, 'status' => PostStatus::Published]);
    SocialAccount::factory()->for($team)->create(['status' => AccountStatus::Connected]);
    SocialAccount::factory()->for($team)->create(['status' => AccountStatus::Expired]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('overview.awaitingApproval', 2)
        ->where('overview.published', 1)
        ->where('overview.activeRuns', 0)
        ->where('overview.accounts.connected', 1)
        ->where('overview.accounts.total', 2)
        ->where('overview.teamSlug', $team->slug),
    );
});

test('dashboard overview ignores other teams posts', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    Post::factory()->count(3)->for($otherTeam)->create(['author_id' => $user->id, 'status' => PostStatus::Approved]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('overview.awaitingApproval', 0),
    );
});

test('dashboard lists the five most recently touched posts, newest first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    foreach (range(1, 6) as $index) {
        Post::factory()->for($team)->create([
            'author_id' => $user->id,
            'brief' => "Brief {$index}",
            'updated_at' => now()->subMinutes(10 - $index),
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('recentPosts', 5)
        ->where('recentPosts.0.brief', 'Brief 6')
        ->where('recentPosts.4.brief', 'Brief 2'),
    );
});

test('dashboard recent posts exclude other teams', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    Post::factory()->for($otherTeam)->create(['author_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('recentPosts', 0),
    );
});

test('dashboard lists recent runs with their post brief and step count', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $otherTeam = Team::factory()->create();

    $post = Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Announce the savings product',
    ]);
    $otherPost = Post::factory()->for($otherTeam)->create(['author_id' => $user->id]);

    AgentRun::factory()->for($post, 'post')->create([
        'status' => RunStatus::Published,
        'step_count' => 7,
    ]);
    AgentRun::factory()->for($otherPost, 'post')->create(['status' => RunStatus::Running]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('recentRuns', 1)
        ->where('recentRuns.0.brief', 'Announce the savings product')
        ->where('recentRuns.0.stepCount', 7)
        ->where('recentRuns.0.status', RunStatus::Published->value),
    );
});

test('dashboard counts only queued and running runs for the current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $otherTeam = Team::factory()->create();

    $queuedPost = Post::factory()->for($team)->create(['author_id' => $user->id]);
    $runningPost = Post::factory()->for($team)->create(['author_id' => $user->id]);
    $publishedPost = Post::factory()->for($team)->create(['author_id' => $user->id]);
    $failedPost = Post::factory()->for($team)->create(['author_id' => $user->id]);
    $otherTeamPost = Post::factory()->for($otherTeam)->create(['author_id' => $user->id]);

    AgentRun::factory()->for($queuedPost, 'post')->create(['status' => RunStatus::Queued]);
    AgentRun::factory()->for($runningPost, 'post')->create(['status' => RunStatus::Running]);
    AgentRun::factory()->for($publishedPost, 'post')->create(['status' => RunStatus::Published]);
    AgentRun::factory()->for($failedPost, 'post')->create(['status' => RunStatus::Failed]);
    AgentRun::factory()->for($otherTeamPost, 'post')->create(['status' => RunStatus::Running]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('overview.activeRuns', 2),
    );
});
