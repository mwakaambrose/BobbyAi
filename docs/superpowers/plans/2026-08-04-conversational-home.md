# Conversational Home Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the metrics dashboard with a conversational Home that briefs the agent in plain language, and turn the post workspace into a thread beside a live draft canvas that streams as the model writes.

**Architecture:** The landing route keeps its URI and name but renders a new `home.tsx` — greeting, composer, three data-derived suggestions, and a one-line status strip. Submitting a brief creates the `Post` and dispatches a queued job instead of calling the model inline, so the redirect is instant. The job streams a single plain-text draft from a new `ComposerAgent`, re-emitting each delta as an application-owned `DraftChunk` event over Reverb; the client renders deltas optimistically and reloads server state on completion, so the websocket is pure enhancement.

**Tech Stack:** PHP 8.4, Laravel 13, Inertia v3, React 19, Tailwind v4, Pest 5, laravel/ai v0.10, Laravel Reverb, Laravel Echo, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-08-04-conversational-home-design.md`

## Global Constraints

- Follow existing conventions; check sibling files before creating anything new. Do not create new base folders.
- Curly braces on all control structures. Constructor property promotion. Explicit return types and parameter type hints on every method. PHPDoc array shapes over inline comments.
- Run `vendor/bin/pint --dirty --format agent` before every commit that touches PHP.
- Every task ends with passing tests. Run the narrowest useful command: `php artisan test --compact --filter=<name>`.
- Route name `home` is already taken by `Route::inertia('/', 'welcome')->name('home')` in `routes/web.php:16`. The landing route keeps the name **`dashboard`** and the URI **`{current_team}/dashboard`**. Only the controller class and the Inertia page component change.
- Frontend route calls go through Wayfinder imports (`@/routes`, `@/actions/...`). Never hardcode URLs. Run `php artisan wayfinder:generate` after changing routes.
- New Inertia pages live in `resources/js/pages/`. Do not add a `resources/js/features/` folder or similar.
- Do not change dependencies beyond the three named in Task 4 (`laravel/reverb`, `laravel-echo`, `pusher-js`), which the spec approves.

---

### Task 1: SuggestionBuilder

Derives up to three Home suggestions from stored data. No model call. Pure enough to test directly.

**Files:**
- Create: `app/Agent/SuggestionBuilder.php`
- Test: `tests/Feature/Agent/SuggestionBuilderTest.php`

**Interfaces:**
- Consumes: `App\Models\Team`, `App\Models\Post`, `App\Models\BrandProfile`, `App\Enums\PostStatus`
- Produces: `SuggestionBuilder::for(Team $team): array` returning a list of
  `array{label: string, hint: string, prompt: string|null, href: string|null}`.
  Exactly one of `prompt` / `href` is non-null per suggestion. Task 2 serialises this
  straight into an Inertia prop; Task 3 renders it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Agent/SuggestionBuilderTest.php`:

```php
<?php

use App\Agent\SuggestionBuilder;
use App\Enums\PostStatus;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\User;

test('an unfinished recent draft is offered first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $post = Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Announce the Series A',
        'status' => PostStatus::Draft,
        'updated_at' => now()->subDay(),
    ]);

    $suggestions = app(SuggestionBuilder::class)->for($team);

    expect($suggestions[0]['label'])->toBe('Finish: Announce the Series A')
        ->and($suggestions[0]['hint'])->toBe('Draft in progress')
        ->and($suggestions[0]['href'])->toContain("/posts/{$post->id}")
        ->and($suggestions[0]['prompt'])->toBeNull();
});

test('a draft untouched for more than a week is not offered', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'status' => PostStatus::Draft,
        'updated_at' => now()->subDays(8),
    ]);

    $labels = collect(app(SuggestionBuilder::class)->for($team))->pluck('label');

    expect($labels->filter(fn (string $label): bool => str_starts_with($label, 'Finish:')))->toBeEmpty();
});

test('an approved post is offered for publishing', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Hiring two engineers',
        'status' => PostStatus::Approved,
    ]);

    $suggestions = app(SuggestionBuilder::class)->for($team);

    expect($suggestions[0]['label'])->toBe('Approve and publish')
        ->and($suggestions[0]['hint'])->toBe('Hiring two engineers');
});

test('an unused brand pillar becomes a prompt suggestion', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    BrandProfile::factory()->for($team)->create(['topics' => ['developer tooling']]);

    $suggestions = app(SuggestionBuilder::class)->for($team);

    expect($suggestions[0]['label'])->toBe('Write about developer tooling')
        ->and($suggestions[0]['prompt'])->toBe('Write a post about developer tooling')
        ->and($suggestions[0]['href'])->toBeNull();
});

test('a pillar covered in the last fortnight is not suggested', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    BrandProfile::factory()->for($team)->create(['topics' => ['developer tooling']]);

    Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Why developer tooling matters',
        'status' => PostStatus::Published,
        'published_at' => now()->subDays(3),
    ]);

    $labels = array_column(app(SuggestionBuilder::class)->for($team), 'label');

    expect($labels)->not->toContain('Write about developer tooling');
});

test('the usual posting weekday becomes a cadence suggestion', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    foreach ([7, 14, 21] as $daysAgo) {
        Post::factory()->for($team)->create([
            'author_id' => $user->id,
            'status' => PostStatus::Published,
            'published_at' => now()->startOfWeek()->addDay()->subDays($daysAgo),
        ]);
    }

    $labels = array_column(app(SuggestionBuilder::class)->for($team), 'label');

    expect($labels)->toContain('Your weekly Tuesday update');
});

test('a brand new team gets generic starters', function () {
    $user = User::factory()->create();

    $suggestions = app(SuggestionBuilder::class)->for($user->currentTeam);

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions[0]['label'])->toBe('Announce a milestone')
        ->and($suggestions[0]['prompt'])->not->toBeNull();
});

test('no more than three suggestions are returned', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    BrandProfile::factory()->for($team)->create([
        'topics' => ['one', 'two', 'three', 'four'],
    ]);

    Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'status' => PostStatus::Draft,
        'updated_at' => now(),
    ]);

    expect(app(SuggestionBuilder::class)->for($team))->toHaveCount(3);
});

test("another team's work is never suggested", function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $other = User::factory()->create()->currentTeam;

    Post::factory()->for($other)->create([
        'author_id' => $user->id,
        'brief' => 'Not yours',
        'status' => PostStatus::Draft,
        'updated_at' => now(),
    ]);

    $labels = array_column(app(SuggestionBuilder::class)->for($team), 'label');

    expect($labels)->not->toContain('Finish: Not yours');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=SuggestionBuilderTest`
Expected: FAIL — `Class "App\Agent\SuggestionBuilder" does not exist`.

- [ ] **Step 3: Write the implementation**

Create `app/Agent/SuggestionBuilder.php`:

```php
<?php

namespace App\Agent;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Builds the Home screen's suggestions from what the team already has, so the
 * entry point knows the account without costing a model call.
 *
 * @phpstan-type Suggestion array{label: string, hint: string, prompt: string|null, href: string|null}
 */
final class SuggestionBuilder
{
    private const LIMIT = 3;

    private const RESUME_WINDOW_DAYS = 7;

    private const PILLAR_WINDOW_DAYS = 14;

    /**
     * Get up to three suggestions for the team, most actionable first.
     *
     * @return array<int, Suggestion>
     */
    public function for(Team $team): array
    {
        $suggestions = (new Collection([
            ...$this->resume($team),
            ...$this->awaitingPublish($team),
            ...$this->pillars($team),
            ...$this->cadence($team),
        ]))->take(self::LIMIT)->values();

        return $suggestions->isEmpty()
            ? $this->starters()
            : $suggestions->all();
    }

    /**
     * Offer the most recently touched draft that is still unfinished.
     *
     * @return array<int, Suggestion>
     */
    private function resume(Team $team): array
    {
        $post = $team->posts()
            ->where('status', PostStatus::Draft)
            ->where('updated_at', '>=', now()->subDays(self::RESUME_WINDOW_DAYS))
            ->latest('updated_at')
            ->first();

        return $post === null ? [] : [[
            'label' => 'Finish: '.$post->brief,
            'hint' => 'Draft in progress',
            'prompt' => null,
            'href' => route('posts.show', [$team->slug, $post], absolute: false),
        ]];
    }

    /**
     * Offer a post that is approved but not yet live.
     *
     * @return array<int, Suggestion>
     */
    private function awaitingPublish(Team $team): array
    {
        $post = $team->posts()
            ->where('status', PostStatus::Approved)
            ->latest('updated_at')
            ->first();

        return $post === null ? [] : [[
            'label' => 'Approve and publish',
            'hint' => $post->brief,
            'prompt' => null,
            'href' => route('posts.show', [$team->slug, $post], absolute: false),
        ]];
    }

    /**
     * Offer brand pillars the team has not written about recently.
     *
     * @return array<int, Suggestion>
     */
    private function pillars(Team $team): array
    {
        $topics = $team->brandProfile?->topics ?? [];

        if ($topics === []) {
            return [];
        }

        $recent = $team->posts()
            ->where('published_at', '>=', now()->subDays(self::PILLAR_WINDOW_DAYS))
            ->pluck('brief')
            ->implode(' ');

        return (new Collection($topics))
            ->reject(fn (string $topic): bool => str_contains(mb_strtolower($recent), mb_strtolower($topic)))
            ->map(fn (string $topic): array => [
                'label' => 'Write about '.$topic,
                'hint' => 'From your brand profile',
                'prompt' => 'Write a post about '.$topic,
                'href' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * Offer the weekday the team usually publishes on.
     *
     * @return array<int, Suggestion>
     */
    private function cadence(Team $team): array
    {
        $weekday = $team->posts()
            ->whereNotNull('published_at')
            ->pluck('published_at')
            ->map(fn ($date): string => $date->format('l'))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $weekday === null ? [] : [[
            'label' => "Your weekly {$weekday} update",
            'hint' => "You usually post on {$weekday}s",
            'prompt' => 'Write my weekly update',
            'href' => null,
        ]];
    }

    /**
     * Fall back to generic openers for a team with no history and no brand profile.
     *
     * @return array<int, Suggestion>
     */
    private function starters(): array
    {
        return [
            [
                'label' => 'Announce a milestone',
                'hint' => 'Something your team just shipped',
                'prompt' => 'Write a post announcing a milestone we just hit',
                'href' => null,
            ],
            [
                'label' => 'Share a lesson',
                'hint' => 'Something you learned this week',
                'prompt' => 'Write a post about a lesson we learned this week',
                'href' => null,
            ],
        ];
    }
}
```

Note: `Post` is imported for the PHPDoc-visible query results; if PHPStan flags it as
unused, drop the import.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=SuggestionBuilderTest`
Expected: PASS, 9 tests.

If the cadence test fails on the weekday name, check that `Post::casts()` casts
`published_at` to a date. If it does not, add `'published_at' => 'datetime'` to
`app/Models/Post.php`'s `casts()` method and re-run.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Agent/SuggestionBuilder.php tests/Feature/Agent/SuggestionBuilderTest.php
git commit -m "feat: derive home suggestions from the team's own work"
```

---

### Task 2: HomeController

Renames the landing controller and reshapes its props around the new screen. The route
name and URI do not move.

**Files:**
- Create: `app/Http/Controllers/HomeController.php`
- Delete: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php:22`
- Create: `tests/Feature/HomeTest.php`
- Delete: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `SuggestionBuilder::for()` from Task 1
- Produces: an Inertia page named `home` with props `pendingInvitations`,
  `suggestions`, and `status` where `status` is
  `array{activeRuns: int, awaitingPublish: int, accountsConnected: int, accountsTotal: int, teamSlug: string}`.
  Task 3 consumes exactly these names.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/HomeTest.php`. Carry over the invitation coverage from
`DashboardTest.php` unchanged in behaviour — only the component name and prop shape move.

```php
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
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the landing route renders the home screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
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
        ->get(route('dashboard'))
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
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('status.awaitingPublish', 0));
});

test('home passes suggestions through', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
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
        ->get(route('dashboard'))
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
        ->get(route('dashboard'))
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
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('pendingInvitations', 0));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=HomeTest`
Expected: FAIL — the component asserts as `dashboard`, not `home`.

- [ ] **Step 3: Write the implementation**

Create `app/Http/Controllers/HomeController.php`. The invitation query moves across
verbatim from `DashboardController`; only the pipeline summary changes shape.

```php
<?php

namespace App\Http\Controllers;

use App\Agent\SuggestionBuilder;
use App\Enums\AccountStatus;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, SuggestionBuilder $suggestions): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('home', [
            'pendingInvitations' => $this->pendingInvitations($request),
            'suggestions' => $suggestions->for($team),
            'status' => $this->status($team),
        ]);
    }

    /**
     * The one-line pipeline summary shown beneath the composer.
     *
     * @return array{activeRuns: int, awaitingPublish: int, accountsConnected: int, accountsTotal: int, teamSlug: string}
     */
    private function status(Team $team): array
    {
        $accounts = $team->socialAccounts()->get(['status']);

        return [
            'activeRuns' => AgentRun::query()
                ->whereIn('post_id', $team->posts()->select('id'))
                ->whereIn('status', [RunStatus::Queued, RunStatus::Running])
                ->count(),
            'awaitingPublish' => $team->posts()->where('status', PostStatus::Approved)->count(),
            'accountsConnected' => $accounts
                ->where('status', AccountStatus::Connected)
                ->count(),
            'accountsTotal' => $accounts->count(),
            'teamSlug' => $team->slug,
        ];
    }

    /**
     * Invitations addressed to the authenticated user that are still open.
     *
     * @return \Illuminate\Support\Collection<int, array{code: string, inviterName: string, team: array{name: string, slug: string}}>
     */
    private function pendingInvitations(Request $request): \Illuminate\Support\Collection
    {
        return TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [strtolower($request->user()->email)])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation): array => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);
    }
}
```

Confirm the accounts relation name against `app/Models/Team.php` before running — the
existing `DashboardController::overview()` shows how accounts are counted today; reuse
whatever relation name it uses rather than assuming `socialAccounts()`.

Update `routes/web.php` — replace the import and the route:

```php
use App\Http\Controllers\HomeController;
```

```php
Route::get('dashboard', HomeController::class)->name('dashboard');
```

Delete `app/Http/Controllers/DashboardController.php` and `tests/Feature/DashboardTest.php`.

- [ ] **Step 4: Run the tests to verify they pass**

The `home` page component does not exist yet, so assert on props only — Inertia does not
resolve the component server-side in tests.

Run: `php artisan test --compact --filter=HomeTest`
Expected: PASS, 8 tests.

Run: `php artisan test --compact`
Expected: PASS. If anything else references `DashboardController`, fix it now.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/HomeController.php routes/web.php tests/Feature/HomeTest.php
git add -u app/Http/Controllers/DashboardController.php tests/Feature/DashboardTest.php
git commit -m "feat: land on a home screen instead of a metrics dashboard"
```

---

### Task 3: The Home screen

The visual entry point. Greeting, composer, suggestions, status strip.

**Files:**
- Create: `resources/js/pages/home.tsx`
- Delete: `resources/js/pages/dashboard.tsx`
- Modify: `resources/css/app.css` (add two tokens inside the existing `@theme` block)
- Modify: `resources/js/types/index.ts` (add `Suggestion`, `HomeStatus`)

**Interfaces:**
- Consumes: props `pendingInvitations`, `suggestions`, `status` from Task 2
- Produces: nothing later tasks depend on

- [ ] **Step 1: Add the design tokens**

In `resources/css/app.css`, inside the existing `@theme` block, after the `--text-title`
group, add:

```css
    /* The front door speaks at a different volume to the console pages. One
       display size and one softer radius carry it; everything else is shared. */
    --text-display: 1.9rem;
    --text-display--line-height: 1.12;
    --text-display--letter-spacing: -0.026em;
    --text-display--font-weight: 600;
```

And after the existing `--radius-sm` line:

```css
    --radius-xl: calc(var(--radius) + 6px);
```

- [ ] **Step 2: Add the types**

In `resources/js/types/index.ts`, append:

```ts
export type Suggestion = {
    label: string;
    hint: string;
    prompt: string | null;
    href: string | null;
};

export type HomeStatus = {
    activeRuns: number;
    awaitingPublish: number;
    accountsConnected: number;
    accountsTotal: number;
    teamSlug: string;
};
```

- [ ] **Step 3: Write the page**

Create `resources/js/pages/home.tsx`:

```tsx
import { Form, Head, Link } from '@inertiajs/react';
import { ArrowUp } from 'lucide-react';
import { useState } from 'react';
import PostController from '@/actions/App/Http/Controllers/Agent/PostController';
import PageContainer from '@/components/page-container';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { index as runsIndex } from '@/routes/runs';
import { studio } from '@/routes';
import type { DashboardInvitation, HomeStatus, Suggestion } from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    suggestions: Suggestion[];
    status: HomeStatus;
};

/**
 * The greeting changes with the hour so returning to the app feels like being
 * met rather than logging in.
 */
function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    return hour < 18 ? 'Good afternoon' : 'Good evening';
}

/**
 * A suggestion either navigates to work already in flight, or fills the
 * composer with a brief. It never does both.
 */
function SuggestionCard({
    suggestion,
    onPrompt,
}: {
    suggestion: Suggestion;
    onPrompt: (prompt: string) => void;
}) {
    const inner = (
        <>
            <span className="line-clamp-2 text-sm font-medium">
                {suggestion.label}
            </span>
            <span className="mt-1 line-clamp-1 text-label text-muted-foreground">
                {suggestion.hint}
            </span>
        </>
    );

    const className =
        'flex flex-col rounded-xl border border-border bg-card p-3 text-left shadow-2xs transition-[border-color,box-shadow,transform] outline-none hover:-translate-y-0.5 hover:border-foreground/15 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring';

    if (suggestion.href) {
        return (
            <Link href={suggestion.href} className={className}>
                {inner}
            </Link>
        );
    }

    return (
        <button
            type="button"
            className={className}
            onClick={() => onPrompt(suggestion.prompt ?? '')}
        >
            {inner}
        </button>
    );
}

export default function Home({
    pendingInvitations = [],
    suggestions,
    status,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const [brief, setBrief] = useState('');

    const { teamSlug } = status;

    return (
        <>
            <Head title="Home" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <PageContainer className="flex flex-col justify-center py-16">
                <div className="mx-auto w-full max-w-2xl">
                    <h1 className="text-display">
                        {greeting()}.
                        <br />
                        <span className="text-muted-foreground">
                            What should we post today?
                        </span>
                    </h1>
                    <p className="mt-3 text-meta text-muted-foreground">
                        Bobby writes in your voice and waits for your approval
                        before anything goes live.
                    </p>

                    <Form
                        {...PostController.store.form({
                            current_team: teamSlug,
                        })}
                        className="mt-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex items-end gap-2 rounded-xl border border-input bg-card p-3 shadow-md focus-within:border-foreground/20">
                                    <textarea
                                        name="brief"
                                        rows={2}
                                        value={brief}
                                        onChange={(event) =>
                                            setBrief(event.target.value)
                                        }
                                        placeholder="Ask Bobby to write something…"
                                        className="w-full resize-none bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                                    />
                                    <Button
                                        size="icon"
                                        className="rounded-full"
                                        disabled={
                                            processing || brief.trim() === ''
                                        }
                                        aria-label="Send brief"
                                    >
                                        <ArrowUp />
                                    </Button>
                                </div>
                                {errors.brief && (
                                    <p className="mt-2 text-label text-destructive">
                                        {errors.brief}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>

                    <div className="mt-3 grid gap-2 sm:grid-cols-3">
                        {suggestions.map((suggestion) => (
                            <SuggestionCard
                                key={suggestion.label}
                                suggestion={suggestion}
                                onPrompt={setBrief}
                            />
                        ))}
                    </div>

                    <p className="mt-8 flex flex-wrap items-center gap-x-2 gap-y-1 text-label text-muted-foreground">
                        <Link
                            href={runsIndex(teamSlug)}
                            className="hover:text-foreground"
                        >
                            {status.activeRuns} in progress
                        </Link>
                        <span aria-hidden>·</span>
                        <Link
                            href={studio(teamSlug)}
                            className="hover:text-foreground"
                        >
                            {status.awaitingPublish} awaiting publish
                        </Link>
                        <span aria-hidden>·</span>
                        <Link
                            href={accountsIndex(teamSlug)}
                            className="hover:text-foreground"
                        >
                            {status.accountsConnected} of{' '}
                            {status.accountsTotal} accounts connected
                        </Link>
                    </p>
                </div>
            </PageContainer>
        </>
    );
}

Home.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Home',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
```

Delete `resources/js/pages/dashboard.tsx`.

- [ ] **Step 4: Verify it builds and renders**

Run: `npm run build`
Expected: builds with no TypeScript errors. If `DashboardInvitation` is not exported from
`@/types`, check `resources/js/types/index.ts` for its real name and use that.

Run: `php artisan test --compact --filter=HomeTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/home.tsx resources/js/types/index.ts resources/css/app.css
git add -u resources/js/pages/dashboard.tsx
git commit -m "feat: greet, compose and suggest on the home screen"
```

---

### Task 4: Broadcasting foundation

Installs Reverb and Echo, and authorises the per-post channel. Nothing streams yet — this
task exists so the channel's authorisation gets its own test and its own review.

**Files:**
- Create: `routes/channels.php`
- Modify: `bootstrap/app.php` (register the channels file)
- Modify: `.env.example`
- Create: `tests/Feature/Agent/PostChannelTest.php`
- Modify: `composer.json`, `package.json` (via the installer)

**Interfaces:**
- Produces: a private channel named `posts.{postId}`, authorised for members of the
  post's team. Tasks 5 and 8 broadcast on and subscribe to exactly this name.

- [ ] **Step 1: Install broadcasting**

```bash
php artisan install:broadcasting --reverb --no-interaction
```

This adds `laravel/reverb` to composer, `laravel-echo` and `pusher-js` to package.json,
creates `routes/channels.php`, registers it in `bootstrap/app.php`, scaffolds
`resources/js/echo.ts`, and writes `REVERB_*` keys into `.env`.

Then set the driver:

```bash
php artisan config:show broadcasting.default
```

If it still reports `log`, set `BROADCAST_CONNECTION=reverb` in `.env` and add the same
line to `.env.example`.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Agent/PostChannelTest.php`:

```php
<?php

use App\Models\Post;
use App\Models\Team;
use App\Models\User;

test('a team member may listen to their post channel', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-posts.{$post->id}"])
        ->assertOk();
});

test('a user from another team may not listen to a post channel', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for(Team::factory()->create())->create(['author_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-posts.{$post->id}"])
        ->assertForbidden();
});

test('a guest may not listen to a post channel', function () {
    $post = Post::factory()->for(Team::factory()->create())
        ->create(['author_id' => User::factory()->create()->id]);

    $this->postJson('/broadcasting/auth', ['channel_name' => "private-posts.{$post->id}"])
        ->assertUnauthorized();
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PostChannelTest`
Expected: FAIL — the channel is not defined, so authorisation is refused for everyone
including the legitimate member.

- [ ] **Step 4: Define the channel**

Replace the contents of `routes/channels.php` with:

```php
<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('posts.{post}', function (User $user, Post $post): bool {
    return $user->teams()->whereKey($post->team_id)->exists();
});
```

Check the relation name on `app/Models/User.php` — if the user's teams relation is not
called `teams()`, use the real one.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PostChannelTest`
Expected: PASS, 3 tests.

Run: `php artisan test --compact`
Expected: PASS. Broadcasting is now on the `reverb` driver but nothing broadcasts yet, so
no other test should change.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/channels.php bootstrap/app.php composer.json composer.lock package.json package-lock.json .env.example resources/js/echo.ts tests/Feature/Agent/PostChannelTest.php
git commit -m "feat: authorise a private channel per post"
```

---

### Task 5: Streaming composer

A plain-text agent that writes one draft, a service that streams and persists it, and the
job that broadcasts the deltas.

**Files:**
- Create: `app/Ai/Agents/ComposerAgent.php`
- Create: `app/Agent/Composer.php`
- Create: `app/Events/DraftChunk.php`, `app/Events/DraftCompleted.php`, `app/Events/DraftFailed.php`
- Create: `app/Jobs/DraftPostContent.php`
- Modify: `app/Enums/MessageKind.php` (add `Error`)
- Test: `tests/Feature/Agent/DraftPostContentTest.php`

**Interfaces:**
- Consumes: `Copywriter::bannedTermsIn(string $body, array $terms): array` (existing, public)
- Produces:
  - `Composer::streamFor(Post $post, string $brief, ?BrandProfile $brand, ?string $revisionOf, Closure $onDelta): PostDraft` — `$onDelta` is `Closure(string $delta): void`
  - `DraftPostContent::__construct(Post $post, string $brief, ?string $revisionOf = null)` — Task 6 dispatches this
  - Broadcast names `draft.chunk`, `draft.completed`, `draft.failed` on `private-posts.{id}` — Task 8 listens for exactly these

- [ ] **Step 1: Confirm the SDK's stream event shape**

Before writing the loop, find out what `stream()` yields:

```bash
ls vendor/laravel/ai/src/Streaming/Events/
```

Expected: a directory of event classes including something like `TextDelta.php`,
`ToolCall.php`, `ToolResult.php`. Open the text-delta class and note its real class name
and the property holding the text.

**The code below assumes `Laravel\Ai\Streaming\Events\TextDelta` with a `->text` string
property.** If the listing shows different names, substitute them consistently everywhere
they appear in this task. Do not proceed on a guess — this is the one place the plan
depends on an API that was read from documentation rather than from the installed code.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Agent/DraftPostContentTest.php`:

```php
<?php

use App\Ai\Agents\ComposerAgent;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Events\DraftChunk;
use App\Events\DraftCompleted;
use App\Events\DraftFailed;
use App\Jobs\DraftPostContent;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('the job persists a single draft and announces completion', function () {
    Event::fake([DraftChunk::class, DraftCompleted::class, DraftFailed::class]);
    ComposerAgent::fake(['We shipped something worth talking about.']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create([
        'author_id' => $user->id,
        'brief' => 'Announce the Series A',
    ]);

    (new DraftPostContent($post, $post->brief))->handle(app(App\Agent\Composer::class));

    $draft = $post->drafts()->sole();

    expect($draft->variant)->toBe('A')
        ->and($draft->body)->toBe('We shipped something worth talking about.')
        ->and($draft->character_count)->toBe(41);

    Event::assertDispatched(DraftCompleted::class, fn (DraftCompleted $event): bool => $event->postId === $post->id
        && $event->draftId === $draft->id);
});

test('the job records the agent turn in the thread', function () {
    Event::fake();
    ComposerAgent::fake(['A draft.']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    (new DraftPostContent($post, 'Announce the Series A'))->handle(app(App\Agent\Composer::class));

    $message = $post->messages()->where('role', MessageRole::Agent)->sole();

    expect($message->kind)->toBe(MessageKind::Drafts);
});

test('a draft using a banned term is regenerated before it is persisted', function () {
    Event::fake();
    ComposerAgent::fake(['Our synergy is unmatched.', 'Our team is unmatched.']);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    BrandProfile::factory()->for($team)->create(['banned_terms' => ['synergy']]);

    $post = Post::factory()->for($team)->create(['author_id' => $user->id]);

    (new DraftPostContent($post, 'Announce the Series A'))->handle(app(App\Agent\Composer::class));

    expect($post->drafts()->sole()->body)->toBe('Our team is unmatched.');
});

test('a failure leaves an error in the thread rather than a silent stall', function () {
    Event::fake([DraftFailed::class]);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    $composer = Mockery::mock(App\Agent\Composer::class);
    $composer->shouldReceive('streamFor')->andThrow(new RuntimeException('provider down'));

    $job = new DraftPostContent($post, 'Announce the Series A');

    expect(fn () => $job->handle($composer))->toThrow(RuntimeException::class);

    $job->failed(new RuntimeException('provider down'));

    expect($post->messages()->where('kind', MessageKind::Error)->exists())->toBeTrue();

    Event::assertDispatched(DraftFailed::class);
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact --filter=DraftPostContentTest`
Expected: FAIL — `Class "App\Ai\Agents\ComposerAgent" does not exist`.

- [ ] **Step 4: Add the error message kind**

In `app/Enums/MessageKind.php`, add to the enum:

```php
    case Error = 'error';
```

- [ ] **Step 5: Write the agent**

Create `app/Ai/Agents/ComposerAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes a single post in plain text so it can be streamed into the canvas as
 * it is composed. The three-variant CopywriterAgent still handles alternatives.
 */
class ComposerAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You write LinkedIn posts for a brand. Given a brief and a brand profile,
        return exactly one post.

        Constraints:
        - Plain text only. No markdown, no bullet characters, no emoji spam.
        - Under 1,200 characters; shorter is usually stronger.
        - At most two hashtags, at the end, only if they earn their place.
        - Never use a banned term you are given.
        - Do not invent facts, statistics, product features or testimonials.
        - Return the post copy and nothing else. No preamble, no explanation.
        TEXT;
    }
}
```

- [ ] **Step 6: Write the composer service**

Create `app/Agent/Composer.php`. It mirrors `Copywriter`'s shape deliberately — same
prompt construction, same regenerate-once-on-banned-term rule, delegating the rule itself
to `Copywriter` so there is one definition of it.

```php
<?php

namespace App\Agent;

use App\Ai\Agents\ComposerAgent;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\PostDraft;
use Closure;
use Laravel\Ai\Streaming\Events\TextDelta;

/**
 * Streams a single brand conditioned draft, handing each delta to the caller so
 * it can be broadcast while the model is still writing.
 */
final class Composer
{
    public function __construct(
        private readonly ComposerAgent $agent,
        private readonly Copywriter $copywriter,
    ) {}

    /**
     * Stream a draft for the post and persist it as variant A.
     *
     * The retry after a banned term is generated without streaming: the client
     * replaces whatever it streamed with server state on completion, so the
     * discarded first attempt never survives on screen.
     *
     * @param  Closure(string): void  $onDelta
     */
    public function streamFor(
        Post $post,
        string $brief,
        ?BrandProfile $brand,
        ?string $revisionOf,
        Closure $onDelta,
    ): PostDraft {
        $banned = $brand->banned_terms ?? [];

        $body = $this->stream($this->prompt($brief, $brand, $revisionOf, []), $onDelta);

        if ($this->copywriter->bannedTermsIn($body, $banned) !== []) {
            $body = $this->generate($this->prompt($brief, $brand, $revisionOf, $banned));
        }

        $post->drafts()->delete();

        return $post->drafts()->create([
            'variant' => 'A',
            'body' => $body,
            'character_count' => mb_strlen($body),
            'model' => config('agent.model'),
        ]);
    }

    /**
     * Stream the agent's response, emitting each delta as it arrives.
     *
     * @param  Closure(string): void  $onDelta
     */
    private function stream(string $prompt, Closure $onDelta): string
    {
        $body = '';

        foreach ($this->agent->stream($prompt) as $event) {
            if (! $event instanceof TextDelta) {
                continue;
            }

            $body .= $event->text;

            $onDelta($event->text);
        }

        return trim($body);
    }

    /**
     * Generate without streaming, used for the banned term retry.
     */
    private function generate(string $prompt): string
    {
        return trim((string) $this->agent->prompt($prompt)->text);
    }

    /**
     * Build the brand conditioned prompt.
     *
     * @param  string[]  $avoid
     */
    private function prompt(string $brief, ?BrandProfile $brand, ?string $revisionOf, array $avoid): string
    {
        return implode("\n\n", array_filter([
            'BRIEF: '.$brief,
            $brand?->voice ? 'VOICE: '.$brand->voice : null,
            $brand?->audience ? 'AUDIENCE: '.$brand->audience : null,
            $brand?->topics ? 'TOPICS: '.implode(', ', $brand->topics) : null,
            $brand?->banned_terms ? 'NEVER USE: '.implode(', ', $brand->banned_terms) : null,
            $brand?->examples ? "EXAMPLES OF OUR VOICE:\n".implode("\n", $brand->examples) : null,
            $revisionOf ? "REVISE THIS DRAFT:\n".$revisionOf : null,
            $avoid === [] ? null : 'Your previous attempt used these forbidden terms: '.implode(', ', $avoid).'. Do not use them.',
        ]));
    }
}
```

- [ ] **Step 7: Write the events**

Create `app/Events/DraftChunk.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A slice of the draft as the model writes it. Broadcast immediately rather
 * than queued, since queueing each delta would defeat the point.
 */
class DraftChunk implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $postId,
        public readonly string $delta,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('posts.'.$this->postId)];
    }

    public function broadcastAs(): string
    {
        return 'draft.chunk';
    }
}
```

Create `app/Events/DraftCompleted.php` — identical shape, with
`public readonly int $draftId` in place of `$delta` and `broadcastAs()` returning
`'draft.completed'`.

Create `app/Events/DraftFailed.php` — identical shape, with
`public readonly string $reason` in place of `$delta` and `broadcastAs()` returning
`'draft.failed'`.

- [ ] **Step 8: Write the job**

Create `app/Jobs/DraftPostContent.php`:

```php
<?php

namespace App\Jobs;

use App\Agent\Composer;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Events\DraftChunk;
use App\Events\DraftCompleted;
use App\Events\DraftFailed;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writes a draft for the post, broadcasting it as it is composed.
 */
class DraftPostContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly Post $post,
        public readonly string $brief,
        public readonly ?string $revisionOf = null,
    ) {}

    public function handle(Composer $composer): void
    {
        $draft = $composer->streamFor(
            $this->post,
            $this->brief,
            $this->post->team->brandProfile,
            $this->revisionOf,
            fn (string $delta) => DraftChunk::dispatch($this->post->id, $delta),
        );

        $this->post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Drafts,
            'body' => __('Drafted'),
            'payload' => ['draft_ids' => [$draft->id]],
        ]);

        DraftCompleted::dispatch($this->post->id, $draft->id);
    }

    /**
     * Leave the failure visible in the thread rather than a canvas that appears
     * to still be thinking.
     */
    public function failed(Throwable $exception): void
    {
        $this->post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Error,
            'body' => __('Bobby could not finish this draft.'),
            'payload' => ['reason' => $exception->getMessage()],
        ]);

        DraftFailed::dispatch($this->post->id, $exception->getMessage());
    }
}
```

- [ ] **Step 9: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=DraftPostContentTest`
Expected: PASS, 4 tests.

If the first test's `character_count` assertion fails, the fake returned different text —
read the actual value from the failure and correct the expectation rather than the code.

Run: `php artisan test --compact --filter=CopywriterTest`
Expected: PASS, unchanged. If this broke, the composer split leaked into the
three-variant path.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Agents/ComposerAgent.php app/Agent/Composer.php app/Events app/Jobs/DraftPostContent.php app/Enums/MessageKind.php tests/Feature/Agent/DraftPostContentTest.php
git commit -m "feat: stream a draft as the model writes it"
```

---

### Task 6: Controllers dispatch instead of blocking

The request stops waiting on the model.

**Files:**
- Modify: `app/Http/Controllers/Agent/PostController.php:22-49`
- Modify: `app/Http/Controllers/Agent/PostMessageController.php:17-45`
- Modify: `tests/Feature/Agent/PostPersistenceTest.php`, `tests/Feature/Agent/PostLifecycleTest.php`

**Interfaces:**
- Consumes: `DraftPostContent::__construct(Post $post, string $brief, ?string $revisionOf = null)` from Task 5

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Agent/PostPersistenceTest.php`:

```php
test('creating a post queues the drafting work instead of blocking the request', function () {
    Queue::fake();

    [$user, $team] = teamUser();

    $this->actingAs($user)
        ->post(route('posts.store', $team->slug), ['brief' => 'Announce the Series A'])
        ->assertRedirect();

    $post = Post::query()->sole();

    expect($post->drafts()->count())->toBe(0)
        ->and($post->messages()->where('role', MessageRole::User)->count())->toBe(1);

    Queue::assertPushed(DraftPostContent::class, fn (DraftPostContent $job): bool => $job->post->is($post)
        && $job->brief === 'Announce the Series A');
});

test('a revision request queues a redraft against the current draft', function () {
    Queue::fake();

    [$user, $team] = teamUser();

    $post = Post::factory()->for($team)->create(['author_id' => $user->id]);
    $post->drafts()->create([
        'variant' => 'A',
        'body' => 'The current draft.',
        'character_count' => 18,
    ]);

    $this->actingAs($user)
        ->post(route('posts.messages.store', [$team->slug, $post]), ['body' => 'Make it punchier'])
        ->assertRedirect();

    Queue::assertPushed(DraftPostContent::class, fn (DraftPostContent $job): bool => $job->brief === 'Make it punchier'
        && $job->revisionOf === 'The current draft.');
});
```

Add the imports the file needs: `use App\Jobs\DraftPostContent;`,
`use App\Enums\MessageRole;`, `use Illuminate\Support\Facades\Queue;`.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PostPersistenceTest`
Expected: FAIL — no job is pushed; the controller still drafts inline.

- [ ] **Step 3: Rewrite `PostController::store`**

Replace the body of `store` in `app/Http/Controllers/Agent/PostController.php`. Drop the
`Copywriter $copywriter` parameter and its import; add `use App\Jobs\DraftPostContent;`.

```php
    /**
     * Create a post from a brief and queue its first draft.
     */
    public function store(StorePostRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $post = Post::create([
            'team_id' => $team->id,
            'social_account_id' => $request->validated('social_account_id'),
            'author_id' => $request->user()->id,
            'brief' => $request->validated('brief'),
            'status' => PostStatus::Draft,
        ]);

        $post->messages()->create([
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => $post->brief,
        ]);

        DraftPostContent::dispatch($post, $post->brief);

        return to_route('posts.show', [$team->slug, $post]);
    }
```

- [ ] **Step 4: Rewrite `PostMessageController::store`**

Same treatment — drop `Copywriter`, add `use App\Jobs\DraftPostContent;`:

```php
    /**
     * Record a revision request and queue a redraft.
     */
    public function store(StorePostMessageRequest $request, string $currentTeam, Post $post): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($post->team_id === $team?->id, 404);

        $post->messages()->create([
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => $request->validated('body'),
        ]);

        DraftPostContent::dispatch(
            $post,
            $request->validated('body'),
            revisionOf: $post->drafts()->first()?->body,
        );

        return back();
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=PostPersistenceTest`
Expected: PASS.

Run: `php artisan test --compact`
Expected: PASS. `PostLifecycleTest` and `PostWorkspaceTest` almost certainly assert that
drafts exist immediately after `posts.store`. Update those assertions to run the job
first — add `Queue::fake()` and dispatch the job by hand, or drop `Queue::fake()` and let
the sync driver run it inline. Prefer the latter where the test is about the lifecycle
rather than the queue; use `ComposerAgent::fake([...])` so no real model call is made.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Agent/PostController.php app/Http/Controllers/Agent/PostMessageController.php tests/Feature/Agent
git commit -m "feat: hand drafting to the queue so the thread opens immediately"
```

---

### Task 7: Alternatives on demand

The three-variant copywriter, now behind an explicit action.

**Files:**
- Create: `app/Http/Controllers/Agent/PostAlternativeController.php`
- Modify: `routes/web.php` (one route inside the existing `{current_team}` group)
- Test: `tests/Feature/Agent/PostAlternativesTest.php`

**Interfaces:**
- Consumes: `Copywriter::draftsFor()` (existing, unchanged)
- Produces: route `posts.alternatives.store`, `POST {current_team}/posts/{post}/alternatives`. Task 8 posts to it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Agent/PostAlternativesTest.php`:

```php
<?php

use App\Ai\Agents\CopywriterAgent;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;

test('asking for other angles produces three variants', function () {
    CopywriterAgent::fake([[
        'drafts' => [
            ['body' => 'Hook led.', 'rationale' => 'Opens on tension.'],
            ['body' => 'Story led.', 'rationale' => 'Opens on a person.'],
            ['body' => 'Question led.', 'rationale' => 'Opens on a question.'],
        ],
    ]]);

    [$user, $team] = teamUser();

    $post = Post::factory()->for($team)->create([
        'author_id' => $user->id,
        'brief' => 'Announce the Series A',
    ]);

    $this->actingAs($user)
        ->post(route('posts.alternatives.store', [$team->slug, $post]))
        ->assertRedirect();

    expect($post->drafts()->pluck('variant')->all())->toBe(['A', 'B', 'C']);
});

test('another team cannot ask for alternatives', function () {
    [$user, $team] = teamUser();

    $post = Post::factory()->for(Team::factory()->create())
        ->create(['author_id' => User::factory()->create()->id]);

    $this->actingAs($user)
        ->post(route('posts.alternatives.store', [$team->slug, $post]))
        ->assertNotFound();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PostAlternativesTest`
Expected: FAIL — route `posts.alternatives.store` is not defined.

- [ ] **Step 3: Write the controller and route**

Create `app/Http/Controllers/Agent/PostAlternativeController.php`:

```php
<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Copywriter;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostAlternativeController extends Controller
{
    /**
     * Generate the three angled alternatives for a post on request.
     */
    public function store(Request $request, string $currentTeam, Post $post, Copywriter $copywriter): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($post->team_id === $team?->id, 404);

        $drafts = $copywriter->draftsFor($post, $post->brief, $team->brandProfile);

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Drafts,
            'body' => __('Three other angles'),
            'payload' => ['draft_ids' => $drafts->pluck('id')->all()],
        ]);

        return back();
    }
}
```

In `routes/web.php`, after the `posts.messages.store` line:

```php
        Route::post('posts/{post}/alternatives', [PostAlternativeController::class, 'store'])->name('posts.alternatives.store');
```

Add the import: `use App\Http\Controllers\Agent\PostAlternativeController;`

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PostAlternativesTest`
Expected: PASS, 2 tests.

Run: `php artisan wayfinder:generate`
Expected: generates the new action module for the frontend.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Agent/PostAlternativeController.php routes/web.php tests/Feature/Agent/PostAlternativesTest.php resources/js/actions resources/js/routes
git commit -m "feat: offer three other angles on request"
```

---

### Task 8: The thread and its live canvas

Splits the post workspace into focused files and wires the stream to the canvas.

**Files:**
- Create: `resources/js/components/agent/thread-column.tsx`
- Create: `resources/js/components/agent/draft-canvas.tsx`
- Create: `resources/js/hooks/use-post-stream.ts`
- Modify: `resources/js/pages/posts/show.tsx` (reduce to composition)
- Modify: `resources/js/types/agent.ts` (add `'error'` to `ThreadMessage['kind']`)

**Interfaces:**
- Consumes: broadcast names `draft.chunk`, `draft.completed`, `draft.failed` on
  `private-posts.{id}` from Task 5; route `posts.alternatives.store` from Task 7
- Produces: `usePostStream(postId: number, isDrafting: boolean): { streamed: string; failed: string | null }`

- [ ] **Step 1: Write the hook**

Create `resources/js/hooks/use-post-stream.ts`:

```ts
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import echo from '@/echo';

type ChunkPayload = { delta: string };
type FailedPayload = { reason: string };

/**
 * Renders the draft as it is written. The stream is enhancement only — the job
 * persists everything, so a page that never connects still gets its draft from
 * the completion reload, and a page that misses the completion event falls back
 * to polling.
 */
export function usePostStream(postId: number, isDrafting: boolean) {
    const [streamed, setStreamed] = useState('');
    const [failed, setFailed] = useState<string | null>(null);

    useEffect(() => {
        if (!isDrafting) {
            return;
        }

        const channel = echo().private(`posts.${postId}`);

        channel.listen('.draft.chunk', (event: ChunkPayload) => {
            setStreamed((current) => current + event.delta);
        });

        channel.listen('.draft.completed', () => {
            setStreamed('');
            router.reload({ only: ['drafts', 'messages', 'post'] });
        });

        channel.listen('.draft.failed', (event: FailedPayload) => {
            setStreamed('');
            setFailed(event.reason);
            router.reload({ only: ['messages'] });
        });

        // If the socket never connects, the draft still lands in the database.
        const fallback = window.setInterval(() => {
            router.reload({ only: ['drafts', 'messages', 'post'] });
        }, 8000);

        return () => {
            window.clearInterval(fallback);
            echo().leave(`posts.${postId}`);
        };
    }, [postId, isDrafting]);

    return { streamed, failed };
}
```

Check how `install:broadcasting` scaffolded the Echo export in `resources/js/echo.ts` —
if it exports a configured instance rather than an `echo()` function, import and use that
instead, consistently.

- [ ] **Step 2: Write the canvas**

Create `resources/js/components/agent/draft-canvas.tsx`:

```tsx
import { Form, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Sparkles } from 'lucide-react';
import { useState } from 'react';
import PostAlternativeController from '@/actions/App/Http/Controllers/Agent/PostAlternativeController';
import { Button } from '@/components/ui/button';
import type { DraftData } from '@/types/agent';

type Props = {
    teamSlug: string;
    postId: number;
    drafts: DraftData[];
    streamed: string;
    failed: string | null;
    isDrafting: boolean;
    children: React.ReactNode;
};

/**
 * The post as it will be published, pinned beside the conversation so it is
 * never more than a glance away.
 */
export function DraftCanvas({
    teamSlug,
    postId,
    drafts,
    streamed,
    failed,
    isDrafting,
    children,
}: Props) {
    const [index, setIndex] = useState(0);

    const draft = drafts[index] ?? null;
    const body = streamed !== '' ? streamed : (draft?.body ?? '');
    const count = streamed !== '' ? streamed.length : (draft?.character_count ?? 0);

    return (
        <aside className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">
            <header className="flex items-center justify-between gap-2">
                <span className="text-label text-muted-foreground">
                    {drafts.length > 1
                        ? `Angle ${index + 1} of ${drafts.length}`
                        : 'Draft'}
                </span>
                {drafts.length > 1 && (
                    <span className="flex items-center gap-1">
                        <Button
                            size="icon"
                            variant="ghost"
                            disabled={index === 0}
                            onClick={() => setIndex(index - 1)}
                            aria-label="Previous angle"
                        >
                            <ChevronLeft />
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            disabled={index === drafts.length - 1}
                            onClick={() => setIndex(index + 1)}
                            aria-label="Next angle"
                        >
                            <ChevronRight />
                        </Button>
                    </span>
                )}
            </header>

            {failed ? (
                <p className="text-meta text-destructive">{failed}</p>
            ) : (
                <p className="text-sm whitespace-pre-wrap">
                    {body}
                    {isDrafting && streamed !== '' && (
                        <span className="ml-0.5 inline-block h-4 w-px animate-pulse bg-agent align-middle" />
                    )}
                </p>
            )}

            <p className="text-label text-muted-foreground tabular-nums">
                {count} characters
            </p>

            {children}

            {drafts.length <= 1 && !isDrafting && (
                <Form
                    {...PostAlternativeController.store.form({
                        current_team: teamSlug,
                        post: postId,
                    })}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={processing}
                            className="justify-start"
                        >
                            <Sparkles /> Show me other angles
                        </Button>
                    )}
                </Form>
            )}
        </aside>
    );
}
```

`children` is where the existing approval form from `posts/show.tsx` goes, moved across
unchanged so the approve flow keeps its current behaviour and tests.

- [ ] **Step 3: Extract the thread column**

Create `resources/js/components/agent/thread-column.tsx` by moving the message list and
the revision `Form` out of `posts/show.tsx` verbatim. It takes:

```tsx
type Props = {
    teamSlug: string;
    postId: number;
    post: PostData;
    messages: ThreadMessageData[];
    drafts: DraftData[];
    isDrafting: boolean;
};
```

The `DraftCard` list that currently renders inside a `drafts` message moves to the canvas,
so the thread shows the agent's turn as prose only. Add one branch for the new kind:

```tsx
{message.kind === 'error' && (
    <p className="text-meta text-destructive">{message.body}</p>
)}
```

Add `'error'` to `ThreadMessage['kind']` in `resources/js/types/agent.ts`.

- [ ] **Step 4: Add the follow-up chips**

Below the revision form in `thread-column.tsx`, add the static chips. They are not
generated — each one submits fixed text through the existing revision route, so no new
endpoint is needed.

```tsx
const FOLLOW_UPS = [
    'Make it shorter',
    'Change the tone',
    'Add a stronger hook',
] as const;
```

```tsx
{!isDrafting && drafts.length > 0 && (
    <div className="flex flex-wrap gap-1.5">
        {FOLLOW_UPS.map((text) => (
            <Form
                key={text}
                {...PostMessageController.store.form({
                    current_team: teamSlug,
                    post: postId,
                })}
                options={{ preserveScroll: true }}
            >
                <input type="hidden" name="body" value={text} />
                <Button type="submit" variant="outline" size="sm">
                    {text}
                </Button>
            </Form>
        ))}
    </div>
)}
```

Approve & publish is deliberately not a chip here — it lives on the canvas, where the
thing being approved is visible.

- [ ] **Step 5: Reduce the page to composition**

Rewrite `resources/js/pages/posts/show.tsx` so the page file is composition only. The
`isDrafting` test is "the last thing in the thread came from the user", which is exactly
the window between dispatching the job and the agent's turn being persisted.

```tsx
export default function Show({ post, drafts, messages, run, steps }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    const isDrafting =
        post.status === 'draft' && messages.at(-1)?.role === 'user';

    const { streamed, failed } = usePostStream(post.id, isDrafting);

    return (
        <>
            <Head title={post.brief} />
            <PageContainer width="wide" className="flex h-full flex-col">
                <div className="grid h-full grid-cols-1 gap-6 lg:grid-cols-2">
                    <ThreadColumn
                        teamSlug={teamSlug}
                        postId={post.id}
                        post={post}
                        messages={messages}
                        drafts={drafts}
                        isDrafting={isDrafting}
                    />
                    <DraftCanvas
                        teamSlug={teamSlug}
                        postId={post.id}
                        drafts={drafts}
                        streamed={streamed}
                        failed={failed}
                        isDrafting={isDrafting}
                    >
                        {/* the existing approval Form, moved across unchanged */}
                    </DraftCanvas>
                </div>
            </PageContainer>
        </>
    );
}
```

Keep `Show.layout` and its breadcrumbs exactly as they are. Keep the deferred run-steps
panel wherever it renders today — it is out of scope for this change.

- [ ] **Step 6: Verify**

Run: `npm run build`
Expected: builds with no TypeScript errors.

Run: `php artisan test --compact --filter=PostWorkspaceTest`
Expected: PASS. The workspace test asserts on props and component name, both unchanged.

Manual check, with three processes running (`php artisan serve`, `php artisan queue:work`,
`php artisan reverb:start`): submit a brief on Home and confirm the draft types into the
canvas. Then stop `reverb:start`, submit another brief, and confirm the draft still
appears within about eight seconds via the polling fallback. **Both halves matter** — the
second is the one that proves streaming is enhancement rather than a dependency.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/agent resources/js/hooks/use-post-stream.ts resources/js/pages/posts/show.tsx resources/js/types/agent.ts
git commit -m "feat: pin the draft beside the thread and stream it in"
```

---

### Task 9: Deployment notes

**Files:**
- Modify: `docs/deployment.md` (currently untracked)

- [ ] **Step 1: Document the processes**

Add a section covering the two long-running processes the app now needs beside the web
server:

```bash
php artisan queue:work    # drafts posts
php artisan reverb:start  # streams them to the browser
```

State plainly that if Reverb is not running, drafting still works — the client falls back
to polling and the draft appears a few seconds later. That is the behaviour to expect, not
a bug to chase.

Document the `REVERB_*` environment variables and note that `BROADCAST_CONNECTION` must be
`reverb` in production.

- [ ] **Step 2: Commit**

```bash
git add docs/deployment.md
git commit -m "docs: note the queue and reverb processes"
```

---

## Final verification

- [ ] Run the full suite: `php artisan test --compact` — expected: all pass
- [ ] Run `vendor/bin/pint --dirty --format agent` — expected: no changes left to make
- [ ] Run `npm run build` — expected: clean build
- [ ] Run `npx tsc --noEmit` if the project has a typecheck script — expected: no errors
- [ ] Confirm `CopywriterTest` still passes untouched, proving the three-variant path was preserved rather than rewritten
