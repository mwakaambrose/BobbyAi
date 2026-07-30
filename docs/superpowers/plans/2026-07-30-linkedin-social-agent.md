# LinkedIn Social Agent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a local Ollama model draft LinkedIn posts in a team's brand voice and, after a human approves the exact text, publish them by driving a real browser one gated action at a time, with every step recorded and replayable.

**Architecture:** Laravel is the control plane: it owns the state machine, the policy gate, and the audit trail. A small Node sidecar owns Chromium via Playwright and does exactly two things — observe (annotated screenshot + numbered element list) and act (by element ref). Ollama is consulted only to write drafts and to choose the next action. The UI is a read-only Kanban board plus a two-panel post workspace (chat thread on the left, live run theatre on the right).

**Tech Stack:** Laravel 13, PHP 8.4, Inertia v3, React 19, Tailwind v4, shadcn/ui, Wayfinder, Pest 5, `laravel/ai` ^0.10 (Ollama provider), Node + Playwright sidecar, SQLite.

**Spec:** [`docs/superpowers/specs/2026-07-30-linkedin-social-agent-design.md`](../specs/2026-07-30-linkedin-social-agent-design.md)

## Global Constraints

Every task's requirements implicitly include this section.

- **Slice scope.** LinkedIn only. Text-only posts. No images, no analytics, no scheduling, no websockets. Do not add features outside this list.
- **Dependencies.** Only two additions are approved: `laravel/ai ^0.10` in `composer.json`, and `playwright` in a new isolated `browser/package.json`. The root `package.json` must not change. Any other dependency requires asking first.
- **Repo conventions.** Follow the existing starter kit exactly: controllers in `app/Http/Controllers/`, form requests in `app/Http/Requests/`, enums in `app/Enums/` with TitleCase cases, pages in `resources/js/pages/` with lowercase-kebab filenames, `Inertia::render('studio/index', [...])`, `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])` for toasts.
- **PHP style.** Curly braces always. Constructor property promotion. Explicit return types and parameter type hints on every method. PHPDoc blocks over inline comments. Array shape types in PHPDoc.
- **Team scoping.** All agent routes live in the existing `Route::prefix('{current_team}')->middleware(['auth', 'verified', EnsureTeamMembership::class])` group in `routes/web.php`. Resolve the team as `$request->user()->currentTeam`. Every query must be scoped by `team_id`; every route-model-bound record must 404 if it belongs to another team.
- **Frontend routing.** Use Wayfinder-generated imports from `@/actions/...` or `@/routes/...`. Never hardcode a URL string.
- **Testing.** Pest. Every task ends with passing tests. Run the narrowest command: `php artisan test --compact --filter=<Name>`. Real LinkedIn is never touched by a test.
- **Formatting gates.** After PHP changes: `vendor/bin/pint --dirty --format agent`. After TS/React changes: `npm run lint` and `npm run types:check`.
- **laravel/ai is 0.x.** Installed version is v0.10.1. Faking uses the agent's own static API — `MyAgent::fake([...])`, `MyAgent::assertPrompted(fn ($prompt) => ...)`, `MyAgent::assertNeverPrompted()` — NOT `Ai::fakeAgent()`. Structured responses are array-accessible (`$response['action']`). Providers are referenced with `Laravel\Ai\Enums\Lab` or a config key string. Consult the `ai-sdk-development` skill and `search-docs` before writing agent code.
- **Config values (exact).** step cap `18`, run timeout `300`s, browser step timeout `20`s, inference timeout `60`s, screenshot retention `30` days, sidecar `http://127.0.0.1:4571`, allowed host `www.linkedin.com`, feed URL `https://www.linkedin.com/feed/`.
- **Commits.** One commit per task minimum, conventional-commit style (`feat:`, `test:`, `chore:`).

## File Structure

**Backend — agent core (`app/Agent/`)**

| File | Responsibility |
|------|----------------|
| `Contracts/BrowserDriver.php` | The only interface the runner knows about for browsing |
| `Browser/SidecarBrowserDriver.php` | HTTP client for the Node sidecar |
| `Browser/FakeBrowserDriver.php` | Scripted observations for tests; asserts actions received |
| `Browser/SessionHandle.php` | Opaque `{sessionId}` returned by `openSession` |
| `Values/Action.php` | A single proposed browser action + validation |
| `Values/ActionResult.php` | Outcome of executing an action |
| `Values/Observation.php` | url, title, screenshot, elements; renders the prompt element list |
| `Values/PageElement.php` | ref, role, name, state |
| `Values/GateDecision.php` | allowed/denied + reason |
| `Values/RunContract.php` | Immutable per-run permissions, built at approval time |
| `PublishPolicy.php` | Pure allow/deny logic — no dependencies |
| `PublishRunner.php` | The observe → think → gate → act → record state machine |
| `ActionPlanner.php` | Observation → Action, wrapping the AI agent, with one retry |
| `Copywriter.php` | Brief + brand → three drafts, with deterministic banned-term check |
| `Exceptions/PlannerFailed.php`, `Exceptions/RunAborted.php` | Typed failures |

**Backend — AI agents (`app/Ai/Agents/`)**

`ActionPlannerAgent.php` (structured), `PublishVerifierAgent.php` (structured), `CopywriterAgent.php` (structured).

**Backend — HTTP (`app/Http/Controllers/Agent/`)**

`StudioController.php`, `PostController.php`, `PostMessageController.php`, `PostApprovalController.php`, `RunController.php`, `SocialAccountController.php`, `BrandProfileController.php`.

**Backend — jobs & commands**

`app/Jobs/PublishPostRun.php`, `app/Console/Commands/AgentDoctor.php`, `app/Console/Commands/PruneAgentScreenshots.php`.

**Models & enums**

`app/Models/{BrandProfile,SocialAccount,Post,PostDraft,PostMessage,AgentRun,AgentStep}.php`;
`app/Enums/{SocialPlatform,AccountStatus,PostStatus,RunStatus,RunMode,StepPhase,GateVerdict,ActionType,MessageRole,MessageKind}.php`.

**Sidecar (`browser/`)**

`package.json`, `server.mjs` (HTTP routing), `session.mjs` (Playwright contexts), `observe.mjs` (marks + screenshot), `act.mjs` (execute by ref), `elements.mjs` (pure formatting — unit tested), `elements.test.mjs`.

**Frontend (`resources/js/`)**

`pages/studio/index.tsx`, `pages/posts/show.tsx`, `pages/runs/index.tsx`, `pages/runs/show.tsx`, `pages/accounts/index.tsx`, `pages/brand/edit.tsx`;
`components/agent/{board-column,post-card,draft-card,thread-message,run-timeline,step-row,screenshot-viewer,post-preview,empty-state}.tsx`;
`types/agent.ts`.

**Tests**

`tests/Unit/Agent/{PublishPolicyTest,ActionTest,ObservationTest,RunContractTest}.php`;
`tests/Feature/Agent/{PublishRunnerTest,ActionPlannerTest,CopywriterTest,PostLifecycleTest,StudioTest,AccountsTest,BrandProfileTest,RunReplayTest,AgentDoctorTest}.php`.

---

### Task 1: Dependencies and configuration

**Files:**
- Modify: `composer.json`
- Create: `config/agent.php`
- Modify: `config/ai.php` (published by the package)
- Modify: `.env`, `.env.example`
- Test: `tests/Feature/Agent/AgentConfigTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `config('agent.*')` keys used by every later task — `provider`, `model`, `perception`, `step_cap`, `run_timeout`, `browser_timeout`, `inference_timeout`, `sidecar_url`, `screenshot_retention_days`, `linkedin.host`, `linkedin.feed_url`.

- [ ] **Step 1: Install the AI SDK**

```bash
composer require laravel/ai:^0.10
```

- [ ] **Step 2: Publish and point the SDK at Ollama**

```bash
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
```

Then in `config/ai.php` change only the default line:

```php
'default' => env('AI_PROVIDER', 'ollama'),
```

- [ ] **Step 3: Write the failing config test**

Create `tests/Feature/Agent/AgentConfigTest.php`:

```php
<?php

test('agent configuration exposes the documented defaults', function (): void {
    expect(config('agent.step_cap'))->toBe(18)
        ->and(config('agent.run_timeout'))->toBe(300)
        ->and(config('agent.browser_timeout'))->toBe(20)
        ->and(config('agent.inference_timeout'))->toBe(60)
        ->and(config('agent.screenshot_retention_days'))->toBe(30)
        ->and(config('agent.perception'))->toBe('marks')
        ->and(config('agent.sidecar_url'))->toBe('http://127.0.0.1:4571')
        ->and(config('agent.linkedin.host'))->toBe('www.linkedin.com')
        ->and(config('agent.linkedin.feed_url'))->toBe('https://www.linkedin.com/feed/');
});

test('the ai sdk defaults to the local ollama provider', function (): void {
    expect(config('ai.default'))->toBe('ollama')
        ->and(config('ai.providers.ollama.driver'))->toBe('ollama');
});
```

- [ ] **Step 4: Run it and watch it fail**

Run: `php artisan test --compact --filter=AgentConfigTest`
Expected: FAIL — `config('agent.step_cap')` is `null`.

- [ ] **Step 5: Create `config/agent.php`**

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | The local Ollama provider and model used for both copywriting and browser
    | action planning. The model must accept image attachments when perception
    | is set to "marks"; run "php artisan agent:doctor" to verify.
    |
    */

    'provider' => env('AGENT_PROVIDER', 'ollama'),
    'model' => env('AGENT_MODEL', 'gemma3:12b'),

    /*
    |--------------------------------------------------------------------------
    | Perception
    |--------------------------------------------------------------------------
    |
    | "marks" sends an annotated screenshot alongside the element list. "text"
    | sends the element list only, for models without reliable vision support.
    | Screenshots are captured and retained for the audit trail either way.
    |
    */

    'perception' => env('AGENT_PERCEPTION', 'marks'),

    /*
    |--------------------------------------------------------------------------
    | Run Limits
    |--------------------------------------------------------------------------
    */

    'step_cap' => (int) env('AGENT_STEP_CAP', 18),
    'run_timeout' => (int) env('AGENT_RUN_TIMEOUT', 300),
    'browser_timeout' => (int) env('AGENT_BROWSER_TIMEOUT', 20),
    'inference_timeout' => (int) env('AGENT_INFERENCE_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Browser Sidecar
    |--------------------------------------------------------------------------
    */

    'sidecar_url' => env('BROWSER_SIDECAR_URL', 'http://127.0.0.1:4571'),
    'profile_path' => storage_path('app/private/browser-profiles'),

    /*
    |--------------------------------------------------------------------------
    | Audit Trail
    |--------------------------------------------------------------------------
    */

    'screenshot_disk' => env('AGENT_SCREENSHOT_DISK', 'local'),
    'screenshot_retention_days' => (int) env('AGENT_SCREENSHOT_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | LinkedIn
    |--------------------------------------------------------------------------
    */

    'linkedin' => [
        'host' => 'www.linkedin.com',
        'feed_url' => 'https://www.linkedin.com/feed/',
    ],

];
```

- [ ] **Step 6: Add the env keys**

Append to both `.env` and `.env.example`:

```ini
AI_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434

AGENT_MODEL=gemma3:12b
AGENT_PERCEPTION=marks
AGENT_STEP_CAP=18
AGENT_RUN_TIMEOUT=300
BROWSER_SIDECAR_URL=http://127.0.0.1:4571
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact --filter=AgentConfigTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add composer.json composer.lock config/ai.php config/agent.php .env.example tests/Feature/Agent/AgentConfigTest.php
git commit -m "feat: add laravel/ai with local ollama provider and agent config"
```

---

### Task 2: Accounts and brand persistence

**Files:**
- Create: `app/Enums/SocialPlatform.php`, `app/Enums/AccountStatus.php`
- Create: `database/migrations/*_create_brand_profiles_table.php`, `database/migrations/*_create_social_accounts_table.php`
- Create: `app/Models/BrandProfile.php`, `app/Models/SocialAccount.php`
- Create: `database/factories/BrandProfileFactory.php`, `database/factories/SocialAccountFactory.php`
- Test: `tests/Feature/Agent/AccountsPersistenceTest.php`

**Interfaces:**
- Consumes: `App\Models\Team` (existing).
- Produces: `SocialAccount` with `team()`, `status: AccountStatus`, `profileDirectory(): string`, scope `connected()`; `BrandProfile` with `team()` and array-cast `topics`, `banned_terms`, `examples`. `AccountStatus::{Disconnected,Connected,Expired}`, `SocialPlatform::LinkedIn`.

- [ ] **Step 1: Generate the enums**

```bash
php artisan make:enum SocialPlatform --string --no-interaction
php artisan make:enum AccountStatus --string --no-interaction
```

`app/Enums/SocialPlatform.php`:

```php
<?php

namespace App\Enums;

enum SocialPlatform: string
{
    case LinkedIn = 'linkedin';

    /**
     * Get the human readable label for the platform.
     */
    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
        };
    }
}
```

`app/Enums/AccountStatus.php`:

```php
<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Disconnected = 'disconnected';
    case Connected = 'connected';
    case Expired = 'expired';

    /**
     * Determine whether the account may start a publishing run.
     */
    public function isPublishable(): bool
    {
        return $this === self::Connected;
    }
}
```

- [ ] **Step 2: Write the failing persistence test**

Create `tests/Feature/Agent/AccountsPersistenceTest.php`:

```php
<?php

use App\Enums\AccountStatus;
use App\Enums\SocialPlatform;
use App\Models\BrandProfile;
use App\Models\SocialAccount;
use App\Models\Team;

test('a social account belongs to a team and casts its status', function (): void {
    $account = SocialAccount::factory()->create();

    expect($account->team)->toBeInstanceOf(Team::class)
        ->and($account->platform)->toBe(SocialPlatform::LinkedIn)
        ->and($account->status)->toBe(AccountStatus::Disconnected);
});

test('the connected scope only returns connected accounts', function (): void {
    SocialAccount::factory()->create();
    $connected = SocialAccount::factory()->connected()->create();

    expect(SocialAccount::connected()->pluck('id')->all())->toBe([$connected->id]);
});

test('each account has an isolated browser profile directory', function (): void {
    $account = SocialAccount::factory()->create();

    expect($account->profileDirectory())
        ->toBe(config('agent.profile_path').'/'.$account->id);
});

test('a brand profile casts its json columns to arrays', function (): void {
    $brand = BrandProfile::factory()->create([
        'topics' => ['savings', 'fintech'],
        'banned_terms' => ['synergy'],
        'examples' => ['We built this for members.'],
    ]);

    expect($brand->topics)->toBe(['savings', 'fintech'])
        ->and($brand->banned_terms)->toBe(['synergy'])
        ->and($brand->examples)->toBe(['We built this for members.']);
});
```

- [ ] **Step 3: Run it and watch it fail**

Run: `php artisan test --compact --filter=AccountsPersistenceTest`
Expected: FAIL — `Class "App\Models\SocialAccount" not found`.

- [ ] **Step 4: Create the migrations**

```bash
php artisan make:migration create_brand_profiles_table --no-interaction
php artisan make:migration create_social_accounts_table --no-interaction
```

Brand profiles migration body:

```php
Schema::create('brand_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
    $table->text('voice')->nullable();
    $table->text('audience')->nullable();
    $table->json('topics')->nullable();
    $table->json('banned_terms')->nullable();
    $table->json('examples')->nullable();
    $table->timestamps();
});
```

Social accounts migration body:

```php
Schema::create('social_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->string('platform');
    $table->string('handle')->nullable();
    $table->string('status')->default('disconnected');
    $table->timestamp('last_verified_at')->nullable();
    $table->timestamps();

    $table->unique(['team_id', 'platform', 'handle']);
});
```

- [ ] **Step 5: Create the models**

`app/Models/SocialAccount.php`:

```php
<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    /** @use HasFactory<\Database\Factories\SocialAccountFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the team that owns the account.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the filesystem path of the account's persistent browser profile.
     */
    public function profileDirectory(): string
    {
        return config('agent.profile_path').'/'.$this->id;
    }

    /**
     * Scope the query to accounts with a live browser session.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeConnected(Builder $query): void
    {
        $query->where('status', AccountStatus::Connected);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => AccountStatus::class,
            'last_verified_at' => 'datetime',
        ];
    }
}
```

`app/Models/BrandProfile.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandProfile extends Model
{
    /** @use HasFactory<\Database\Factories\BrandProfileFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the team that owns the brand profile.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'banned_terms' => 'array',
            'examples' => 'array',
        ];
    }
}
```

- [ ] **Step 6: Create the factories**

`database/factories/SocialAccountFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\SocialPlatform;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'platform' => SocialPlatform::LinkedIn,
            'handle' => fake()->userName(),
            'status' => AccountStatus::Disconnected,
        ];
    }

    /**
     * Indicate that the account has a verified browser session.
     */
    public function connected(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountStatus::Connected,
            'last_verified_at' => now(),
        ]);
    }
}
```

`database/factories/BrandProfileFactory.php`:

```php
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
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact --filter=AccountsPersistenceTest`
Expected: PASS (4 tests).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums app/Models database/migrations database/factories tests/Feature/Agent/AccountsPersistenceTest.php
git commit -m "feat: add social account and brand profile persistence"
```

---

### Task 3: Post, draft, message, run and step persistence

**Files:**
- Create: `app/Enums/{PostStatus,RunStatus,RunMode,StepPhase,GateVerdict,MessageRole,MessageKind}.php`
- Create: migrations for `posts`, `post_drafts`, `post_messages`, `agent_runs`, `agent_steps`
- Create: `app/Models/{Post,PostDraft,PostMessage,AgentRun,AgentStep}.php` and matching factories
- Test: `tests/Feature/Agent/PostPersistenceTest.php`

**Interfaces:**
- Consumes: `SocialAccount`, `Team`, `User`.
- Produces: `Post` (`drafts()`, `messages()`, `runs()`, `latestRun()`, `account()`, `status: PostStatus`, `approved_body`); `AgentRun` (`post()`, `steps()`, `contract` array cast, `isActive(): bool`); `AgentStep` (`run()`, `action` array cast). Enum cases: `PostStatus::{Brief,Draft,Approved,Publishing,Published,Failed}`, `RunStatus::{Queued,Running,Published,Failed,Aborted}`, `RunMode::{Autonomous,Step}`, `StepPhase::{Observe,Think,Gate,Act,Verify}`, `GateVerdict::{Allowed,Denied}`, `MessageRole::{User,Agent}`, `MessageKind::{Text,Drafts,Run}`.

- [ ] **Step 1: Create the enums**

```php
// app/Enums/PostStatus.php
<?php

namespace App\Enums;

enum PostStatus: string
{
    case Brief = 'brief';
    case Draft = 'draft';
    case Approved = 'approved';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';

    /**
     * Get the ordered list of board columns.
     *
     * @return array<int, self>
     */
    public static function board(): array
    {
        return [self::Brief, self::Draft, self::Approved, self::Publishing, self::Published, self::Failed];
    }

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
```

```php
// app/Enums/RunStatus.php
<?php

namespace App\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Published = 'published';
    case Failed = 'failed';
    case Aborted = 'aborted';

    /**
     * Determine whether the run is still in progress.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }
}
```

```php
// app/Enums/RunMode.php
<?php

namespace App\Enums;

enum RunMode: string
{
    case Autonomous = 'autonomous';
    case Step = 'step';
}
```

```php
// app/Enums/StepPhase.php
<?php

namespace App\Enums;

enum StepPhase: string
{
    case Observe = 'observe';
    case Think = 'think';
    case Gate = 'gate';
    case Act = 'act';
    case Verify = 'verify';
}
```

```php
// app/Enums/GateVerdict.php
<?php

namespace App\Enums;

enum GateVerdict: string
{
    case Allowed = 'allowed';
    case Denied = 'denied';
}
```

```php
// app/Enums/MessageRole.php
<?php

namespace App\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Agent = 'agent';
}
```

```php
// app/Enums/MessageKind.php
<?php

namespace App\Enums;

enum MessageKind: string
{
    case Text = 'text';
    case Drafts = 'drafts';
    case Run = 'run';
}
```

- [ ] **Step 2: Write the failing persistence test**

Create `tests/Feature/Agent/PostPersistenceTest.php`:

```php
<?php

use App\Enums\GateVerdict;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Post;
use App\Models\PostDraft;

test('a post exposes its drafts, messages and runs', function (): void {
    $post = Post::factory()->create();
    PostDraft::factory()->count(3)->create(['post_id' => $post->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);

    expect($post->status)->toBe(PostStatus::Brief)
        ->and($post->drafts)->toHaveCount(3)
        ->and($post->latestRun()->id)->toBe($run->id);
});

test('a run casts its contract and reports whether it is active', function (): void {
    $run = AgentRun::factory()->create([
        'contract' => ['allowed_host' => 'www.linkedin.com', 'step_budget' => 18],
    ]);

    expect($run->contract['allowed_host'])->toBe('www.linkedin.com')
        ->and($run->status)->toBe(RunStatus::Queued)
        ->and($run->isActive())->toBeTrue();
});

test('a step records the action and the gate verdict', function (): void {
    $step = AgentStep::factory()->create([
        'action' => ['action' => 'click', 'ref' => 7, 'why' => 'open the composer'],
        'gate_decision' => GateVerdict::Allowed,
    ]);

    expect($step->action['ref'])->toBe(7)
        ->and($step->gate_decision)->toBe(GateVerdict::Allowed)
        ->and($step->run)->toBeInstanceOf(AgentRun::class);
});

test('deleting a post cascades to its runs and steps', function (): void {
    $step = AgentStep::factory()->create();
    $post = $step->run->post;

    $post->delete();

    expect(AgentRun::count())->toBe(0)
        ->and(AgentStep::count())->toBe(0);
});
```

- [ ] **Step 3: Run it and watch it fail**

Run: `php artisan test --compact --filter=PostPersistenceTest`
Expected: FAIL — `Class "App\Models\Post" not found`.

- [ ] **Step 4: Create the migrations**

```bash
php artisan make:migration create_posts_table --no-interaction
php artisan make:migration create_agent_runs_table --no-interaction
```

Posts migration body (posts, drafts and messages live together — they change together):

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
    $table->text('brief');
    $table->string('status')->default('brief');
    $table->text('approved_body')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('published_url')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    $table->index(['team_id', 'status']);
});

Schema::create('post_drafts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->string('variant', 1);
    $table->text('body');
    $table->text('rationale')->nullable();
    $table->unsignedInteger('character_count');
    $table->string('model')->nullable();
    $table->timestamp('selected_at')->nullable();
    $table->timestamps();
});

Schema::create('post_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->string('role');
    $table->string('kind')->default('text');
    $table->text('body')->nullable();
    $table->json('payload')->nullable();
    $table->timestamps();
});
```

Agent runs migration body:

```php
Schema::create('agent_runs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
    $table->string('status')->default('queued');
    $table->string('mode')->default('autonomous');
    $table->json('contract');
    $table->unsignedInteger('step_count')->default(0);
    $table->string('failure_reason')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('finished_at')->nullable();
    $table->timestamps();
});

Schema::create('agent_steps', function (Blueprint $table) {
    $table->id();
    $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('index');
    $table->string('phase');
    $table->json('action')->nullable();
    $table->text('reasoning')->nullable();
    $table->unsignedInteger('ref')->nullable();
    $table->string('screenshot_path')->nullable();
    $table->string('gate_decision')->nullable();
    $table->string('gate_reason')->nullable();
    $table->unsignedInteger('latency_ms')->nullable();
    $table->text('error')->nullable();
    $table->timestamps();

    $table->unique(['agent_run_id', 'index']);
});
```

- [ ] **Step 5: Create the models**

`app/Models/Post.php`:

```php
<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the team that owns the post.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the social account the post publishes to.
     *
     * @return BelongsTo<SocialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Get the generated drafts for the post.
     *
     * @return HasMany<PostDraft, $this>
     */
    public function drafts(): HasMany
    {
        return $this->hasMany(PostDraft::class)->orderBy('variant');
    }

    /**
     * Get the workspace thread messages for the post.
     *
     * @return HasMany<PostMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(PostMessage::class)->oldest();
    }

    /**
     * Get the publishing runs for the post.
     *
     * @return HasMany<AgentRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * Get the most recent publishing run for the post.
     */
    public function latestRun(): ?AgentRun
    {
        return $this->runs()->latest('id')->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
```

`app/Models/PostDraft.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostDraft extends Model
{
    /** @use HasFactory<\Database\Factories\PostDraftFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the post the draft belongs to.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['selected_at' => 'datetime'];
    }
}
```

`app/Models/PostMessage.php`:

```php
<?php

namespace App\Models;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMessage extends Model
{
    /** @use HasFactory<\Database\Factories\PostMessageFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the post the message belongs to.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'kind' => MessageKind::class,
            'payload' => 'array',
        ];
    }
}
```

`app/Models/AgentRun.php`:

```php
<?php

namespace App\Models;

use App\Enums\RunMode;
use App\Enums\RunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentRun extends Model
{
    /** @use HasFactory<\Database\Factories\AgentRunFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the post being published.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the account the run publishes to.
     *
     * @return BelongsTo<SocialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Get the recorded steps for the run, in execution order.
     *
     * @return HasMany<AgentStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class)->orderBy('index');
    }

    /**
     * Determine whether the run is still in progress.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'mode' => RunMode::class,
            'contract' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
```

`app/Models/AgentStep.php`:

```php
<?php

namespace App\Models;

use App\Enums\GateVerdict;
use App\Enums\StepPhase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStep extends Model
{
    /** @use HasFactory<\Database\Factories\AgentStepFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the run the step belongs to.
     *
     * @return BelongsTo<AgentRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => StepPhase::class,
            'gate_decision' => GateVerdict::class,
            'action' => 'array',
        ];
    }
}
```

- [ ] **Step 6: Create the factories**

```php
// database/factories/PostFactory.php
<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\SocialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'social_account_id' => SocialAccount::factory(),
            'author_id' => User::factory(),
            'brief' => 'Announce Growth Savings in a warm, jargon-free tone.',
            'status' => PostStatus::Brief,
        ];
    }

    /**
     * Indicate that the post has approved copy ready to publish.
     */
    public function approved(string $body = 'We built Growth Savings because members asked for it.'): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Approved,
            'approved_body' => $body,
            'approved_at' => now(),
        ]);
    }
}
```

```php
// database/factories/PostDraftFactory.php
<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PostDraft>
 */
class PostDraftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->sentence(12);

        return [
            'post_id' => Post::factory(),
            'variant' => fake()->randomElement(['A', 'B', 'C']),
            'body' => $body,
            'rationale' => 'Hook-led opening.',
            'character_count' => mb_strlen($body),
            'model' => 'gemma3:12b',
        ];
    }
}
```

```php
// database/factories/PostMessageFactory.php
<?php

namespace Database\Factories;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PostMessage>
 */
class PostMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => fake()->sentence(),
        ];
    }
}
```

```php
// database/factories/AgentRunFactory.php
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
```

```php
// database/factories/AgentStepFactory.php
<?php

namespace Database\Factories;

use App\Enums\GateVerdict;
use App\Enums\StepPhase;
use App\Models\AgentRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AgentStep>
 */
class AgentStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_run_id' => AgentRun::factory(),
            'index' => 0,
            'phase' => StepPhase::Act,
            'action' => ['action' => 'click', 'ref' => 7, 'why' => 'open the composer'],
            'reasoning' => 'The composer is not open yet.',
            'gate_decision' => GateVerdict::Allowed,
            'latency_ms' => 620,
        ];
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PostPersistenceTest`
Expected: PASS (4 tests).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums app/Models database/migrations database/factories tests/Feature/Agent/PostPersistenceTest.php
git commit -m "feat: add post, draft, message, run and step persistence"
```

---

### Task 4: Agent value objects

**Files:**
- Create: `app/Agent/Values/{PageElement,Observation,Action,ActionResult,GateDecision,RunContract}.php`
- Create: `app/Agent/Exceptions/{PlannerFailed,RunAborted}.php`
- Test: `tests/Unit/Agent/{ActionTest,ObservationTest,RunContractTest}.php`

**Interfaces:**
- Consumes: `App\Enums\ActionType` (created here), `App\Models\Post`.
- Produces:
  - `ActionType::{Click,Type,Press,Scroll,Wait,Finish,Abort}` (string-backed, lowercase).
  - `Action::fromArray(array $data): self` — throws `InvalidArgumentException` when a required field for the type is missing; props `type: ActionType`, `ref: ?int`, `text: ?string`, `key: ?string`, `why: string`; `toArray(): array`; `describe(): string`.
  - `PageElement` — `ref: int`, `role: string`, `name: string`, `state: array`; `isDisabled(): bool`.
  - `Observation` — `url: string`, `title: string`, `screenshot: ?string` (base64), `elements: PageElement[]`; `fromArray(array): self`; `element(int $ref): ?PageElement`; `host(): ?string`; `toPromptText(): string`.
  - `ActionResult` — `ok: bool`, `error: ?string`; `ok(): self`, `failed(string $error): self`.
  - `GateDecision` — `verdict: GateVerdict`, `reason: string`; `allow(): self`, `deny(string $reason): self`; `isAllowed(): bool`.
  - `RunContract` — `allowedHost`, `approvedBody`, `allowedRoles: string[]`, `stepBudget: int`; `forPost(Post $post): self`; `toArray()`/`fromArray()`.

- [ ] **Step 1: Create the ActionType enum**

`app/Enums/ActionType.php`:

```php
<?php

namespace App\Enums;

enum ActionType: string
{
    case Click = 'click';
    case Type = 'type';
    case Press = 'press';
    case Scroll = 'scroll';
    case Wait = 'wait';
    case Finish = 'finish';
    case Abort = 'abort';

    /**
     * Determine whether the action ends the run.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Finish, self::Abort], true);
    }

    /**
     * Determine whether the action targets a specific page element.
     */
    public function targetsElement(): bool
    {
        return in_array($this, [self::Click, self::Type], true);
    }
}
```

- [ ] **Step 2: Write the failing value object tests**

`tests/Unit/Agent/ActionTest.php`:

```php
<?php

use App\Agent\Values\Action;
use App\Enums\ActionType;

test('it builds a click action from model output', function (): void {
    $action = Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open the composer']);

    expect($action->type)->toBe(ActionType::Click)
        ->and($action->ref)->toBe(7)
        ->and($action->why)->toBe('open the composer')
        ->and($action->toArray())->toBe([
            'action' => 'click',
            'ref' => 7,
            'text' => null,
            'key' => null,
            'why' => 'open the composer',
        ]);
});

test('it rejects an unknown action type', function (): void {
    Action::fromArray(['action' => 'teleport', 'why' => 'why not']);
})->throws(InvalidArgumentException::class, 'Unknown action type [teleport].');

test('it requires a ref for element actions', function (): void {
    Action::fromArray(['action' => 'click', 'why' => 'no target']);
})->throws(InvalidArgumentException::class, 'Action [click] requires a ref.');

test('it requires text for a type action', function (): void {
    Action::fromArray(['action' => 'type', 'ref' => 12, 'why' => 'no text']);
})->throws(InvalidArgumentException::class, 'Action [type] requires text.');

test('it describes itself for the step history', function (): void {
    expect(Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'x'])->describe())
        ->toBe('click #7');
});
```

`tests/Unit/Agent/ObservationTest.php`:

```php
<?php

use App\Agent\Values\Observation;

function observationFixture(): Observation
{
    return Observation::fromArray([
        'url' => 'https://www.linkedin.com/feed/',
        'title' => 'Feed | LinkedIn',
        'screenshot' => 'ZmFrZQ==',
        'elements' => [
            ['ref' => 3, 'role' => 'link', 'name' => 'Home', 'state' => []],
            ['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []],
            ['ref' => 19, 'role' => 'button', 'name' => 'Post', 'state' => ['disabled' => true]],
        ],
    ]);
}

test('it exposes elements by ref', function (): void {
    expect(observationFixture()->element(7)->name)->toBe('Start a post')
        ->and(observationFixture()->element(99))->toBeNull();
});

test('it reports the page host', function (): void {
    expect(observationFixture()->host())->toBe('www.linkedin.com');
});

test('it renders a compact element list for the prompt', function (): void {
    expect(observationFixture()->toPromptText())->toBe(<<<'TEXT'
    URL: https://www.linkedin.com/feed/
    TITLE: Feed | LinkedIn
    ELEMENTS:
    [3] link "Home"
    [7] button "Start a post"
    [19] button "Post" (disabled)
    TEXT);
});

test('it knows when an element is disabled', function (): void {
    expect(observationFixture()->element(19)->isDisabled())->toBeTrue()
        ->and(observationFixture()->element(7)->isDisabled())->toBeFalse();
});
```

`tests/Unit/Agent/RunContractTest.php`:

```php
<?php

use App\Agent\Values\RunContract;
use App\Models\Post;

test('it is built from the approved body of a post', function (): void {
    $post = Post::factory()->approved('Exactly this copy.')->make();

    $contract = RunContract::forPost($post);

    expect($contract->approvedBody)->toBe('Exactly this copy.')
        ->and($contract->allowedHost)->toBe('www.linkedin.com')
        ->and($contract->stepBudget)->toBe(18)
        ->and($contract->allowedRoles)->toBe(['button', 'textbox', 'link']);
});

test('it round trips through an array', function (): void {
    $contract = new RunContract('www.linkedin.com', 'Copy.', ['button'], 5);

    expect(RunContract::fromArray($contract->toArray()))->toEqual($contract);
});
```

- [ ] **Step 3: Run them and watch them fail**

Run: `php artisan test --compact --filter="ActionTest|ObservationTest|RunContractTest"`
Expected: FAIL — `Class "App\Agent\Values\Action" not found`.

- [ ] **Step 4: Implement the value objects**

`app/Agent/Values/Action.php`:

```php
<?php

namespace App\Agent\Values;

use App\Enums\ActionType;
use InvalidArgumentException;

final readonly class Action
{
    public function __construct(
        public ActionType $type,
        public ?int $ref = null,
        public ?string $text = null,
        public ?string $key = null,
        public string $why = '',
    ) {}

    /**
     * Build an action from raw model output, validating the shape.
     *
     * @param  array{action?: string, ref?: int|string|null, text?: string|null, key?: string|null, why?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $type = ActionType::tryFrom((string) ($data['action'] ?? ''))
            ?? throw new InvalidArgumentException('Unknown action type ['.($data['action'] ?? '').'].');

        $ref = isset($data['ref']) ? (int) $data['ref'] : null;

        if ($type->targetsElement() && $ref === null) {
            throw new InvalidArgumentException('Action ['.$type->value.'] requires a ref.');
        }

        if ($type === ActionType::Type && blank($data['text'] ?? null)) {
            throw new InvalidArgumentException('Action [type] requires text.');
        }

        if ($type === ActionType::Press && blank($data['key'] ?? null)) {
            throw new InvalidArgumentException('Action [press] requires a key.');
        }

        return new self(
            type: $type,
            ref: $ref,
            text: $data['text'] ?? null,
            key: $data['key'] ?? null,
            why: (string) ($data['why'] ?? ''),
        );
    }

    /**
     * Get a one line description used in the step history.
     */
    public function describe(): string
    {
        return match (true) {
            $this->type->targetsElement() => $this->type->value.' #'.$this->ref,
            $this->type === ActionType::Press => 'press '.$this->key,
            default => $this->type->value,
        };
    }

    /**
     * @return array{action: string, ref: int|null, text: string|null, key: string|null, why: string}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->type->value,
            'ref' => $this->ref,
            'text' => $this->text,
            'key' => $this->key,
            'why' => $this->why,
        ];
    }
}
```

`app/Agent/Values/PageElement.php`:

```php
<?php

namespace App\Agent\Values;

final readonly class PageElement
{
    /**
     * @param  array<string, bool>  $state
     */
    public function __construct(
        public int $ref,
        public string $role,
        public string $name,
        public array $state = [],
    ) {}

    /**
     * @param  array{ref: int|string, role?: string, name?: string, state?: array<string, bool>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ref: (int) $data['ref'],
            role: (string) ($data['role'] ?? 'generic'),
            name: (string) ($data['name'] ?? ''),
            state: $data['state'] ?? [],
        );
    }

    /**
     * Determine whether the element cannot currently be interacted with.
     */
    public function isDisabled(): bool
    {
        return (bool) ($this->state['disabled'] ?? false);
    }

    /**
     * Render the element as a single prompt line.
     */
    public function toPromptLine(): string
    {
        return '['.$this->ref.'] '.$this->role.' "'.$this->name.'"'.($this->isDisabled() ? ' (disabled)' : '');
    }
}
```

`app/Agent/Values/Observation.php`:

```php
<?php

namespace App\Agent\Values;

final readonly class Observation
{
    /**
     * @param  PageElement[]  $elements
     */
    public function __construct(
        public string $url,
        public string $title,
        public ?string $screenshot,
        public array $elements,
    ) {}

    /**
     * @param  array{url?: string, title?: string, screenshot?: string|null, elements?: array<int, array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: (string) ($data['url'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            screenshot: $data['screenshot'] ?? null,
            elements: array_map(PageElement::fromArray(...), $data['elements'] ?? []),
        );
    }

    /**
     * Find the element carrying the given ref.
     */
    public function element(int $ref): ?PageElement
    {
        foreach ($this->elements as $element) {
            if ($element->ref === $ref) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Get the host of the currently loaded page.
     */
    public function host(): ?string
    {
        return parse_url($this->url, PHP_URL_HOST) ?: null;
    }

    /**
     * Render the observation as the textual half of the prompt.
     */
    public function toPromptText(): string
    {
        $lines = array_map(fn (PageElement $element): string => $element->toPromptLine(), $this->elements);

        return implode("\n", [
            'URL: '.$this->url,
            'TITLE: '.$this->title,
            'ELEMENTS:',
            ...$lines,
        ]);
    }
}
```

`app/Agent/Values/ActionResult.php`:

```php
<?php

namespace App\Agent\Values;

final readonly class ActionResult
{
    public function __construct(
        public bool $ok,
        public ?string $error = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function ok(): self
    {
        return new self(true);
    }

    /**
     * Create a failed result carrying the reason.
     */
    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
```

`app/Agent/Values/GateDecision.php`:

```php
<?php

namespace App\Agent\Values;

use App\Enums\GateVerdict;

final readonly class GateDecision
{
    public function __construct(
        public GateVerdict $verdict,
        public string $reason = '',
    ) {}

    /**
     * Allow the proposed action.
     */
    public static function allow(): self
    {
        return new self(GateVerdict::Allowed);
    }

    /**
     * Deny the proposed action for the given reason.
     */
    public static function deny(string $reason): self
    {
        return new self(GateVerdict::Denied, $reason);
    }

    /**
     * Determine whether the action may be executed.
     */
    public function isAllowed(): bool
    {
        return $this->verdict === GateVerdict::Allowed;
    }
}
```

`app/Agent/Values/RunContract.php`:

```php
<?php

namespace App\Agent\Values;

use App\Models\Post;

final readonly class RunContract
{
    /**
     * @param  string[]  $allowedRoles
     */
    public function __construct(
        public string $allowedHost,
        public string $approvedBody,
        public array $allowedRoles,
        public int $stepBudget,
    ) {}

    /**
     * Build the immutable contract for publishing the given approved post.
     */
    public static function forPost(Post $post): self
    {
        return new self(
            allowedHost: config('agent.linkedin.host'),
            approvedBody: (string) $post->approved_body,
            allowedRoles: ['button', 'textbox', 'link'],
            stepBudget: config('agent.step_cap'),
        );
    }

    /**
     * @param  array{allowed_host: string, approved_body: string, allowed_roles: string[], step_budget: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            allowedHost: $data['allowed_host'],
            approvedBody: $data['approved_body'],
            allowedRoles: $data['allowed_roles'],
            stepBudget: (int) $data['step_budget'],
        );
    }

    /**
     * @return array{allowed_host: string, approved_body: string, allowed_roles: string[], step_budget: int}
     */
    public function toArray(): array
    {
        return [
            'allowed_host' => $this->allowedHost,
            'approved_body' => $this->approvedBody,
            'allowed_roles' => $this->allowedRoles,
            'step_budget' => $this->stepBudget,
        ];
    }
}
```

- [ ] **Step 5: Create the exceptions**

```php
<?php
// app/Agent/Exceptions/PlannerFailed.php

namespace App\Agent\Exceptions;

use RuntimeException;

class PlannerFailed extends RuntimeException {}
```

```php
<?php
// app/Agent/Exceptions/RunAborted.php

namespace App\Agent\Exceptions;

use RuntimeException;

class RunAborted extends RuntimeException {}
```

- [ ] **Step 6: Point Pest's Unit suite at the framework**

`RunContractTest` uses model factories, so the Unit suite needs the application bootstrapped. In `tests/Pest.php`, extend the Unit directory too:

```php
pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact --filter="ActionTest|ObservationTest|RunContractTest"`
Expected: PASS (12 tests).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Agent app/Enums/ActionType.php tests/Unit/Agent tests/Pest.php
git commit -m "feat: add agent value objects for observations, actions and contracts"
```

---

### Task 5: The publish policy gate

This is the component whose correctness matters most, so it is pure: no models, no config reads at call time, no I/O.

**Files:**
- Create: `app/Agent/PublishPolicy.php`
- Test: `tests/Unit/Agent/PublishPolicyTest.php`

**Interfaces:**
- Consumes: `Action`, `Observation`, `RunContract`, `GateDecision`.
- Produces: `PublishPolicy::evaluate(Action $action, Observation $observation, RunContract $contract, int $stepIndex): GateDecision` and the public constants `BANNED_NAME_PATTERN` and `ALLOWED_KEYS`.

- [ ] **Step 1: Write the failing gate tests**

`tests/Unit/Agent/PublishPolicyTest.php`:

```php
<?php

use App\Agent\PublishPolicy;
use App\Agent\Values\Action;
use App\Agent\Values\Observation;
use App\Agent\Values\RunContract;

const APPROVED_BODY = 'We built Growth Savings because members asked for it.';

function contract(int $budget = 18): RunContract
{
    return new RunContract('www.linkedin.com', APPROVED_BODY, ['button', 'textbox', 'link'], $budget);
}

function feed(string $url = 'https://www.linkedin.com/feed/'): Observation
{
    return Observation::fromArray([
        'url' => $url,
        'title' => 'Feed | LinkedIn',
        'screenshot' => null,
        'elements' => [
            ['ref' => 3, 'role' => 'link', 'name' => 'Home', 'state' => []],
            ['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []],
            ['ref' => 9, 'role' => 'button', 'name' => 'Like', 'state' => []],
            ['ref' => 10, 'role' => 'button', 'name' => 'Send message', 'state' => []],
            ['ref' => 12, 'role' => 'textbox', 'name' => 'What do you want to talk about?', 'state' => []],
            ['ref' => 19, 'role' => 'button', 'name' => 'Post', 'state' => []],
            ['ref' => 20, 'role' => 'button', 'name' => 'Post', 'state' => ['disabled' => true]],
            ['ref' => 21, 'role' => 'img', 'name' => 'Add media', 'state' => []],
        ],
    ]);
}

function gate(Action $action, ?Observation $observation = null, ?RunContract $contract = null, int $step = 0): App\Agent\Values\GateDecision
{
    return (new PublishPolicy)->evaluate($action, $observation ?? feed(), $contract ?? contract(), $step);
}

test('it allows clicking the composer button', function (): void {
    expect(gate(Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']))->isAllowed())->toBeTrue();
});

test('it allows typing the exact approved body', function (): void {
    $action = Action::fromArray(['action' => 'type', 'ref' => 12, 'text' => APPROVED_BODY, 'why' => 'write']);

    expect(gate($action)->isAllowed())->toBeTrue();
});

test('it denies typing anything other than the approved body', function (string $text): void {
    $action = Action::fromArray(['action' => 'type', 'ref' => 12, 'text' => $text, 'why' => 'write']);

    $decision = gate($action);

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('approved');
})->with([
    'appended hashtag' => [APPROVED_BODY.' #fintech'],
    'trailing space' => [APPROVED_BODY.' '],
    'reworded' => ['We built Growth Savings for our members.'],
    'empty' => [''],
]);

test('it denies any action while off the allowed host', function (): void {
    $decision = gate(
        Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']),
        feed('https://example.com/feed/'),
    );

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('host');
});

test('it denies social graph interactions regardless of role', function (int $ref): void {
    $decision = gate(Action::fromArray(['action' => 'click', 'ref' => $ref, 'why' => 'engage']));

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('forbidden');
})->with(['like button' => [9], 'message button' => [10]]);

test('it denies clicking an element whose role is not allowed', function (): void {
    $decision = gate(Action::fromArray(['action' => 'click', 'ref' => 21, 'why' => 'add media']));

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('role');
});

test('it denies clicking a ref that is not on the page', function (): void {
    $decision = gate(Action::fromArray(['action' => 'click', 'ref' => 404, 'why' => 'stale']));

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('not present');
});

test('it denies clicking a disabled element', function (): void {
    $decision = gate(Action::fromArray(['action' => 'click', 'ref' => 20, 'why' => 'publish']));

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('disabled');
});

test('it denies typing into an element that is not a textbox', function (): void {
    $action = Action::fromArray(['action' => 'type', 'ref' => 7, 'text' => APPROVED_BODY, 'why' => 'write']);

    expect(gate($action)->isAllowed())->toBeFalse();
});

test('it only allows non submitting key presses', function (string $key, bool $allowed): void {
    $action = Action::fromArray(['action' => 'press', 'key' => $key, 'why' => 'navigate']);

    expect(gate($action)->isAllowed())->toBe($allowed);
})->with([
    'tab' => ['Tab', true],
    'escape' => ['Escape', true],
    'enter' => ['Enter', false],
    'meta enter' => ['Meta+Enter', false],
]);

test('it denies actions once the step budget is exhausted', function (): void {
    $decision = gate(
        Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']),
        contract: contract(budget: 3),
        step: 3,
    );

    expect($decision->isAllowed())->toBeFalse()
        ->and($decision->reason)->toContain('budget');
});

test('it always allows terminal actions even off host and over budget', function (string $type): void {
    $decision = gate(
        Action::fromArray(['action' => $type, 'why' => 'done']),
        feed('https://example.com/'),
        contract(budget: 1),
        step: 99,
    );

    expect($decision->isAllowed())->toBeTrue();
})->with([['finish'], ['abort']]);
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --compact --filter=PublishPolicyTest`
Expected: FAIL — `Class "App\Agent\PublishPolicy" not found`.

- [ ] **Step 3: Implement the policy**

`app/Agent/PublishPolicy.php`:

```php
<?php

namespace App\Agent;

use App\Agent\Values\Action;
use App\Agent\Values\GateDecision;
use App\Agent\Values\Observation;
use App\Agent\Values\PageElement;
use App\Agent\Values\RunContract;
use App\Enums\ActionType;

/**
 * Decides whether a model proposed browser action may be executed.
 *
 * This class is deliberately pure: given the same action, observation and
 * contract it always returns the same decision, which is what makes the
 * safety rules exhaustively testable.
 */
final class PublishPolicy
{
    /**
     * Accessible names matching this pattern are never clickable, whatever their role.
     */
    public const BANNED_NAME_PATTERN = '/\b(like|react|reaction|follow|connect|comment|reply|message|send|share|repost)\b/i';

    /**
     * Keys the agent may press. Submitting keys are excluded so that publishing
     * can only ever happen through an explicit, gated button click.
     *
     * @var string[]
     */
    public const ALLOWED_KEYS = ['Tab', 'Escape'];

    /**
     * Evaluate a proposed action against the run's contract.
     */
    public function evaluate(Action $action, Observation $observation, RunContract $contract, int $stepIndex): GateDecision
    {
        if ($action->type->isTerminal()) {
            return GateDecision::allow();
        }

        if ($stepIndex >= $contract->stepBudget) {
            return GateDecision::deny('step budget of '.$contract->stepBudget.' exhausted');
        }

        if ($observation->host() !== $contract->allowedHost) {
            return GateDecision::deny('page host ['.($observation->host() ?? 'unknown').'] is outside the allowed host ['.$contract->allowedHost.']');
        }

        return match ($action->type) {
            ActionType::Click => $this->evaluateClick($action, $observation, $contract),
            ActionType::Type => $this->evaluateType($action, $observation, $contract),
            ActionType::Press => $this->evaluatePress($action),
            ActionType::Scroll, ActionType::Wait => GateDecision::allow(),
            default => GateDecision::deny('unsupported action ['.$action->type->value.']'),
        };
    }

    /**
     * Evaluate a click against the element allow list and the interaction ban.
     */
    private function evaluateClick(Action $action, Observation $observation, RunContract $contract): GateDecision
    {
        $element = $this->resolve($action, $observation);

        if (! $element instanceof PageElement) {
            return GateDecision::deny('ref ['.$action->ref.'] is not present in the current observation');
        }

        if ($element->isDisabled()) {
            return GateDecision::deny('element ['.$action->ref.'] is disabled');
        }

        if (preg_match(self::BANNED_NAME_PATTERN, $element->name) === 1) {
            return GateDecision::deny('element ["'.$element->name.'"] is a forbidden social interaction');
        }

        if (! in_array($element->role, $contract->allowedRoles, true)) {
            return GateDecision::deny('element role ['.$element->role.'] is not an allowed role');
        }

        return GateDecision::allow();
    }

    /**
     * Evaluate typing, which may only ever reproduce the approved body verbatim.
     */
    private function evaluateType(Action $action, Observation $observation, RunContract $contract): GateDecision
    {
        $element = $this->resolve($action, $observation);

        if (! $element instanceof PageElement) {
            return GateDecision::deny('ref ['.$action->ref.'] is not present in the current observation');
        }

        if ($element->role !== 'textbox') {
            return GateDecision::deny('element role ['.$element->role.'] cannot receive text');
        }

        if ($action->text !== $contract->approvedBody) {
            return GateDecision::deny('typed text does not match the approved body');
        }

        return GateDecision::allow();
    }

    /**
     * Evaluate a key press against the non submitting key allow list.
     */
    private function evaluatePress(Action $action): GateDecision
    {
        return in_array($action->key, self::ALLOWED_KEYS, true)
            ? GateDecision::allow()
            : GateDecision::deny('key ['.$action->key.'] is not permitted');
    }

    /**
     * Resolve the element targeted by the action, if it exists.
     */
    private function resolve(Action $action, Observation $observation): ?PageElement
    {
        return $action->ref === null ? null : $observation->element($action->ref);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=PublishPolicyTest`
Expected: PASS (21 tests including datasets).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Agent/PublishPolicy.php tests/Unit/Agent/PublishPolicyTest.php
git commit -m "feat: add the publish policy gate"
```

---

### Task 6: Browser driver contract and fake

**Files:**
- Create: `app/Agent/Contracts/BrowserDriver.php`, `app/Agent/Browser/SessionHandle.php`, `app/Agent/Browser/FakeBrowserDriver.php`
- Test: `tests/Unit/Agent/FakeBrowserDriverTest.php`

**Interfaces:**
- Consumes: `SocialAccount`, `Observation`, `Action`, `ActionResult`.
- Produces:
  - `BrowserDriver::openSession(SocialAccount $account, bool $headed = false): SessionHandle`
  - `BrowserDriver::observe(SessionHandle $session): Observation`
  - `BrowserDriver::act(SessionHandle $session, Action $action): ActionResult`
  - `BrowserDriver::navigate(SessionHandle $session, string $url): ActionResult`
  - `BrowserDriver::closeSession(SessionHandle $session): void`
  - `FakeBrowserDriver::script(array $observations): self`, `->failNextAction(string $error)`, `->actions(): Action[]`, `->wasClosed(): bool`

- [ ] **Step 1: Write the failing fake driver test**

`tests/Unit/Agent/FakeBrowserDriverTest.php`:

```php
<?php

use App\Agent\Browser\FakeBrowserDriver;
use App\Agent\Values\Action;
use App\Agent\Values\Observation;
use App\Models\SocialAccount;

test('it replays scripted observations in order and repeats the last one', function (): void {
    $driver = (new FakeBrowserDriver)->script([
        Observation::fromArray(['url' => 'https://www.linkedin.com/feed/', 'title' => 'One', 'elements' => []]),
        Observation::fromArray(['url' => 'https://www.linkedin.com/feed/', 'title' => 'Two', 'elements' => []]),
    ]);

    $session = $driver->openSession(SocialAccount::factory()->make());

    expect($driver->observe($session)->title)->toBe('One')
        ->and($driver->observe($session)->title)->toBe('Two')
        ->and($driver->observe($session)->title)->toBe('Two');
});

test('it records every action it is asked to perform', function (): void {
    $driver = (new FakeBrowserDriver)->script([
        Observation::fromArray(['url' => 'https://www.linkedin.com/feed/', 'title' => 'Feed', 'elements' => []]),
    ]);

    $session = $driver->openSession(SocialAccount::factory()->make());
    $driver->act($session, Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']));

    expect($driver->actions())->toHaveCount(1)
        ->and($driver->actions()[0]->ref)->toBe(7);
});

test('it can be told to fail the next action', function (): void {
    $driver = (new FakeBrowserDriver)->script([
        Observation::fromArray(['url' => 'https://www.linkedin.com/feed/', 'title' => 'Feed', 'elements' => []]),
    ]);

    $session = $driver->openSession(SocialAccount::factory()->make());
    $driver->failNextAction('element detached');

    $result = $driver->act($session, Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']));

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toBe('element detached');
});

test('it tracks session closure', function (): void {
    $driver = (new FakeBrowserDriver)->script([]);
    $session = $driver->openSession(SocialAccount::factory()->make());

    $driver->closeSession($session);

    expect($driver->wasClosed())->toBeTrue();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=FakeBrowserDriverTest`
Expected: FAIL — `Class "App\Agent\Browser\FakeBrowserDriver" not found`.

- [ ] **Step 3: Create the contract and session handle**

`app/Agent/Contracts/BrowserDriver.php`:

```php
<?php

namespace App\Agent\Contracts;

use App\Agent\Browser\SessionHandle;
use App\Agent\Values\Action;
use App\Agent\Values\ActionResult;
use App\Agent\Values\Observation;
use App\Models\SocialAccount;

interface BrowserDriver
{
    /**
     * Open a browser session on the account's persistent profile.
     */
    public function openSession(SocialAccount $account, bool $headed = false): SessionHandle;

    /**
     * Capture the current page state, including the annotated screenshot.
     */
    public function observe(SessionHandle $session): Observation;

    /**
     * Execute a single action against the element it references.
     */
    public function act(SessionHandle $session, Action $action): ActionResult;

    /**
     * Navigate the session to the given URL.
     */
    public function navigate(SessionHandle $session, string $url): ActionResult;

    /**
     * Release the session, leaving the persistent profile intact.
     */
    public function closeSession(SessionHandle $session): void;
}
```

`app/Agent/Browser/SessionHandle.php`:

```php
<?php

namespace App\Agent\Browser;

final readonly class SessionHandle
{
    public function __construct(public string $id) {}
}
```

- [ ] **Step 4: Implement the fake driver**

`app/Agent/Browser/FakeBrowserDriver.php`:

```php
<?php

namespace App\Agent\Browser;

use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Action;
use App\Agent\Values\ActionResult;
use App\Agent\Values\Observation;
use App\Models\SocialAccount;

/**
 * An in memory browser driver used by tests. Observations are replayed in the
 * order they were scripted; the final observation repeats indefinitely so a
 * runner that loops longer than expected still terminates deterministically.
 */
final class FakeBrowserDriver implements BrowserDriver
{
    /** @var Observation[] */
    private array $observations = [];

    /** @var Action[] */
    private array $actions = [];

    private int $cursor = 0;

    private bool $closed = false;

    private ?string $nextError = null;

    /**
     * Script the observations returned by successive observe calls.
     *
     * @param  Observation[]  $observations
     */
    public function script(array $observations): self
    {
        $this->observations = $observations;

        return $this;
    }

    /**
     * Make the next action fail with the given error.
     */
    public function failNextAction(string $error): self
    {
        $this->nextError = $error;

        return $this;
    }

    /**
     * Get every action the runner asked for, in order.
     *
     * @return Action[]
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * Determine whether the session was released.
     */
    public function wasClosed(): bool
    {
        return $this->closed;
    }

    public function openSession(SocialAccount $account, bool $headed = false): SessionHandle
    {
        return new SessionHandle('fake-session');
    }

    public function observe(SessionHandle $session): Observation
    {
        if ($this->observations === []) {
            return Observation::fromArray(['url' => '', 'title' => '', 'elements' => []]);
        }

        $observation = $this->observations[min($this->cursor, count($this->observations) - 1)];

        $this->cursor++;

        return $observation;
    }

    public function act(SessionHandle $session, Action $action): ActionResult
    {
        $this->actions[] = $action;

        if ($this->nextError !== null) {
            $error = $this->nextError;
            $this->nextError = null;

            return ActionResult::failed($error);
        }

        return ActionResult::ok();
    }

    public function navigate(SessionHandle $session, string $url): ActionResult
    {
        return ActionResult::ok();
    }

    public function closeSession(SessionHandle $session): void
    {
        $this->closed = true;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=FakeBrowserDriverTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Agent/Contracts app/Agent/Browser tests/Unit/Agent/FakeBrowserDriverTest.php
git commit -m "feat: add browser driver contract and test fake"
```

---

### Task 7: The Node browser sidecar

**Files:**
- Create: `browser/package.json`, `browser/server.mjs`, `browser/session.mjs`, `browser/observe.mjs`, `browser/act.mjs`, `browser/elements.mjs`
- Create: `browser/elements.test.mjs`
- Modify: `composer.json` (add a `browser` script), `.gitignore` (ignore `browser/node_modules` and `storage/app/private/browser-profiles`)

**Interfaces:**
- Consumes: nothing from PHP.
- Produces the HTTP contract the next task codes against:
  - `POST /sessions` `{accountId, profileDir, headed}` → `201 {sessionId, url, title}`
  - `GET /sessions/:id/observation` → `200 {url, title, screenshot, elements:[{ref, role, name, state}]}`
  - `POST /sessions/:id/actions` `{action, ref, text, key}` → `200 {ok, error, url, title}`
  - `POST /sessions/:id/navigate` `{url}` → `200 {ok, url, title}`
  - `DELETE /sessions/:id` → `204`
  - `GET /health` → `200 {ok: true, chromium: true}`
- Also produces the pure helper `formatElements(raw, {max}) => elements[]` used by both the server and its test.

- [ ] **Step 1: Write the failing pure-function test**

`browser/elements.test.mjs`:

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import { formatElements, normalizeName } from './elements.mjs';

test('it drops elements without an accessible name', () => {
    const elements = formatElements([
        { ref: 1, role: 'button', name: 'Start a post', disabled: false },
        { ref: 2, role: 'button', name: '   ', disabled: false },
    ]);

    assert.deepEqual(elements, [
        { ref: 1, role: 'button', name: 'Start a post', state: {} },
    ]);
});

test('it preserves the page assigned ref so actions stay addressable', () => {
    const elements = formatElements([
        { ref: 42, role: 'textbox', name: 'What do you want to talk about?', disabled: false },
    ]);

    assert.equal(elements[0].ref, 42);
});

test('it reports disabled state', () => {
    const [element] = formatElements([{ ref: 3, role: 'button', name: 'Post', disabled: true }]);

    assert.deepEqual(element.state, { disabled: true });
});

test('it caps the element list', () => {
    const raw = Array.from({ length: 200 }, (_, index) => ({
        ref: index + 1,
        role: 'button',
        name: `Item ${index}`,
        disabled: false,
    }));

    assert.equal(formatElements(raw, { max: 60 }).length, 60);
});

test('it collapses whitespace and truncates long names', () => {
    assert.equal(normalizeName('  Start   a\n post '), 'Start a post');
    assert.equal(normalizeName('x'.repeat(300)).length, 120);
});
```

- [ ] **Step 2: Create the package and run the test to watch it fail**

`browser/package.json`:

```json
{
    "name": "bobbyai-browser-sidecar",
    "private": true,
    "type": "module",
    "scripts": {
        "start": "node server.mjs",
        "test": "node --test"
    },
    "dependencies": {
        "playwright": "^1.50.0"
    }
}
```

```bash
cd browser && npm install && npx playwright install chromium && npm test
```

Expected: FAIL — `Cannot find module './elements.mjs'`.

- [ ] **Step 3: Implement the pure helper**

`browser/elements.mjs`:

```js
const MAX_NAME_LENGTH = 120;

/**
 * Collapse whitespace and truncate an accessible name for prompt use.
 */
export function normalizeName(name) {
    return String(name ?? '')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, MAX_NAME_LENGTH);
}

/**
 * Turn raw in-page element records into the API payload. Refs are assigned in
 * the page itself, so they survive this filtering and stay addressable.
 */
export function formatElements(raw, { max = 60 } = {}) {
    return raw
        .map((element) => ({
            ref: element.ref,
            role: element.role,
            name: normalizeName(element.name),
            state: element.disabled ? { disabled: true } : {},
        }))
        .filter((element) => element.name.length > 0)
        .slice(0, max);
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd browser && npm test`
Expected: PASS (5 tests).

- [ ] **Step 5: Implement session management**

`browser/session.mjs`:

```js
import { chromium } from 'playwright';

const sessions = new Map();

let counter = 0;

/**
 * Launch (or reuse) a persistent browser context for an account.
 */
export async function openSession({ accountId, profileDir, headed = false }) {
    const context = await chromium.launchPersistentContext(profileDir, {
        headless: !headed,
        viewport: { width: 1440, height: 900 },
        args: ['--disable-blink-features=AutomationControlled'],
    });

    const page = context.pages()[0] ?? (await context.newPage());
    const sessionId = `s${++counter}-${accountId}`;

    sessions.set(sessionId, { context, page });

    return { sessionId, url: page.url(), title: await page.title() };
}

/**
 * Get a live session or throw a 404-shaped error.
 */
export function requireSession(sessionId) {
    const session = sessions.get(sessionId);

    if (!session) {
        const error = new Error(`Unknown session [${sessionId}].`);
        error.status = 404;
        throw error;
    }

    return session;
}

/**
 * Close a session, leaving the persistent profile on disk.
 */
export async function closeSession(sessionId) {
    const session = sessions.get(sessionId);

    if (session) {
        await session.context.close();
        sessions.delete(sessionId);
    }
}
```

- [ ] **Step 6: Implement observation with set-of-marks**

`browser/observe.mjs`:

```js
import { formatElements } from './elements.mjs';

const CANDIDATE_SELECTOR = [
    'a[href]',
    'button',
    'input:not([type=hidden])',
    'textarea',
    '[contenteditable="true"]',
    '[role=button]',
    '[role=textbox]',
    '[role=link]',
    '[role=menuitem]',
].join(',');

/**
 * Tag every visible, actionable element with a stable ref and return its record.
 * Runs inside the page; must be self contained.
 */
function collect(selector) {
    const roleOf = (node) => {
        const explicit = node.getAttribute('role');
        if (explicit) return explicit;
        const tag = node.tagName.toLowerCase();
        if (tag === 'a') return 'link';
        if (tag === 'button') return 'button';
        if (tag === 'textarea') return 'textbox';
        if (tag === 'input') return node.type === 'checkbox' ? 'checkbox' : 'textbox';
        if (node.isContentEditable) return 'textbox';
        return 'generic';
    };

    const nameOf = (node) =>
        node.getAttribute('aria-label') ||
        node.getAttribute('placeholder') ||
        node.getAttribute('title') ||
        (node.innerText || '').trim();

    const visible = (node) => {
        const rect = node.getBoundingClientRect();
        const style = window.getComputedStyle(node);
        return (
            rect.width > 4 &&
            rect.height > 4 &&
            rect.bottom > 0 &&
            rect.top < window.innerHeight &&
            style.visibility !== 'hidden' &&
            style.display !== 'none'
        );
    };

    document.querySelectorAll('[data-agent-ref]').forEach((node) => node.removeAttribute('data-agent-ref'));

    const records = [];
    let ref = 0;

    for (const node of document.querySelectorAll(selector)) {
        if (!visible(node)) continue;

        ref += 1;
        node.setAttribute('data-agent-ref', String(ref));

        const rect = node.getBoundingClientRect();

        records.push({
            ref,
            role: roleOf(node),
            name: nameOf(node),
            disabled: node.disabled === true || node.getAttribute('aria-disabled') === 'true',
            box: { x: rect.x, y: rect.y, width: rect.width, height: rect.height },
        });
    }

    return records;
}

/**
 * Draw numbered overlay boxes for the collected elements.
 */
function drawMarks(records) {
    const layer = document.createElement('div');
    layer.id = '__agent_marks__';
    layer.style.cssText = 'position:fixed;inset:0;z-index:2147483647;pointer-events:none;';

    for (const record of records) {
        const box = document.createElement('div');
        box.style.cssText = `position:absolute;left:${record.box.x}px;top:${record.box.y}px;width:${record.box.width}px;height:${record.box.height}px;border:2px solid #e11d48;box-sizing:border-box;`;

        const label = document.createElement('span');
        label.textContent = String(record.ref);
        label.style.cssText =
            'position:absolute;top:-2px;left:-2px;background:#e11d48;color:#fff;font:600 11px/1.2 monospace;padding:1px 3px;';

        box.appendChild(label);
        layer.appendChild(box);
    }

    document.body.appendChild(layer);
}

/**
 * Remove the overlay layer.
 */
function clearMarks() {
    document.getElementById('__agent_marks__')?.remove();
}

/**
 * Produce a full observation: annotated screenshot plus the element list.
 */
export async function observe(page, { marks = true } = {}) {
    const records = await page.evaluate(collect, CANDIDATE_SELECTOR);

    if (marks) {
        await page.evaluate(drawMarks, records);
    }

    const screenshot = await page.screenshot({ type: 'png' });

    if (marks) {
        await page.evaluate(clearMarks);
    }

    return {
        url: page.url(),
        title: await page.title(),
        screenshot: screenshot.toString('base64'),
        elements: formatElements(records),
    };
}
```

- [ ] **Step 7: Implement action execution**

`browser/act.mjs`:

```js
const STEP_TIMEOUT = 20_000;

/**
 * Execute one action against the element carrying its ref.
 */
export async function act(page, { action, ref, text, key }) {
    const target = ref === undefined || ref === null ? null : page.locator(`[data-agent-ref="${ref}"]`);

    switch (action) {
        case 'click':
            await target.click({ timeout: STEP_TIMEOUT });
            break;

        case 'type':
            await target.click({ timeout: STEP_TIMEOUT });
            await target.fill('', { timeout: STEP_TIMEOUT }).catch(() => {});
            await page.keyboard.type(text, { delay: 12 });
            break;

        case 'press':
            await page.keyboard.press(key);
            break;

        case 'scroll':
            await page.mouse.wheel(0, 600);
            break;

        case 'wait':
            await page.waitForTimeout(1_000);
            break;

        default:
            throw Object.assign(new Error(`Unsupported action [${action}].`), { status: 422 });
    }

    await page.waitForTimeout(400);

    return { ok: true, url: page.url(), title: await page.title() };
}
```

- [ ] **Step 8: Implement the HTTP server**

`browser/server.mjs`:

```js
import http from 'node:http';
import { act } from './act.mjs';
import { observe } from './observe.mjs';
import { closeSession, openSession, requireSession } from './session.mjs';

const PORT = Number(process.env.SIDECAR_PORT ?? 4571);

/**
 * Read and parse a JSON request body.
 */
async function body(request) {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    return chunks.length ? JSON.parse(Buffer.concat(chunks).toString()) : {};
}

/**
 * Write a JSON response.
 */
function json(response, status, payload) {
    response.writeHead(status, { 'content-type': 'application/json' });
    response.end(payload === undefined ? '' : JSON.stringify(payload));
}

const server = http.createServer(async (request, response) => {
    const url = new URL(request.url, `http://localhost:${PORT}`);
    const segments = url.pathname.split('/').filter(Boolean);

    try {
        if (request.method === 'GET' && url.pathname === '/health') {
            return json(response, 200, { ok: true, chromium: true });
        }

        if (request.method === 'POST' && url.pathname === '/sessions') {
            return json(response, 201, await openSession(await body(request)));
        }

        if (segments[0] === 'sessions' && segments[1]) {
            const sessionId = segments[1];

            if (request.method === 'DELETE' && !segments[2]) {
                await closeSession(sessionId);
                return json(response, 204);
            }

            const { page } = requireSession(sessionId);

            if (request.method === 'GET' && segments[2] === 'observation') {
                return json(response, 200, await observe(page, { marks: url.searchParams.get('marks') !== '0' }));
            }

            if (request.method === 'POST' && segments[2] === 'actions') {
                return json(response, 200, await act(page, await body(request)));
            }

            if (request.method === 'POST' && segments[2] === 'navigate') {
                const { url: target } = await body(request);
                await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 30_000 });
                return json(response, 200, { ok: true, url: page.url(), title: await page.title() });
            }
        }

        return json(response, 404, { ok: false, error: 'Not found.' });
    } catch (error) {
        return json(response, error.status ?? 500, { ok: false, error: error.message });
    }
});

server.listen(PORT, '127.0.0.1', () => {
    console.log(`Browser sidecar listening on http://127.0.0.1:${PORT}`);
});
```

- [ ] **Step 9: Wire the run script and ignores**

Add to the `scripts` block in the root `composer.json`:

```json
"browser": [
    "Composer\\Config::disableProcessTimeout",
    "node browser/server.mjs"
]
```

Append to `.gitignore`:

```
/browser/node_modules
/storage/app/private/browser-profiles
```

- [ ] **Step 10: Smoke test the sidecar by hand**

```bash
composer run browser   # in one terminal
curl -s http://127.0.0.1:4571/health
```

Expected: `{"ok":true,"chromium":true}`.

- [ ] **Step 11: Commit**

```bash
git add browser .gitignore composer.json
git commit -m "feat: add playwright browser sidecar with set-of-marks perception"
```

---

### Task 8: The sidecar browser driver

**Files:**
- Create: `app/Agent/Browser/SidecarBrowserDriver.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind `BrowserDriver`)
- Test: `tests/Feature/Agent/SidecarBrowserDriverTest.php`

**Interfaces:**
- Consumes: the sidecar HTTP contract from Task 7, `BrowserDriver`.
- Produces: a container binding so `app(BrowserDriver::class)` resolves `SidecarBrowserDriver`.

- [ ] **Step 1: Write the failing driver test**

`tests/Feature/Agent/SidecarBrowserDriverTest.php`:

```php
<?php

use App\Agent\Browser\SessionHandle;
use App\Agent\Browser\SidecarBrowserDriver;
use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Action;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

test('the contract resolves to the sidecar driver', function (): void {
    expect(app(BrowserDriver::class))->toBeInstanceOf(SidecarBrowserDriver::class);
});

test('it opens a session on the account profile directory', function (): void {
    Http::fake(['*/sessions' => Http::response(['sessionId' => 's1-9', 'url' => '', 'title' => ''], 201)]);

    $account = SocialAccount::factory()->create();
    $session = app(BrowserDriver::class)->openSession($account, headed: true);

    expect($session->id)->toBe('s1-9');

    Http::assertSent(fn ($request): bool => $request['profileDir'] === $account->profileDirectory()
        && $request['headed'] === true
        && $request['accountId'] === $account->id);
});

test('it maps an observation response into the value object', function (): void {
    Http::fake(['*/observation*' => Http::response([
        'url' => 'https://www.linkedin.com/feed/',
        'title' => 'Feed | LinkedIn',
        'screenshot' => 'ZmFrZQ==',
        'elements' => [['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []]],
    ])]);

    $observation = app(BrowserDriver::class)->observe(new SessionHandle('s1'));

    expect($observation->host())->toBe('www.linkedin.com')
        ->and($observation->element(7)->name)->toBe('Start a post')
        ->and($observation->screenshot)->toBe('ZmFrZQ==');
});

test('it posts the action payload and reports success', function (): void {
    Http::fake(['*/actions' => Http::response(['ok' => true, 'url' => '', 'title' => ''])]);

    $result = app(BrowserDriver::class)->act(
        new SessionHandle('s1'),
        Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']),
    );

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request): bool => $request['action'] === 'click' && $request['ref'] === 7);
});

test('it converts a sidecar error into a failed result', function (): void {
    Http::fake(['*/actions' => Http::response(['ok' => false, 'error' => 'element detached'], 500)]);

    $result = app(BrowserDriver::class)->act(
        new SessionHandle('s1'),
        Action::fromArray(['action' => 'click', 'ref' => 7, 'why' => 'open']),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toBe('element detached');
});

test('it converts an unreachable sidecar into a failed result', function (): void {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('Connection refused'));

    $result = app(BrowserDriver::class)->act(
        new SessionHandle('s1'),
        Action::fromArray(['action' => 'wait', 'why' => 'settle']),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('Connection refused');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=SidecarBrowserDriverTest`
Expected: FAIL — `Target [App\Agent\Contracts\BrowserDriver] is not instantiable.`

- [ ] **Step 3: Implement the driver**

`app/Agent/Browser/SidecarBrowserDriver.php`:

```php
<?php

namespace App\Agent\Browser;

use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Action;
use App\Agent\Values\ActionResult;
use App\Agent\Values\Observation;
use App\Models\SocialAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Speaks the browser sidecar's HTTP contract. All failures are returned as
 * failed results rather than thrown, so the runner can record them as steps.
 */
final class SidecarBrowserDriver implements BrowserDriver
{
    public function openSession(SocialAccount $account, bool $headed = false): SessionHandle
    {
        $response = $this->request()->post('/sessions', [
            'accountId' => $account->id,
            'profileDir' => $account->profileDirectory(),
            'headed' => $headed,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('The browser sidecar could not open a session: '.$response->json('error', $response->status()));
        }

        return new SessionHandle($response->json('sessionId'));
    }

    public function observe(SessionHandle $session): Observation
    {
        $response = $this->request()->get('/sessions/'.$session->id.'/observation', [
            'marks' => config('agent.perception') === 'marks' ? 1 : 0,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('The browser sidecar could not observe the page: '.$response->json('error', $response->status()));
        }

        return Observation::fromArray($response->json());
    }

    public function act(SessionHandle $session, Action $action): ActionResult
    {
        try {
            $response = $this->request()->post('/sessions/'.$session->id.'/actions', $action->toArray());
        } catch (ConnectionException $exception) {
            return ActionResult::failed($exception->getMessage());
        }

        return $response->successful()
            ? ActionResult::ok()
            : ActionResult::failed((string) $response->json('error', 'sidecar returned '.$response->status()));
    }

    public function navigate(SessionHandle $session, string $url): ActionResult
    {
        try {
            $response = $this->request()->post('/sessions/'.$session->id.'/navigate', ['url' => $url]);
        } catch (ConnectionException $exception) {
            return ActionResult::failed($exception->getMessage());
        }

        return $response->successful()
            ? ActionResult::ok()
            : ActionResult::failed((string) $response->json('error', 'sidecar returned '.$response->status()));
    }

    public function closeSession(SessionHandle $session): void
    {
        rescue(fn () => $this->request()->delete('/sessions/'.$session->id), report: false);
    }

    /**
     * Build a request bound to the configured sidecar.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl(config('agent.sidecar_url'))
            ->timeout(config('agent.browser_timeout') + 15)
            ->acceptJson()
            ->asJson();
    }
}
```

- [ ] **Step 4: Bind the contract**

In `app/Providers/AppServiceProvider.php`, inside `register()`:

```php
$this->app->bind(
    \App\Agent\Contracts\BrowserDriver::class,
    \App\Agent\Browser\SidecarBrowserDriver::class,
);
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=SidecarBrowserDriverTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Agent/Browser/SidecarBrowserDriver.php app/Providers/AppServiceProvider.php tests/Feature/Agent/SidecarBrowserDriverTest.php
git commit -m "feat: add http driver for the browser sidecar"
```

---

### Task 9: Action planner and publish verifier

**Files:**
- Create: `app/Ai/Agents/ActionPlannerAgent.php`, `app/Ai/Agents/PublishVerifierAgent.php`
- Create: `app/Agent/ActionPlanner.php`
- Test: `tests/Feature/Agent/ActionPlannerTest.php`

**Interfaces:**
- Consumes: `laravel/ai`, `Observation`, `RunContract`, `Action`, `PlannerFailed`.
- Produces:
  - `ActionPlanner::next(Observation $observation, RunContract $contract, array $history): Action` — `$history` is a `list<string>` of past step descriptions. Throws `PlannerFailed` after one retry.
  - `ActionPlanner::verify(Observation $observation, RunContract $contract): array{published: bool, evidence: string, url: string|null}`.

- [ ] **Step 1: Generate the agents**

```bash
php artisan make:agent ActionPlannerAgent --structured --no-interaction
php artisan make:agent PublishVerifierAgent --structured --no-interaction
```

Move them to `app/Ai/Agents/` if the generator places them elsewhere, updating the namespace to `App\Ai\Agents`.

- [ ] **Step 2: Write the failing planner test**

`tests/Feature/Agent/ActionPlannerTest.php`:

```php
<?php

use App\Agent\ActionPlanner;
use App\Agent\Exceptions\PlannerFailed;
use App\Agent\Values\Observation;
use App\Agent\Values\RunContract;
use App\Ai\Agents\ActionPlannerAgent;
use App\Ai\Agents\PublishVerifierAgent;
use App\Enums\ActionType;

function planningObservation(): Observation
{
    return Observation::fromArray([
        'url' => 'https://www.linkedin.com/feed/',
        'title' => 'Feed | LinkedIn',
        'screenshot' => base64_encode('png-bytes'),
        'elements' => [['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []]],
    ]);
}

function planningContract(): RunContract
{
    return new RunContract('www.linkedin.com', 'Approved copy.', ['button', 'textbox', 'link'], 18);
}

test('it turns model output into an action', function (): void {
    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 7, 'text' => null, 'key' => null, 'why' => 'the composer is not open'],
    ]);

    $action = app(ActionPlanner::class)->next(planningObservation(), planningContract(), []);

    expect($action->type)->toBe(ActionType::Click)
        ->and($action->ref)->toBe(7)
        ->and($action->why)->toBe('the composer is not open');
});

test('it sends the element list and the step history in the prompt', function (): void {
    ActionPlannerAgent::fake([
        ['action' => 'wait', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'page is loading'],
    ]);

    app(ActionPlanner::class)->next(planningObservation(), planningContract(), ['1. click #7 -> ok']);

    ActionPlannerAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, '[7] button "Start a post"')
        && str_contains($prompt->prompt, '1. click #7 -> ok'));
});

test('it retries once when the model returns an unusable action', function (): void {
    ActionPlannerAgent::fake([
        ['action' => 'teleport', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'nonsense'],
        ['action' => 'wait', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'settle'],
    ]);

    $action = app(ActionPlanner::class)->next(planningObservation(), planningContract(), []);

    expect($action->type)->toBe(ActionType::Wait);
});

test('it fails the run when both attempts are unusable', function (): void {
    ActionPlannerAgent::fake([
        ['action' => 'teleport', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'nonsense'],
        ['action' => 'teleport', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'still nonsense'],
    ]);

    app(ActionPlanner::class)->next(planningObservation(), planningContract(), []);
})->throws(PlannerFailed::class);

test('it verifies whether the post went live', function (): void {
    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'The post appears at the top of the feed.', 'url' => 'https://www.linkedin.com/feed/update/123'],
    ]);

    $verification = app(ActionPlanner::class)->verify(planningObservation(), planningContract());

    expect($verification['published'])->toBeTrue()
        ->and($verification['url'])->toBe('https://www.linkedin.com/feed/update/123');
});

test('it omits the screenshot attachment in text perception mode', function (): void {
    config()->set('agent.perception', 'text');

    ActionPlannerAgent::fake([
        ['action' => 'wait', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'settle'],
    ]);

    app(ActionPlanner::class)->next(planningObservation(), planningContract(), []);

    ActionPlannerAgent::assertPrompted(fn ($prompt): bool => $prompt->attachments === []);
});
```

- [ ] **Step 3: Run it and watch it fail**

Run: `php artisan test --compact --filter=ActionPlannerTest`
Expected: FAIL — `Class "App\Agent\ActionPlanner" not found`.

- [ ] **Step 4: Implement the planner agent**

`app/Ai/Agents/ActionPlannerAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class ActionPlannerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You operate a web browser on LinkedIn to publish one already approved post.

        You are shown a screenshot with numbered boxes drawn over the interactive
        elements, and a matching list of those elements. Choose exactly ONE next
        action and refer to elements only by their number.

        Rules you must obey:
        - Never invent a number that is not in the element list.
        - Type only the approved text you are given, character for character.
        - Never like, follow, connect, comment, reply, message, share or repost.
        - Never navigate away from linkedin.com.
        - Choose "finish" once the post has been submitted.
        - Choose "abort" if you are blocked, see a login wall, or are unsure.

        The usual sequence is: open the post composer, type the approved text,
        click the button that submits the post, then finish.
        TEXT;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['click', 'type', 'press', 'scroll', 'wait', 'finish', 'abort'])
                ->description('The single next action to perform.')
                ->required(),
            'ref' => $schema->integer()
                ->description('The number of the target element, for click and type.'),
            'text' => $schema->string()
                ->description('The text to type. Must equal the approved text exactly.'),
            'key' => $schema->string()
                ->description('The key to press, for the press action.'),
            'why' => $schema->string()
                ->description('One sentence explaining the choice.')
                ->required(),
        ];
    }
}
```

`app/Ai/Agents/PublishVerifierAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class PublishVerifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You are shown the state of a LinkedIn page immediately after a post was
        submitted. Decide whether the post is actually live.

        Only answer published=true if you can point to concrete evidence, such as
        the post visible in the feed or a confirmation message. If the composer is
        still open, an error is shown, or you cannot tell, answer published=false
        and say what you saw. Never guess.
        TEXT;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'published' => $schema->boolean()
                ->description('Whether the post is confirmed live.')
                ->required(),
            'evidence' => $schema->string()
                ->description('What on the page supports the answer.')
                ->required(),
            'url' => $schema->string()
                ->description('The permalink of the published post, if visible.'),
        ];
    }
}
```

- [ ] **Step 5: Implement the planner service**

`app/Agent/ActionPlanner.php`:

```php
<?php

namespace App\Agent;

use App\Agent\Exceptions\PlannerFailed;
use App\Agent\Values\Action;
use App\Agent\Values\Observation;
use App\Agent\Values\RunContract;
use App\Ai\Agents\ActionPlannerAgent;
use App\Ai\Agents\PublishVerifierAgent;
use InvalidArgumentException;
use Laravel\Ai\Files\Base64Image;

/**
 * Turns an observation into the next proposed action, and decides whether a
 * submitted post actually went live.
 */
final class ActionPlanner
{
    public function __construct(
        private readonly ActionPlannerAgent $planner,
        private readonly PublishVerifierAgent $verifier,
    ) {}

    /**
     * Ask the model for the next action, retrying once if the answer is unusable.
     *
     * @param  list<string>  $history
     *
     * @throws PlannerFailed
     */
    public function next(Observation $observation, RunContract $contract, array $history): Action
    {
        $prompt = $this->planningPrompt($observation, $contract, $history);

        foreach ([$prompt, $prompt."\n\nYour previous answer was rejected. Answer with one valid action from the list of allowed actions, using only element numbers that appear above."] as $attempt) {
            try {
                return Action::fromArray($this->prompt($this->planner, $attempt, $observation)->toArray());
            } catch (InvalidArgumentException $exception) {
                $failure = $exception;
            }
        }

        throw new PlannerFailed('The planner returned an unusable action twice: '.$failure->getMessage());
    }

    /**
     * Ask the model whether the post is live.
     *
     * @return array{published: bool, evidence: string, url: string|null}
     */
    public function verify(Observation $observation, RunContract $contract): array
    {
        $response = $this->prompt($this->verifier, implode("\n\n", [
            'The approved post text was:',
            $contract->approvedBody,
            'The page now shows:',
            $observation->toPromptText(),
        ]), $observation)->toArray();

        return [
            'published' => (bool) ($response['published'] ?? false),
            'evidence' => (string) ($response['evidence'] ?? ''),
            'url' => $response['url'] ?? null,
        ];
    }

    /**
     * Build the planning prompt from the observation, contract and history.
     *
     * @param  list<string>  $history
     */
    private function planningPrompt(Observation $observation, RunContract $contract, array $history): string
    {
        return implode("\n\n", array_filter([
            'GOAL: publish this exact text as a new LinkedIn post:',
            $contract->approvedBody,
            $history === [] ? null : "STEPS SO FAR:\n".implode("\n", $history),
            'CURRENT PAGE:',
            $observation->toPromptText(),
            'What is the single next action?',
        ]));
    }

    /**
     * Prompt an agent, attaching the screenshot when perception allows it.
     */
    private function prompt(object $agent, string $prompt, Observation $observation): object
    {
        $attachments = config('agent.perception') === 'marks' && $observation->screenshot !== null
            ? [new Base64Image($observation->screenshot, 'image/png')]
            : [];

        return $agent->prompt(
            $prompt,
            attachments: $attachments,
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ActionPlannerTest`
Expected: PASS (6 tests). If the `JsonSchema` builder methods differ in the installed release, adjust the call to match the package and keep the assertions.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai app/Agent/ActionPlanner.php tests/Feature/Agent/ActionPlannerTest.php
git commit -m "feat: add action planner and publish verifier agents"
```

---

### Task 10: The publish runner and its job

**Files:**
- Create: `app/Agent/PublishRunner.php`, `app/Jobs/PublishPostRun.php`
- Test: `tests/Feature/Agent/PublishRunnerTest.php`

**Interfaces:**
- Consumes: `BrowserDriver`, `ActionPlanner`, `PublishPolicy`, `AgentRun`, `AgentStep`, `Post`.
- Produces:
  - `PublishRunner::run(AgentRun $run): AgentRun` — drives the loop, writes every step, and leaves the run and its post in a terminal state.
  - `PublishPostRun::dispatch(AgentRun $run)` — queued entry point, `tries = 1`, `timeout = config('agent.run_timeout')`.

- [ ] **Step 1: Write the failing runner test**

`tests/Feature/Agent/PublishRunnerTest.php`:

```php
<?php

use App\Agent\Browser\FakeBrowserDriver;
use App\Agent\Contracts\BrowserDriver;
use App\Agent\PublishRunner;
use App\Agent\Values\Observation;
use App\Ai\Agents\ActionPlannerAgent;
use App\Ai\Agents\PublishVerifierAgent;
use App\Enums\GateVerdict;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\Post;
use App\Models\SocialAccount;

const RUNNER_BODY = 'We built Growth Savings because members asked for it.';

function composerPage(bool $composerOpen = false, bool $published = false): Observation
{
    $elements = [['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []]];

    if ($composerOpen) {
        $elements[] = ['ref' => 12, 'role' => 'textbox', 'name' => 'What do you want to talk about?', 'state' => []];
        $elements[] = ['ref' => 19, 'role' => 'button', 'name' => 'Post', 'state' => []];
    }

    return Observation::fromArray([
        'url' => 'https://www.linkedin.com/feed/',
        'title' => $published ? 'Feed | LinkedIn' : 'Feed | LinkedIn',
        'screenshot' => base64_encode('png'),
        'elements' => $elements,
    ]);
}

function runnableRun(): AgentRun
{
    $account = SocialAccount::factory()->connected()->create();

    $post = Post::factory()->approved(RUNNER_BODY)->create([
        'team_id' => $account->team_id,
        'social_account_id' => $account->id,
        'status' => PostStatus::Publishing,
    ]);

    return AgentRun::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'contract' => [
            'allowed_host' => 'www.linkedin.com',
            'approved_body' => RUNNER_BODY,
            'allowed_roles' => ['button', 'textbox', 'link'],
            'step_budget' => 18,
        ],
    ]);
}

function fakeDriver(): FakeBrowserDriver
{
    $driver = (new FakeBrowserDriver)->script([
        composerPage(),
        composerPage(composerOpen: true),
        composerPage(composerOpen: true),
        composerPage(published: true),
        composerPage(published: true),
    ]);

    app()->instance(BrowserDriver::class, $driver);

    return $driver;
}

test('it publishes a post through the full loop', function (): void {
    $driver = fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 7, 'text' => null, 'key' => null, 'why' => 'open the composer'],
        ['action' => 'type', 'ref' => 12, 'text' => RUNNER_BODY, 'key' => null, 'why' => 'write the approved copy'],
        ['action' => 'click', 'ref' => 19, 'text' => null, 'key' => null, 'why' => 'submit the post'],
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'submitted'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'The post is at the top of the feed.', 'url' => 'https://www.linkedin.com/feed/update/123'],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($run->status)->toBe(RunStatus::Published)
        ->and($run->post->fresh()->status)->toBe(PostStatus::Published)
        ->and($run->post->fresh()->published_url)->toBe('https://www.linkedin.com/feed/update/123')
        ->and($driver->actions())->toHaveCount(3)
        ->and($driver->wasClosed())->toBeTrue();
});

test('it records a step for every phase with screenshots and reasoning', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'nothing to do'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'Visible in feed.', 'url' => null],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    $step = $run->steps()->first();

    expect($run->steps()->count())->toBeGreaterThan(0)
        ->and($step->reasoning)->toBe('nothing to do')
        ->and($step->screenshot_path)->not->toBeNull()
        ->and($step->latency_ms)->not->toBeNull();
});

test('it blocks an off contract action and records the denial', function (): void {
    $driver = fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'type', 'ref' => 12, 'text' => RUNNER_BODY.' #growth', 'key' => null, 'why' => 'add a hashtag'],
        ['action' => 'abort', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'blocked'],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($driver->actions())->toBeEmpty()
        ->and($run->steps()->where('gate_decision', GateVerdict::Denied)->count())->toBe(1)
        ->and($run->status)->toBe(RunStatus::Aborted)
        ->and($run->post->fresh()->status)->toBe(PostStatus::Failed);
});

test('it aborts after two identical denials', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 404, 'text' => null, 'key' => null, 'why' => 'stale ref'],
        ['action' => 'click', 'ref' => 404, 'text' => null, 'key' => null, 'why' => 'stale ref again'],
        ['action' => 'click', 'ref' => 404, 'text' => null, 'key' => null, 'why' => 'and again'],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toContain('denied');
});

test('it fails the run when the step budget is exhausted', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake(fn (): array => [
        'action' => 'scroll', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'looking around',
    ]);

    $run = runnableRun();
    $run->update(['contract' => [...$run->contract, 'step_budget' => 3]]);

    $run = app(PublishRunner::class)->run($run);

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toContain('budget');
});

test('it fails the run as unverified when the post cannot be confirmed', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'submitted'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => false, 'evidence' => 'The composer is still open.', 'url' => null],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toContain('unverified')
        ->and($run->post->fresh()->status)->toBe(PostStatus::Failed);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=PublishRunnerTest`
Expected: FAIL — `Class "App\Agent\PublishRunner" not found`.

- [ ] **Step 3: Implement the runner**

`app/Agent/PublishRunner.php`:

```php
<?php

namespace App\Agent;

use App\Agent\Browser\SessionHandle;
use App\Agent\Contracts\BrowserDriver;
use App\Agent\Exceptions\PlannerFailed;
use App\Agent\Values\Action;
use App\Agent\Values\Observation;
use App\Agent\Values\RunContract;
use App\Enums\ActionType;
use App\Enums\GateVerdict;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Enums\StepPhase;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drives one publishing run: observe, think, gate, act, record — one action at
 * a time — until the post is verified live or the run terminates.
 */
final class PublishRunner
{
    private int $stepIndex = 0;

    /** @var list<string> */
    private array $history = [];

    private ?string $lastDenial = null;

    private int $repeatedDenials = 0;

    public function __construct(
        private readonly BrowserDriver $browser,
        private readonly ActionPlanner $planner,
        private readonly PublishPolicy $policy,
    ) {}

    /**
     * Execute the run to completion.
     */
    public function run(AgentRun $run): AgentRun
    {
        $contract = RunContract::fromArray($run->contract);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        $session = null;

        try {
            $session = $this->browser->openSession($run->account);
            $this->browser->navigate($session, config('agent.linkedin.feed_url'));

            $this->loop($run, $contract, $session);
        } catch (PlannerFailed $exception) {
            $this->fail($run, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->fail($run, $exception->getMessage());
        } finally {
            if ($session instanceof SessionHandle) {
                $this->browser->closeSession($session);
            }
        }

        return $run->refresh();
    }

    /**
     * Run the observe → think → gate → act cycle until termination.
     */
    private function loop(AgentRun $run, RunContract $contract, SessionHandle $session): void
    {
        while (true) {
            if ($this->stepIndex >= $contract->stepBudget) {
                $this->fail($run, 'step budget of '.$contract->stepBudget.' exhausted');

                return;
            }

            $observation = $this->browser->observe($session);

            $startedAt = hrtime(true);
            $action = $this->planner->next($observation, $contract, $this->history);
            $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            $decision = $this->policy->evaluate($action, $observation, $contract, $this->stepIndex);

            if (! $decision->isAllowed()) {
                $this->record($run, StepPhase::Gate, $observation, $action, $latency, GateVerdict::Denied, $decision->reason);
                $this->history[] = ($this->stepIndex).'. '.$action->describe().' -> denied: '.$decision->reason;

                if ($this->tooManyDenials($decision->reason)) {
                    $this->fail($run, 'the same action was denied twice: '.$decision->reason);

                    return;
                }

                continue;
            }

            if ($action->type === ActionType::Abort) {
                $this->record($run, StepPhase::Act, $observation, $action, $latency, GateVerdict::Allowed);
                $this->abort($run, $action->why);

                return;
            }

            if ($action->type === ActionType::Finish) {
                $this->record($run, StepPhase::Act, $observation, $action, $latency, GateVerdict::Allowed);
                $this->finish($run, $contract, $session);

                return;
            }

            $result = $this->browser->act($session, $action);

            $this->record($run, StepPhase::Act, $observation, $action, $latency, GateVerdict::Allowed, error: $result->error);

            $this->history[] = ($this->stepIndex).'. '.$action->describe().' -> '.($result->ok ? 'ok' : 'error: '.$result->error);
        }
    }

    /**
     * Observe once more and ask the verifier whether the post is live.
     */
    private function finish(AgentRun $run, RunContract $contract, SessionHandle $session): void
    {
        $observation = $this->browser->observe($session);
        $verification = $this->planner->verify($observation, $contract);

        $this->record(
            $run,
            StepPhase::Verify,
            $observation,
            null,
            null,
            GateVerdict::Allowed,
            reasoning: $verification['evidence'],
        );

        if (! $verification['published']) {
            $this->fail($run, 'unverified: '.$verification['evidence']);

            return;
        }

        $run->forceFill(['status' => RunStatus::Published, 'finished_at' => now(), 'step_count' => $this->stepIndex])->save();

        $run->post->forceFill([
            'status' => PostStatus::Published,
            'published_url' => $verification['url'],
            'published_at' => now(),
        ])->save();
    }

    /**
     * Record a step, persisting the screenshot that produced it.
     */
    private function record(
        AgentRun $run,
        StepPhase $phase,
        Observation $observation,
        ?Action $action,
        ?int $latency,
        GateVerdict $verdict,
        ?string $gateReason = null,
        ?string $error = null,
        ?string $reasoning = null,
    ): void {
        $run->steps()->create([
            'index' => $this->stepIndex++,
            'phase' => $phase,
            'action' => $action?->toArray(),
            'reasoning' => $reasoning ?? $action?->why,
            'ref' => $action?->ref,
            'screenshot_path' => $this->storeScreenshot($run, $observation),
            'gate_decision' => $verdict,
            'gate_reason' => $gateReason,
            'latency_ms' => $latency,
            'error' => $error,
        ]);

        $run->forceFill(['step_count' => $this->stepIndex])->save();
    }

    /**
     * Persist the observation screenshot to the private disk.
     */
    private function storeScreenshot(AgentRun $run, Observation $observation): ?string
    {
        if ($observation->screenshot === null) {
            return null;
        }

        $path = 'agent-screenshots/'.$run->id.'/'.Str::padLeft((string) $this->stepIndex, 3, '0').'.png';

        Storage::disk(config('agent.screenshot_disk'))->put($path, base64_decode($observation->screenshot));

        return $path;
    }

    /**
     * Track consecutive identical denials.
     */
    private function tooManyDenials(string $reason): bool
    {
        $this->repeatedDenials = $reason === $this->lastDenial ? $this->repeatedDenials + 1 : 0;
        $this->lastDenial = $reason;

        return $this->repeatedDenials >= 1;
    }

    /**
     * Mark the run and its post as failed.
     */
    private function fail(AgentRun $run, string $reason): void
    {
        $run->forceFill([
            'status' => RunStatus::Failed,
            'failure_reason' => $reason,
            'finished_at' => now(),
            'step_count' => $this->stepIndex,
        ])->save();

        $run->post->forceFill(['status' => PostStatus::Failed])->save();
    }

    /**
     * Mark the run as deliberately aborted.
     */
    private function abort(AgentRun $run, string $reason): void
    {
        $run->forceFill([
            'status' => RunStatus::Aborted,
            'failure_reason' => $reason,
            'finished_at' => now(),
            'step_count' => $this->stepIndex,
        ])->save();

        $run->post->forceFill(['status' => PostStatus::Failed])->save();
    }
}
```

- [ ] **Step 4: Implement the job**

`app/Jobs/PublishPostRun.php`:

```php
<?php

namespace App\Jobs;

use App\Agent\PublishRunner;
use App\Models\AgentRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class PublishPostRun implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(public AgentRun $run) {}

    /**
     * Execute the publishing run.
     */
    public function handle(PublishRunner $runner): void
    {
        $runner->run($this->run);
    }

    /**
     * Ensure only one run is active per social account.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->run->social_account_id))->dontRelease()];
    }

    /**
     * Get the number of seconds the job may run for.
     */
    public function timeout(): int
    {
        return config('agent.run_timeout');
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PublishRunnerTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Agent/PublishRunner.php app/Jobs/PublishPostRun.php tests/Feature/Agent/PublishRunnerTest.php
git commit -m "feat: add the gated publish runner state machine"
```

---

### Task 11: The copywriter

**Files:**
- Create: `app/Ai/Agents/CopywriterAgent.php`, `app/Agent/Copywriter.php`
- Test: `tests/Feature/Agent/CopywriterTest.php`

**Interfaces:**
- Consumes: `laravel/ai`, `BrandProfile`, `Post`, `PostDraft`.
- Produces: `Copywriter::draftsFor(Post $post, string $brief, ?BrandProfile $brand, ?string $revisionOf = null): Collection<PostDraft>` — persists three drafts (variants `A`, `B`, `C`) and regenerates once if any contains a banned term. Also `Copywriter::bannedTermsIn(string $body, array $terms): array`.

- [ ] **Step 1: Write the failing copywriter test**

`tests/Feature/Agent/CopywriterTest.php`:

```php
<?php

use App\Agent\Copywriter;
use App\Ai\Agents\CopywriterAgent;
use App\Models\BrandProfile;
use App\Models\Post;

function draftPayload(string $suffix = ''): array
{
    return ['drafts' => [
        ['body' => 'Hook led draft.'.$suffix, 'rationale' => 'Opens with a hook.'],
        ['body' => 'Story led draft.'.$suffix, 'rationale' => 'Opens with a member story.'],
        ['body' => 'Question led draft.'.$suffix, 'rationale' => 'Opens with a question.'],
    ]];
}

test('it persists three drafts with character counts', function (): void {
    CopywriterAgent::fake([draftPayload()]);

    $post = Post::factory()->create();

    $drafts = app(Copywriter::class)->draftsFor($post, $post->brief, null);

    expect($drafts)->toHaveCount(3)
        ->and($drafts->pluck('variant')->all())->toBe(['A', 'B', 'C'])
        ->and($drafts->first()->character_count)->toBe(mb_strlen('Hook led draft.'))
        ->and($drafts->first()->model)->toBe(config('agent.model'));
});

test('it conditions the prompt on the brand profile', function (): void {
    CopywriterAgent::fake([draftPayload()]);

    $post = Post::factory()->create();
    $brand = BrandProfile::factory()->create([
        'team_id' => $post->team_id,
        'voice' => 'Warm and direct.',
        'banned_terms' => ['synergy'],
    ]);

    app(Copywriter::class)->draftsFor($post, 'Announce Growth Savings.', $brand);

    CopywriterAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'Warm and direct.')
        && str_contains($prompt->prompt, 'synergy')
        && str_contains($prompt->prompt, 'Announce Growth Savings.'));
});

test('it regenerates once when a draft uses a banned term', function (): void {
    CopywriterAgent::fake([
        ['drafts' => [
            ['body' => 'Real synergy here.', 'rationale' => 'Bad.'],
            ['body' => 'Fine copy.', 'rationale' => 'Good.'],
            ['body' => 'Also fine.', 'rationale' => 'Good.'],
        ]],
        draftPayload(' clean'),
    ]);

    $post = Post::factory()->create();
    $brand = BrandProfile::factory()->create(['team_id' => $post->team_id, 'banned_terms' => ['synergy']]);

    $drafts = app(Copywriter::class)->draftsFor($post, $post->brief, $brand);

    expect($drafts->pluck('body')->implode(' '))->not->toContain('synergy');
});

test('it detects banned terms case insensitively on word boundaries', function (): void {
    $copywriter = app(Copywriter::class);

    expect($copywriter->bannedTermsIn('Real Synergy here.', ['synergy']))->toBe(['synergy'])
        ->and($copywriter->bannedTermsIn('Synergistic thinking.', ['synergy']))->toBe([]);
});

test('it replaces previous drafts when revising', function (): void {
    CopywriterAgent::fake([draftPayload(), draftPayload(' v2')]);

    $post = Post::factory()->create();
    $copywriter = app(Copywriter::class);

    $copywriter->draftsFor($post, $post->brief, null);
    $revised = $copywriter->draftsFor($post, 'Make it shorter.', null, revisionOf: 'Hook led draft.');

    expect($post->fresh()->drafts)->toHaveCount(3)
        ->and($revised->first()->body)->toBe('Hook led draft. v2');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=CopywriterTest`
Expected: FAIL — `Class "App\Agent\Copywriter" not found`.

- [ ] **Step 3: Implement the copywriter agent**

`app/Ai/Agents/CopywriterAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class CopywriterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You write LinkedIn posts for a brand. Given a brief and a brand profile,
        return exactly three distinct drafts: one hook led, one story led, and one
        question led.

        Constraints:
        - Plain text only. No markdown, no bullet characters, no emoji spam.
        - Under 1,200 characters each; shorter is usually stronger.
        - At most two hashtags, at the end, only if they earn their place.
        - Never use a banned term you are given.
        - Do not invent facts, statistics, product features or testimonials.
        TEXT;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'drafts' => $schema->array()
                ->items($schema->object([
                    'body' => $schema->string()->description('The post copy.')->required(),
                    'rationale' => $schema->string()->description('One sentence on the angle taken.')->required(),
                ]))
                ->description('Exactly three drafts.')
                ->required(),
        ];
    }
}
```

- [ ] **Step 4: Implement the copywriter service**

`app/Agent/Copywriter.php`:

```php
<?php

namespace App\Agent;

use App\Ai\Agents\CopywriterAgent;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\PostDraft;
use Illuminate\Support\Collection;

/**
 * Generates brand conditioned drafts, enforcing banned terms deterministically
 * rather than trusting the model to remember them.
 */
final class Copywriter
{
    private const VARIANTS = ['A', 'B', 'C'];

    public function __construct(private readonly CopywriterAgent $agent) {}

    /**
     * Generate and persist three drafts for the post, replacing any earlier set.
     *
     * @return Collection<int, PostDraft>
     */
    public function draftsFor(Post $post, string $brief, ?BrandProfile $brand, ?string $revisionOf = null): Collection
    {
        $banned = $brand?->banned_terms ?? [];

        $drafts = $this->generate($brief, $brand, $revisionOf);

        if ($this->containsBannedTerm($drafts, $banned)) {
            $drafts = $this->generate($brief, $brand, $revisionOf, avoid: $banned);
        }

        $post->drafts()->delete();

        return (new Collection($drafts))
            ->take(count(self::VARIANTS))
            ->values()
            ->map(fn (array $draft, int $index): PostDraft => $post->drafts()->create([
                'variant' => self::VARIANTS[$index],
                'body' => $draft['body'],
                'rationale' => $draft['rationale'] ?? null,
                'character_count' => mb_strlen($draft['body']),
                'model' => config('agent.model'),
            ]));
    }

    /**
     * Find which of the given banned terms appear in the body.
     *
     * @param  string[]  $terms
     * @return string[]
     */
    public function bannedTermsIn(string $body, array $terms): array
    {
        return array_values(array_filter(
            $terms,
            fn (string $term): bool => preg_match('/\b'.preg_quote($term, '/').'\b/i', $body) === 1,
        ));
    }

    /**
     * Prompt the model for a set of drafts.
     *
     * @param  string[]  $avoid
     * @return array<int, array{body: string, rationale?: string}>
     */
    private function generate(string $brief, ?BrandProfile $brand, ?string $revisionOf, array $avoid = []): array
    {
        $response = $this->agent->prompt(
            $this->prompt($brief, $brand, $revisionOf, $avoid),
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );

        return $response->toArray()['drafts'] ?? [];
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

    /**
     * Determine whether any draft uses a banned term.
     *
     * @param  array<int, array{body: string}>  $drafts
     * @param  string[]  $banned
     */
    private function containsBannedTerm(array $drafts, array $banned): bool
    {
        foreach ($drafts as $draft) {
            if ($this->bannedTermsIn($draft['body'], $banned) !== []) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=CopywriterTest`
Expected: PASS (5 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Agents/CopywriterAgent.php app/Agent/Copywriter.php tests/Feature/Agent/CopywriterTest.php
git commit -m "feat: add brand conditioned copywriter with banned term enforcement"
```

---

### Task 12: Post lifecycle backend

**Files:**
- Create: `app/Http/Controllers/Agent/{PostController,PostMessageController,PostApprovalController,RunController}.php`
- Create: `app/Http/Requests/Agent/{StorePostRequest,StorePostMessageRequest,ApprovePostRequest}.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Agent/PostLifecycleTest.php`

**Interfaces:**
- Consumes: `Copywriter`, `PublishPostRun`, `RunContract`, models from Tasks 2–3.
- Produces the named routes used by every UI task: `studio`, `posts.store`, `posts.show`, `posts.messages.store`, `posts.approve`, `runs.index`, `runs.show`, `runs.abort`, `accounts.index`, `accounts.connect`, `accounts.verify`, `brand.edit`, `brand.update`.

- [ ] **Step 1: Write the failing lifecycle test**

`tests/Feature/Agent/PostLifecycleTest.php`:

```php
<?php

use App\Ai\Agents\CopywriterAgent;
use App\Enums\MessageRole;
use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Enums\TeamRole;
use App\Jobs\PublishPostRun;
use App\Models\AgentRun;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function teamUser(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->forceFill(['current_team_id' => $team->id])->save();

    return [$user, $team];
}

function fakeDrafts(): void
{
    CopywriterAgent::fake([
        ['drafts' => [
            ['body' => 'Hook led draft.', 'rationale' => 'Hook.'],
            ['body' => 'Story led draft.', 'rationale' => 'Story.'],
            ['body' => 'Question led draft.', 'rationale' => 'Question.'],
        ]],
    ]);
}

test('a brief creates a post with three drafts and a thread', function (): void {
    [$user, $team] = teamUser();
    fakeDrafts();
    $account = SocialAccount::factory()->connected()->create(['team_id' => $team->id]);

    $response = $this->actingAs($user)->post(route('posts.store', $team->slug), [
        'brief' => 'Announce Growth Savings in a warm tone.',
        'social_account_id' => $account->id,
    ]);

    $post = Post::sole();

    $response->assertRedirect(route('posts.show', [$team->slug, $post]));

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->drafts)->toHaveCount(3)
        ->and($post->messages()->where('role', MessageRole::User)->count())->toBe(1)
        ->and($post->messages()->where('role', MessageRole::Agent)->count())->toBe(1);
});

test('a revision request regenerates the drafts', function (): void {
    [$user, $team] = teamUser();
    fakeDrafts();
    $post = Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Draft]);

    $this->actingAs($user)
        ->post(route('posts.messages.store', [$team->slug, $post]), ['body' => 'Make it shorter.'])
        ->assertRedirect();

    expect($post->fresh()->drafts)->toHaveCount(3)
        ->and($post->fresh()->messages()->count())->toBe(2);
});

test('approving snapshots the body and queues a run', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->connected()->create(['team_id' => $team->id]);
    $post = Post::factory()->create([
        'team_id' => $team->id,
        'social_account_id' => $account->id,
        'status' => PostStatus::Draft,
    ]);

    $this->actingAs($user)
        ->post(route('posts.approve', [$team->slug, $post]), ['body' => 'The exact approved copy.'])
        ->assertRedirect();

    $post->refresh();
    $run = AgentRun::sole();

    expect($post->status)->toBe(PostStatus::Publishing)
        ->and($post->approved_body)->toBe('The exact approved copy.')
        ->and($post->approved_by)->toBe($user->id)
        ->and($run->contract['approved_body'])->toBe('The exact approved copy.')
        ->and($run->status)->toBe(RunStatus::Queued);

    Queue::assertPushed(PublishPostRun::class);
});

test('approving is rejected when the account is not connected', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->create(['team_id' => $team->id]);
    $post = Post::factory()->create([
        'team_id' => $team->id,
        'social_account_id' => $account->id,
        'status' => PostStatus::Draft,
    ]);

    $this->actingAs($user)
        ->post(route('posts.approve', [$team->slug, $post]), ['body' => 'Copy.'])
        ->assertSessionHasErrors('social_account_id');

    Queue::assertNothingPushed();
});

test('an active run can be aborted', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Publishing]);
    $run = AgentRun::factory()->create(['post_id' => $post->id, 'status' => RunStatus::Running]);

    $this->actingAs($user)
        ->post(route('runs.abort', [$team->slug, $run]))
        ->assertRedirect();

    expect($run->fresh()->status)->toBe(RunStatus::Aborted)
        ->and($post->fresh()->status)->toBe(PostStatus::Failed);
});

test('posts belonging to another team are not reachable', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create();

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]))
        ->assertNotFound();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=PostLifecycleTest`
Expected: FAIL — `Route [posts.store] not defined.`

- [ ] **Step 3: Create the form requests**

```php
<?php
// app/Http/Requests/Agent/StorePostRequest.php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brief' => ['required', 'string', 'min:8', 'max:2000'],
            'social_account_id' => [
                'required',
                Rule::exists('social_accounts', 'id')->where('team_id', $this->user()->currentTeam->id),
            ],
        ];
    }
}
```

```php
<?php
// app/Http/Requests/Agent/StorePostMessageRequest.php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StorePostMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }
}
```

```php
<?php
// app/Http/Requests/Agent/ApprovePostRequest.php

namespace App\Http\Requests\Agent;

use App\Enums\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApprovePostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ];
    }

    /**
     * Ensure the post's account has a verified browser session.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $account = $this->route('post')->account;

                if (! $account || ! $account->status->isPublishable()) {
                    $validator->errors()->add(
                        'social_account_id',
                        __('Connect the LinkedIn account before publishing.'),
                    );
                }
            },
        ];
    }
}
```

- [ ] **Step 4: Create the controllers**

```php
<?php
// app/Http/Controllers/Agent/PostController.php

namespace App\Http\Controllers\Agent;

use App\Agent\Copywriter;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StorePostRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Create a post from a brief and generate its first drafts.
     */
    public function store(StorePostRequest $request, Copywriter $copywriter): RedirectResponse
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

        $drafts = $copywriter->draftsFor($post, $post->brief, $team->brandProfile);

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Drafts,
            'body' => __(':count drafts', ['count' => $drafts->count()]),
            'payload' => ['draft_ids' => $drafts->pluck('id')->all()],
        ]);

        return to_route('posts.show', [$team->slug, $post]);
    }

    /**
     * Show the post workspace.
     */
    public function show(Request $request, Post $post): Response
    {
        $this->authorizeTeam($request, $post);

        $run = $post->latestRun();

        return Inertia::render('posts/show', [
            'post' => $post->only(['id', 'brief', 'status', 'approved_body', 'published_url']),
            'drafts' => $post->drafts()->get(['id', 'variant', 'body', 'rationale', 'character_count']),
            'messages' => $post->messages()->get(['id', 'role', 'kind', 'body', 'payload', 'created_at']),
            'run' => $run?->only(['id', 'status', 'mode', 'step_count', 'failure_reason']),
            'steps' => Inertia::defer(fn (): array => $run
                ? $run->steps()->get(['id', 'index', 'phase', 'action', 'reasoning', 'gate_decision', 'gate_reason', 'latency_ms', 'error'])->all()
                : []),
        ]);
    }

    /**
     * Abort with a 404 when the record belongs to another team.
     */
    protected function authorizeTeam(Request $request, Post $post): void
    {
        abort_unless($post->team_id === $request->user()->currentTeam?->id, 404);
    }
}
```

```php
<?php
// app/Http/Controllers/Agent/PostMessageController.php

namespace App\Http\Controllers\Agent;

use App\Agent\Copywriter;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StorePostMessageRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class PostMessageController extends Controller
{
    /**
     * Record a revision request and regenerate the drafts.
     */
    public function store(StorePostMessageRequest $request, Post $post, Copywriter $copywriter): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($post->team_id === $team?->id, 404);

        $post->messages()->create([
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => $request->validated('body'),
        ]);

        $drafts = $copywriter->draftsFor(
            $post,
            $request->validated('body'),
            $team->brandProfile,
            revisionOf: $post->drafts()->first()?->body,
        );

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Drafts,
            'body' => __('Updated drafts'),
            'payload' => ['draft_ids' => $drafts->pluck('id')->all()],
        ]);

        return back();
    }
}
```

```php
<?php
// app/Http/Controllers/Agent/PostApprovalController.php

namespace App\Http\Controllers\Agent;

use App\Agent\Values\RunContract;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\PostStatus;
use App\Enums\RunMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ApprovePostRequest;
use App\Jobs\PublishPostRun;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PostApprovalController extends Controller
{
    /**
     * Snapshot the approved copy, build the run contract and queue the run.
     */
    public function store(ApprovePostRequest $request, Post $post): RedirectResponse
    {
        abort_unless($post->team_id === $request->user()->currentTeam?->id, 404);

        $post->forceFill([
            'approved_body' => $request->validated('body'),
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'status' => PostStatus::Publishing,
        ])->save();

        $run = $post->runs()->create([
            'social_account_id' => $post->social_account_id,
            'mode' => RunMode::Autonomous,
            'contract' => RunContract::forPost($post)->toArray(),
        ]);

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Run,
            'body' => __('Publishing started.'),
            'payload' => ['run_id' => $run->id],
        ]);

        PublishPostRun::dispatch($run);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approved. The agent is publishing now.')]);

        return back();
    }
}
```

```php
<?php
// app/Http/Controllers/Agent/RunController.php

namespace App\Http\Controllers\Agent;

use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RunController extends Controller
{
    /**
     * List the team's publishing runs.
     */
    public function index(Request $request): Response
    {
        $runs = AgentRun::query()
            ->whereRelation('post', 'team_id', $request->user()->currentTeam->id)
            ->with('post:id,brief')
            ->latest('id')
            ->paginate(20)
            ->through(fn (AgentRun $run): array => [
                'id' => $run->id,
                'status' => $run->status,
                'stepCount' => $run->step_count,
                'failureReason' => $run->failure_reason,
                'startedAt' => $run->started_at,
                'brief' => $run->post->brief,
            ]);

        return Inertia::render('runs/index', ['runs' => $runs]);
    }

    /**
     * Replay a single run step by step.
     */
    public function show(Request $request, AgentRun $run): Response
    {
        $this->authorizeRun($request, $run);

        return Inertia::render('runs/show', [
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'failureReason' => $run->failure_reason,
                'contract' => $run->contract,
                'postId' => $run->post_id,
            ],
            'steps' => Inertia::defer(fn (): array => $run->steps()->get([
                'id', 'index', 'phase', 'action', 'reasoning', 'gate_decision', 'gate_reason', 'latency_ms', 'error', 'screenshot_path',
            ])->all()),
        ]);
    }

    /**
     * Abort an in flight run.
     */
    public function abort(Request $request, AgentRun $run): RedirectResponse
    {
        $this->authorizeRun($request, $run);

        abort_unless($run->isActive(), 422);

        $run->forceFill([
            'status' => RunStatus::Aborted,
            'failure_reason' => __('Aborted by :name.', ['name' => $request->user()->name]),
            'finished_at' => now(),
        ])->save();

        $run->post->forceFill(['status' => PostStatus::Failed])->save();

        return back();
    }

    /**
     * Abort with a 404 when the run belongs to another team.
     */
    protected function authorizeRun(Request $request, AgentRun $run): void
    {
        abort_unless($run->post->team_id === $request->user()->currentTeam?->id, 404);
    }
}
```

- [ ] **Step 5: Add the team relation used by the controllers**

In `app/Models/Team.php`, add:

```php
/**
 * Get the team's brand profile.
 *
 * @return HasOne<BrandProfile, $this>
 */
public function brandProfile(): HasOne
{
    return $this->hasOne(BrandProfile::class);
}

/**
 * Get the team's social accounts.
 *
 * @return HasMany<SocialAccount, $this>
 */
public function socialAccounts(): HasMany
{
    return $this->hasMany(SocialAccount::class);
}

/**
 * Get the team's posts.
 *
 * @return HasMany<Post, $this>
 */
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, inside the existing `Route::prefix('{current_team}')` group:

```php
Route::get('studio', StudioController::class)->name('studio');

Route::post('posts', [PostController::class, 'store'])->name('posts.store');
Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
Route::post('posts/{post}/messages', [PostMessageController::class, 'store'])->name('posts.messages.store');
Route::post('posts/{post}/approve', [PostApprovalController::class, 'store'])->name('posts.approve');

Route::get('runs', [RunController::class, 'index'])->name('runs.index');
Route::get('runs/{run}', [RunController::class, 'show'])->name('runs.show');
Route::post('runs/{run}/abort', [RunController::class, 'abort'])->name('runs.abort');

Route::get('accounts', [SocialAccountController::class, 'index'])->name('accounts.index');
Route::post('accounts/{account}/connect', [SocialAccountController::class, 'connect'])->name('accounts.connect');
Route::post('accounts/{account}/verify', [SocialAccountController::class, 'verify'])->name('accounts.verify');

Route::get('brand', [BrandProfileController::class, 'edit'])->name('brand.edit');
Route::put('brand', [BrandProfileController::class, 'update'])->name('brand.update');
```

Add the matching `use` statements at the top of the file. `StudioController`, `SocialAccountController` and `BrandProfileController` arrive in Tasks 13–15 — create empty invokable stubs now so the routes resolve, and fill them in there:

```bash
php artisan make:controller Agent/StudioController --invokable --no-interaction
php artisan make:controller Agent/SocialAccountController --no-interaction
php artisan make:controller Agent/BrandProfileController --no-interaction
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PostLifecycleTest`
Expected: PASS (6 tests). The two page-rendering assertions will fail until the pages exist in Tasks 15–16 if `inertia.pages.ensure_pages_exist` is enabled for tests; create empty placeholder page files if so.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http routes/web.php app/Models/Team.php resources/js/actions resources/js/routes tests/Feature/Agent/PostLifecycleTest.php
git commit -m "feat: add post lifecycle routes, controllers and approval gate"
```

---

### Task 13: Brand profile screen

**Files:**
- Modify: `app/Http/Controllers/Agent/BrandProfileController.php`
- Create: `app/Http/Requests/Agent/UpdateBrandProfileRequest.php`
- Create: `resources/js/pages/brand/edit.tsx`, `resources/js/types/agent.ts`
- Test: `tests/Feature/Agent/BrandProfileTest.php`

**Interfaces:**
- Consumes: `BrandProfile`, routes `brand.edit` / `brand.update`.
- Produces: `resources/js/types/agent.ts` exporting `BrandProfileData`, `PostSummary`, `DraftData`, `ThreadMessage`, `RunData`, `StepData`, `AccountData` — imported by every later page.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Agent/BrandProfileTest.php`:

```php
<?php

use App\Models\BrandProfile;
use Inertia\Testing\AssertableInertia as Assert;

test('the brand page renders the current profile', function (): void {
    [$user, $team] = teamUser();
    BrandProfile::factory()->create(['team_id' => $team->id, 'voice' => 'Warm and direct.']);

    $this->actingAs($user)
        ->get(route('brand.edit', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('brand/edit')
            ->where('brand.voice', 'Warm and direct.'));
});

test('the brand profile can be created and updated', function (): void {
    [$user, $team] = teamUser();

    $this->actingAs($user)->put(route('brand.update', $team->slug), [
        'voice' => 'Warm, direct, jargon free.',
        'audience' => 'Members of a savings cooperative.',
        'topics' => ['savings', 'literacy'],
        'banned_terms' => ['synergy'],
        'examples' => ['We built this for members.'],
    ])->assertRedirect();

    $brand = $team->fresh()->brandProfile;

    expect($brand->voice)->toBe('Warm, direct, jargon free.')
        ->and($brand->topics)->toBe(['savings', 'literacy']);
});

test('another team's brand profile is never exposed', function (): void {
    [$user, $team] = teamUser();
    BrandProfile::factory()->create(['voice' => 'Someone else.']);

    $this->actingAs($user)
        ->get(route('brand.edit', $team->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->where('brand.voice', null));
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=BrandProfileTest`
Expected: FAIL — the controller has no `edit` method.

- [ ] **Step 3: Implement the request and controller**

```php
<?php
// app/Http/Requests/Agent/UpdateBrandProfileRequest.php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'voice' => ['nullable', 'string', 'max:1000'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'topics' => ['nullable', 'array', 'max:20'],
            'topics.*' => ['string', 'max:60'],
            'banned_terms' => ['nullable', 'array', 'max:50'],
            'banned_terms.*' => ['string', 'max:60'],
            'examples' => ['nullable', 'array', 'max:10'],
            'examples.*' => ['string', 'max:3000'],
        ];
    }
}
```

```php
<?php
// app/Http/Controllers/Agent/BrandProfileController.php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateBrandProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandProfileController extends Controller
{
    /**
     * Show the team's brand profile.
     */
    public function edit(Request $request): Response
    {
        $brand = $request->user()->currentTeam->brandProfile;

        return Inertia::render('brand/edit', [
            'brand' => [
                'voice' => $brand?->voice,
                'audience' => $brand?->audience,
                'topics' => $brand?->topics ?? [],
                'bannedTerms' => $brand?->banned_terms ?? [],
                'examples' => $brand?->examples ?? [],
            ],
        ]);
    }

    /**
     * Create or update the team's brand profile.
     */
    public function update(UpdateBrandProfileRequest $request): RedirectResponse
    {
        $request->user()->currentTeam->brandProfile()->updateOrCreate([], [
            'voice' => $request->validated('voice'),
            'audience' => $request->validated('audience'),
            'topics' => $request->validated('topics', []),
            'banned_terms' => $request->validated('banned_terms', []),
            'examples' => $request->validated('examples', []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand voice saved.')]);

        return back();
    }
}
```

- [ ] **Step 4: Create the shared frontend types**

`resources/js/types/agent.ts`:

```ts
export type PostStatus =
    | 'brief'
    | 'draft'
    | 'approved'
    | 'publishing'
    | 'published'
    | 'failed';

export type RunStatus = 'queued' | 'running' | 'published' | 'failed' | 'aborted';

export type BrandProfileData = {
    voice: string | null;
    audience: string | null;
    topics: string[];
    bannedTerms: string[];
    examples: string[];
};

export type AccountData = {
    id: number;
    handle: string | null;
    status: 'disconnected' | 'connected' | 'expired';
    lastVerifiedAt: string | null;
};

export type DraftData = {
    id: number;
    variant: string;
    body: string;
    rationale: string | null;
    character_count: number;
};

export type ThreadMessage = {
    id: number;
    role: 'user' | 'agent';
    kind: 'text' | 'drafts' | 'run';
    body: string | null;
    payload: Record<string, unknown> | null;
    created_at: string;
};

export type StepData = {
    id: number;
    index: number;
    phase: 'observe' | 'think' | 'gate' | 'act' | 'verify';
    action: { action: string; ref: number | null; why: string } | null;
    reasoning: string | null;
    gate_decision: 'allowed' | 'denied' | null;
    gate_reason: string | null;
    latency_ms: number | null;
    error: string | null;
    screenshot_path?: string | null;
};

export type RunData = {
    id: number;
    status: RunStatus;
    mode: 'autonomous' | 'step';
    step_count: number;
    failure_reason: string | null;
};

export type PostSummary = {
    id: number;
    brief: string;
    status: PostStatus;
    approved_body: string | null;
    published_url: string | null;
};
```

- [ ] **Step 5: Build the brand page**

`resources/js/pages/brand/edit.tsx`:

```tsx
import { Form, Head } from '@inertiajs/react';
import BrandProfileController from '@/actions/App/Http/Controllers/Agent/BrandProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BrandProfileData } from '@/types/agent';

type Props = {
    brand: BrandProfileData;
};

export default function BrandEdit({ brand }: Props) {
    return (
        <>
            <Head title="Brand voice" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="Brand voice"
                    description="Everything the agent writes is conditioned on this."
                />

                <Form
                    {...BrandProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    transform={(data) => ({
                        ...data,
                        topics: String(data.topics ?? '')
                            .split(',')
                            .map((value) => value.trim())
                            .filter(Boolean),
                        banned_terms: String(data.banned_terms ?? '')
                            .split(',')
                            .map((value) => value.trim())
                            .filter(Boolean),
                        examples: String(data.examples ?? '')
                            .split('\n\n')
                            .map((value) => value.trim())
                            .filter(Boolean),
                    })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="voice">Voice</Label>
                                <textarea
                                    id="voice"
                                    name="voice"
                                    rows={3}
                                    defaultValue={brand.voice ?? ''}
                                    placeholder="Warm, direct, jargon-free."
                                    className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={errors.voice} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="audience">Audience</Label>
                                <Input
                                    id="audience"
                                    name="audience"
                                    defaultValue={brand.audience ?? ''}
                                    placeholder="Members of a savings cooperative."
                                />
                                <InputError message={errors.audience} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="topics">Topics</Label>
                                <Input
                                    id="topics"
                                    name="topics"
                                    defaultValue={brand.topics.join(', ')}
                                    placeholder="savings, financial literacy"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Comma separated.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="banned_terms">Never use</Label>
                                <Input
                                    id="banned_terms"
                                    name="banned_terms"
                                    defaultValue={brand.bannedTerms.join(', ')}
                                    placeholder="synergy, leverage"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Checked in code after every generation, not left to the model.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="examples">Example posts</Label>
                                <textarea
                                    id="examples"
                                    name="examples"
                                    rows={8}
                                    defaultValue={brand.examples.join('\n\n')}
                                    placeholder="Paste posts that sound like you. Separate them with a blank line."
                                    className="min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Save brand voice
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
```

- [ ] **Step 6: Run the test and the frontend gates**

```bash
php artisan test --compact --filter=BrandProfileTest
npm run lint && npm run types:check
```

Expected: 3 tests pass, lint and types clean.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http resources/js tests/Feature/Agent/BrandProfileTest.php
git commit -m "feat: add brand voice profile screen"
```

---

### Task 14: Accounts connection screen

**Files:**
- Modify: `app/Http/Controllers/Agent/SocialAccountController.php`
- Create: `resources/js/pages/accounts/index.tsx`
- Test: `tests/Feature/Agent/AccountsTest.php`

**Interfaces:**
- Consumes: `BrowserDriver` (headed session), `SocialAccount`.
- Produces: `accounts.index`, `accounts.connect` (opens a headed browser for interactive login), `accounts.verify` (observes the feed and flips status).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Agent/AccountsTest.php`:

```php
<?php

use App\Agent\Browser\FakeBrowserDriver;
use App\Agent\Contracts\BrowserDriver;
use App\Agent\Values\Observation;
use App\Enums\AccountStatus;
use App\Models\SocialAccount;
use Inertia\Testing\AssertableInertia as Assert;

function fakeObservation(string $url, array $elements = []): void
{
    app()->instance(BrowserDriver::class, (new FakeBrowserDriver)->script([
        Observation::fromArray(['url' => $url, 'title' => 'LinkedIn', 'elements' => $elements]),
    ]));
}

test('the accounts page lists the team accounts', function (): void {
    [$user, $team] = teamUser();
    SocialAccount::factory()->connected()->create(['team_id' => $team->id, 'handle' => 'zimba']);

    $this->actingAs($user)
        ->get(route('accounts.index', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('accounts/index')
            ->has('accounts', 1)
            ->where('accounts.0.handle', 'zimba'));
});

test('connecting opens a headed browser session', function (): void {
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->create(['team_id' => $team->id]);
    fakeObservation('https://www.linkedin.com/login');

    $this->actingAs($user)
        ->post(route('accounts.connect', [$team->slug, $account]))
        ->assertRedirect();

    expect($account->fresh()->status)->toBe(AccountStatus::Disconnected);
});

test('verifying a signed in session marks the account connected', function (): void {
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->create(['team_id' => $team->id]);
    fakeObservation('https://www.linkedin.com/feed/', [
        ['ref' => 7, 'role' => 'button', 'name' => 'Start a post', 'state' => []],
    ]);

    $this->actingAs($user)
        ->post(route('accounts.verify', [$team->slug, $account]))
        ->assertRedirect();

    expect($account->fresh()->status)->toBe(AccountStatus::Connected)
        ->and($account->fresh()->last_verified_at)->not->toBeNull();
});

test('verifying a logged out session marks the account expired', function (): void {
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->connected()->create(['team_id' => $team->id]);
    fakeObservation('https://www.linkedin.com/login', [
        ['ref' => 1, 'role' => 'textbox', 'name' => 'Email or phone', 'state' => []],
    ]);

    $this->actingAs($user)->post(route('accounts.verify', [$team->slug, $account]));

    expect($account->fresh()->status)->toBe(AccountStatus::Expired);
});

test('accounts from another team are not reachable', function (): void {
    [$user, $team] = teamUser();
    $account = SocialAccount::factory()->create();

    $this->actingAs($user)
        ->post(route('accounts.verify', [$team->slug, $account]))
        ->assertNotFound();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=AccountsTest`
Expected: FAIL — the controller has no `index` method.

- [ ] **Step 3: Implement the controller**

```php
<?php
// app/Http/Controllers/Agent/SocialAccountController.php

namespace App\Http\Controllers\Agent;

use App\Agent\Contracts\BrowserDriver;
use App\Enums\AccountStatus;
use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SocialAccountController extends Controller
{
    /**
     * List the team's social accounts, creating the LinkedIn row on first visit.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        $team->socialAccounts()->firstOrCreate(['platform' => SocialPlatform::LinkedIn]);

        return Inertia::render('accounts/index', [
            'accounts' => $team->socialAccounts()->get()->map(fn (SocialAccount $account): array => [
                'id' => $account->id,
                'handle' => $account->handle,
                'status' => $account->status,
                'lastVerifiedAt' => $account->last_verified_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Open a visible browser on the account profile so the user can sign in.
     */
    public function connect(Request $request, SocialAccount $account, BrowserDriver $browser): RedirectResponse
    {
        $this->authorizeAccount($request, $account);

        try {
            $session = $browser->openSession($account, headed: true);
            $browser->navigate($session, config('agent.linkedin.feed_url'));
        } catch (Throwable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Could not open the browser: :error', ['error' => $exception->getMessage()])]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sign in and clear MFA in the browser window, then press Verify.')]);

        return back();
    }

    /**
     * Observe the feed and record whether the session is signed in.
     */
    public function verify(Request $request, SocialAccount $account, BrowserDriver $browser): RedirectResponse
    {
        $this->authorizeAccount($request, $account);

        try {
            $session = $browser->openSession($account);
            $browser->navigate($session, config('agent.linkedin.feed_url'));
            $observation = $browser->observe($session);
            $browser->closeSession($session);
        } catch (Throwable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $signedIn = str_contains($observation->url, '/feed');

        $account->forceFill([
            'status' => $signedIn ? AccountStatus::Connected : AccountStatus::Expired,
            'last_verified_at' => $signedIn ? now() : $account->last_verified_at,
        ])->save();

        Inertia::flash('toast', $signedIn
            ? ['type' => 'success', 'message' => __('Account connected.')]
            : ['type' => 'error', 'message' => __('Not signed in. Press Connect and complete the login.')]);

        return back();
    }

    /**
     * Abort with a 404 when the account belongs to another team.
     */
    protected function authorizeAccount(Request $request, SocialAccount $account): void
    {
        abort_unless($account->team_id === $request->user()->currentTeam?->id, 404);
    }
}
```

- [ ] **Step 4: Build the accounts page**

`resources/js/pages/accounts/index.tsx`:

```tsx
import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, CircleDashed, TriangleAlert } from 'lucide-react';
import SocialAccountController from '@/actions/App/Http/Controllers/Agent/SocialAccountController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { AccountData } from '@/types/agent';

const STATUS = {
    connected: { label: 'Connected', icon: CheckCircle2, tone: 'text-emerald-600 dark:text-emerald-400' },
    expired: { label: 'Session expired', icon: TriangleAlert, tone: 'text-amber-600 dark:text-amber-400' },
    disconnected: { label: 'Not connected', icon: CircleDashed, tone: 'text-muted-foreground' },
} as const;

type Props = {
    accounts: AccountData[];
};

export default function AccountsIndex({ accounts }: Props) {
    return (
        <>
            <Head title="Accounts" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="Accounts"
                    description="The agent uses a real browser session. You sign in yourself — no password is ever stored here."
                />

                {accounts.map((account) => {
                    const status = STATUS[account.status];
                    const Icon = status.icon;

                    return (
                        <Card key={account.id} className="p-5">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="space-y-1">
                                    <p className="font-medium">LinkedIn</p>
                                    <p className={`flex items-center gap-1.5 text-sm ${status.tone}`}>
                                        <Icon className="size-4" />
                                        {status.label}
                                        {account.lastVerifiedAt ? (
                                            <span className="text-muted-foreground">
                                                · verified {account.lastVerifiedAt}
                                            </span>
                                        ) : null}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Form {...SocialAccountController.connect.form({ account: account.id })}>
                                        {({ processing }) => (
                                            <Button variant="outline" disabled={processing}>
                                                Open browser & sign in
                                            </Button>
                                        )}
                                    </Form>

                                    <Form {...SocialAccountController.verify.form({ account: account.id })}>
                                        {({ processing }) => (
                                            <Button disabled={processing}>Verify</Button>
                                        )}
                                    </Form>
                                </div>
                            </div>

                            {account.status !== 'connected' ? (
                                <ol className="mt-4 space-y-1 border-t pt-4 text-sm text-muted-foreground">
                                    <li>1. Press <Badge variant="secondary">Open browser & sign in</Badge> — a Chromium window appears.</li>
                                    <li>2. Sign in to LinkedIn and clear any two-factor prompt yourself.</li>
                                    <li>3. Come back here and press <Badge variant="secondary">Verify</Badge>.</li>
                                </ol>
                            ) : null}
                        </Card>
                    );
                })}
            </div>
        </>
    );
}
```

- [ ] **Step 5: Run the test and the frontend gates**

```bash
php artisan test --compact --filter=AccountsTest
npm run lint && npm run types:check
```

Expected: 5 tests pass.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http resources/js tests/Feature/Agent/AccountsTest.php
git commit -m "feat: add interactive linkedin account connection screen"
```

---

### Task 15: Studio board

The board is read-only: cards are never draggable, and state changes only from approval or agent execution. That is what licenses the visual expressiveness here.

**Files:**
- Modify: `app/Http/Controllers/Agent/StudioController.php`
- Create: `resources/js/pages/studio/index.tsx`, `resources/js/components/agent/{board-column,post-card,empty-state,new-post-dialog}.tsx`
- Test: `tests/Feature/Agent/StudioTest.php`

**Interfaces:**
- Consumes: `Post`, `PostStatus::board()`, `posts.store`.
- Produces: the `studio` landing route and `PostCard`/`BoardColumn`/`EmptyState` components reused by later pages.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Agent/StudioTest.php`:

```php
<?php

use App\Enums\PostStatus;
use App\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;

test('the board groups posts into every column', function (): void {
    [$user, $team] = teamUser();
    Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Draft]);
    Post::factory()->count(2)->create(['team_id' => $team->id, 'status' => PostStatus::Published]);

    $this->actingAs($user)
        ->get(route('studio', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('studio/index')
            ->has('columns', 6)
            ->where('columns.1.status', 'draft')
            ->has('columns.1.posts', 1)
            ->has('columns.4.posts', 2));
});

test('the board never shows another team\'s posts', function (): void {
    [$user, $team] = teamUser();
    Post::factory()->create();

    $this->actingAs($user)
        ->get(route('studio', $team->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->where('totalPosts', 0));
});

test('the board exposes the connected accounts for the composer', function (): void {
    [$user, $team] = teamUser();
    App\Models\SocialAccount::factory()->connected()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('studio', $team->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->has('accounts', 1));
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=StudioTest`
Expected: FAIL — the invokable controller renders nothing.

- [ ] **Step 3: Implement the controller**

```php
<?php
// app/Http/Controllers/Agent/StudioController.php

namespace App\Http\Controllers\Agent;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    /**
     * Show the read only pipeline board.
     */
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        $posts = $team->posts()
            ->with('runs:id,post_id,status,step_count')
            ->latest('id')
            ->get(['id', 'brief', 'status', 'approved_body', 'published_url', 'updated_at']);

        return Inertia::render('studio/index', [
            'columns' => $this->columns($posts),
            'totalPosts' => $posts->count(),
            'accounts' => $team->socialAccounts()->connected()->get()->map(fn (SocialAccount $account): array => [
                'id' => $account->id,
                'handle' => $account->handle,
                'status' => $account->status,
                'lastVerifiedAt' => $account->last_verified_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Group the posts into the board's fixed columns.
     *
     * @param  Collection<int, Post>  $posts
     * @return array<int, array{status: string, label: string, posts: array<int, array<string, mixed>>}>
     */
    private function columns(Collection $posts): array
    {
        return array_map(fn (PostStatus $status): array => [
            'status' => $status->value,
            'label' => $status->label(),
            'posts' => $posts
                ->where('status', $status)
                ->map(fn (Post $post): array => [
                    'id' => $post->id,
                    'brief' => $post->brief,
                    'body' => $post->approved_body,
                    'publishedUrl' => $post->published_url,
                    'updatedAt' => $post->updated_at->diffForHumans(),
                    'progress' => $this->progress($post),
                ])
                ->values()
                ->all(),
        ], PostStatus::board());
    }

    /**
     * Get the step progress of the post's active run, if any.
     *
     * @return array{steps: int, budget: int}|null
     */
    private function progress(Post $post): ?array
    {
        $run = $post->runs->last();

        return $run && $run->status->isActive()
            ? ['steps' => $run->step_count, 'budget' => config('agent.step_cap')]
            : null;
    }
}
```

- [ ] **Step 4: Build the board components**

`resources/js/components/agent/empty-state.tsx`:

```tsx
import type { LucideIcon } from 'lucide-react';

type Props = {
    icon: LucideIcon;
    title: string;
    description: string;
    children?: React.ReactNode;
};

export function EmptyState({ icon: Icon, title, description, children }: Props) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-14 text-center">
            <Icon className="size-6 text-muted-foreground" />
            <div className="space-y-1">
                <p className="font-medium">{title}</p>
                <p className="max-w-sm text-sm text-muted-foreground">{description}</p>
            </div>
            {children}
        </div>
    );
}
```

`resources/js/components/agent/post-card.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { show } from '@/routes/posts';

export type BoardPost = {
    id: number;
    brief: string;
    body: string | null;
    publishedUrl: string | null;
    updatedAt: string;
    progress: { steps: number; budget: number } | null;
};

/**
 * A single ring segment showing how far through its step budget a run is.
 */
function ProgressRing({ steps, budget }: { steps: number; budget: number }) {
    const fraction = Math.min(steps / Math.max(budget, 1), 1);

    return (
        <svg viewBox="0 0 36 36" className="size-6 -rotate-90" aria-hidden>
            <circle cx="18" cy="18" r="15" fill="none" strokeWidth="4" className="stroke-muted" />
            <circle
                cx="18"
                cy="18"
                r="15"
                fill="none"
                strokeWidth="4"
                strokeLinecap="round"
                className="stroke-primary transition-[stroke-dashoffset] duration-700"
                strokeDasharray={`${2 * Math.PI * 15}`}
                strokeDashoffset={`${2 * Math.PI * 15 * (1 - fraction)}`}
            />
        </svg>
    );
}

export function PostCard({ post, teamSlug }: { post: BoardPost; teamSlug: string }) {
    return (
        <Link
            href={show([teamSlug, post.id])}
            className="group block rounded-lg border bg-card p-3 transition-colors hover:border-primary/40 hover:bg-accent/40"
        >
            <p className="line-clamp-3 text-sm leading-relaxed">
                {post.body ?? post.brief}
            </p>

            <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                <span>{post.updatedAt}</span>
                {post.progress ? (
                    <span className="flex items-center gap-1.5">
                        <ProgressRing {...post.progress} />
                        {post.progress.steps}/{post.progress.budget}
                    </span>
                ) : null}
            </div>
        </Link>
    );
}
```

`resources/js/components/agent/board-column.tsx`:

```tsx
import { PostCard, type BoardPost } from '@/components/agent/post-card';

type Props = {
    label: string;
    status: string;
    posts: BoardPost[];
    teamSlug: string;
};

export function BoardColumn({ label, status, posts, teamSlug }: Props) {
    const live = status === 'publishing' && posts.length > 0;

    return (
        <section className="flex min-w-64 flex-1 flex-col gap-3">
            <header className="flex items-center gap-2 px-1">
                <h2 className="text-sm font-medium">{label}</h2>
                <span
                    className={`rounded-full px-1.5 py-0.5 font-mono text-[11px] ${
                        live
                            ? 'animate-pulse bg-primary/15 text-primary'
                            : 'bg-muted text-muted-foreground'
                    }`}
                >
                    {posts.length}
                </span>
            </header>

            <div
                className={`flex flex-1 flex-col gap-2 rounded-xl border border-dashed p-2 transition-colors ${
                    live ? 'border-primary/40 bg-primary/[0.03]' : 'border-sidebar-border/70'
                }`}
            >
                {posts.map((post) => (
                    <PostCard key={post.id} post={post} teamSlug={teamSlug} />
                ))}
            </div>
        </section>
    );
}
```

- [ ] **Step 5: Build the studio page**

`resources/js/pages/studio/index.tsx`:

```tsx
import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { PenLine, Sparkles } from 'lucide-react';
import { useState } from 'react';
import PostController from '@/actions/App/Http/Controllers/Agent/PostController';
import { BoardColumn } from '@/components/agent/board-column';
import { EmptyState } from '@/components/agent/empty-state';
import type { BoardPost } from '@/components/agent/post-card';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as accountsIndex } from '@/routes/accounts';
import type { AccountData } from '@/types/agent';

type Column = { status: string; label: string; posts: BoardPost[] };

type Props = {
    columns: Column[];
    totalPosts: number;
    accounts: AccountData[];
};

export default function Studio({ columns, totalPosts, accounts }: Props) {
    const [open, setOpen] = useState(false);
    const teamSlug = usePage().props.currentTeam?.slug ?? '';
    const publishing = columns.find((column) => column.status === 'publishing');

    usePoll(publishing && publishing.posts.length > 0 ? 3000 : 0);

    return (
        <>
            <Head title="Studio" />

            <div className="flex h-full flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Studio"
                        description="Every post, and exactly where the agent has taken it."
                    />

                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button disabled={accounts.length === 0}>
                                <PenLine /> New post
                            </Button>
                        </DialogTrigger>

                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>What should the post say?</DialogTitle>
                                <DialogDescription>
                                    Describe the point, the tone, and anything that must appear.
                                </DialogDescription>
                            </DialogHeader>

                            <Form {...PostController.store.form()} onSuccess={() => setOpen(false)} className="space-y-4">
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="social_account_id"
                                            value={accounts[0]?.id ?? ''}
                                        />

                                        <textarea
                                            name="brief"
                                            rows={5}
                                            autoFocus
                                            placeholder="Announce Growth Savings. Warm, no jargon, mention that members asked for it."
                                            className="min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                        <InputError message={errors.brief} />

                                        <Button type="submit" disabled={processing} className="w-full">
                                            <Sparkles /> {processing ? 'Writing drafts…' : 'Write drafts'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                {accounts.length === 0 ? (
                    <EmptyState
                        icon={Sparkles}
                        title="Connect LinkedIn first"
                        description="The agent publishes through a real browser session that you sign in to yourself."
                    >
                        <Button variant="outline" asChild>
                            <a href={accountsIndex(teamSlug).url}>Go to accounts</a>
                        </Button>
                    </EmptyState>
                ) : totalPosts === 0 ? (
                    <EmptyState
                        icon={PenLine}
                        title="No posts yet"
                        description="Start with a brief. You'll get three drafts to choose from, and nothing is published until you approve the exact text."
                    />
                ) : (
                    <div className="flex flex-1 gap-4 overflow-x-auto pb-2">
                        {columns.map((column) => (
                            <BoardColumn key={column.status} teamSlug={teamSlug} {...column} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
```

- [ ] **Step 6: Run the test and the frontend gates**

```bash
php artisan test --compact --filter=StudioTest
npm run lint && npm run types:check
```

Expected: 3 tests pass.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http resources/js tests/Feature/Agent/StudioTest.php
git commit -m "feat: add the read-only studio pipeline board"
```

---

### Task 16: Post workspace

**Files:**
- Create: `resources/js/pages/posts/show.tsx`
- Create: `resources/js/components/agent/{thread-message,draft-card,run-timeline,step-row,post-preview}.tsx`
- Test: `tests/Feature/Agent/PostWorkspaceTest.php`

**Interfaces:**
- Consumes: props from `PostController@show` (`post`, `drafts`, `messages`, `run`, deferred `steps`), routes `posts.messages.store`, `posts.approve`, `runs.abort`.
- Produces: `RunTimeline` and `StepRow`, reused by the run replay page in Task 17.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Agent/PostWorkspaceTest.php`:

```php
<?php

use App\Enums\PostStatus;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Post;
use App\Models\PostDraft;
use Inertia\Testing\AssertableInertia as Assert;

test('the workspace renders the thread, drafts and run', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id, 'status' => PostStatus::Draft]);
    PostDraft::factory()->count(3)->create(['post_id' => $post->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id, 'status' => RunStatus::Running]);
    AgentStep::factory()->create(['agent_run_id' => $run->id]);

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('posts/show')
            ->where('post.id', $post->id)
            ->has('drafts', 3)
            ->where('run.status', 'running'));
});

test('the steps prop is deferred', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]))
        ->assertInertia(fn (Assert $page): Assert => $page->missing('steps'));
});

test('the deferred steps load on request', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);
    AgentStep::factory()->count(2)->create(['agent_run_id' => $run->id, 'index' => fake()->unique()->numberBetween(0, 50)]);

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]), ['X-Inertia' => true, 'X-Inertia-Partial-Data' => 'steps', 'X-Inertia-Partial-Component' => 'posts/show'])
        ->assertInertia(fn (Assert $page): Assert => $page->has('steps', 2));
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=PostWorkspaceTest`
Expected: FAIL — the `posts/show` page component does not exist.

- [ ] **Step 3: Build the thread components**

`resources/js/components/agent/thread-message.tsx`:

```tsx
import type { ThreadMessage as ThreadMessageData } from '@/types/agent';

export function ThreadMessage({ message, children }: { message: ThreadMessageData; children?: React.ReactNode }) {
    const isUser = message.role === 'user';

    return (
        <div className="space-y-2">
            <p className="font-mono text-[11px] uppercase tracking-wide text-muted-foreground">
                {isUser ? 'you' : 'bobby'}
            </p>

            {message.body ? (
                <p
                    className={`text-sm leading-relaxed ${
                        isUser ? 'rounded-lg bg-muted px-3 py-2' : ''
                    }`}
                >
                    {message.body}
                </p>
            ) : null}

            {children}
        </div>
    );
}
```

`resources/js/components/agent/draft-card.tsx`:

```tsx
import { Check } from 'lucide-react';
import type { DraftData } from '@/types/agent';

type Props = {
    draft: DraftData;
    selected: boolean;
    onSelect: (draft: DraftData) => void;
};

export function DraftCard({ draft, selected, onSelect }: Props) {
    return (
        <button
            type="button"
            onClick={() => onSelect(draft)}
            aria-pressed={selected}
            className={`w-full rounded-lg border p-3 text-left transition-colors ${
                selected ? 'border-primary bg-primary/[0.04]' : 'hover:border-primary/40 hover:bg-accent/40'
            }`}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="font-mono text-[11px] uppercase text-muted-foreground">
                    draft {draft.variant} · {draft.character_count} chars
                </span>
                {selected ? <Check className="size-4 text-primary" /> : null}
            </div>

            <p className="mt-2 line-clamp-4 text-sm leading-relaxed">{draft.body}</p>

            {draft.rationale ? (
                <p className="mt-2 text-xs text-muted-foreground">{draft.rationale}</p>
            ) : null}
        </button>
    );
}
```

- [ ] **Step 4: Build the theatre components**

`resources/js/components/agent/step-row.tsx`:

```tsx
import { Ban, Check, CircleDot, Loader2 } from 'lucide-react';
import { useState } from 'react';
import type { StepData } from '@/types/agent';

export function StepRow({ step }: { step: StepData }) {
    const [open, setOpen] = useState(false);
    const denied = step.gate_decision === 'denied';
    const errored = Boolean(step.error);

    const Icon = denied ? Ban : errored ? CircleDot : Check;

    return (
        <li>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left font-mono text-xs hover:bg-accent/50"
            >
                <Icon
                    className={`size-3.5 shrink-0 ${
                        denied ? 'text-amber-500' : errored ? 'text-destructive' : 'text-emerald-500'
                    }`}
                />
                <span className="text-muted-foreground">{String(step.index).padStart(2, '0')}</span>
                <span className="truncate">
                    {step.action ? `${step.action.action}${step.action.ref ? ` #${step.action.ref}` : ''}` : step.phase}
                </span>
                <span className="ml-auto shrink-0 text-muted-foreground">
                    {step.latency_ms ? `${step.latency_ms}ms` : ''}
                </span>
            </button>

            {open ? (
                <div className="space-y-1 px-8 pb-2 text-xs text-muted-foreground">
                    {step.reasoning ? <p>“{step.reasoning}”</p> : null}
                    {step.gate_reason ? <p className="text-amber-600 dark:text-amber-400">gate: {step.gate_reason}</p> : null}
                    {step.error ? <p className="text-destructive">error: {step.error}</p> : null}
                </div>
            ) : null}
        </li>
    );
}

export function StepSkeleton() {
    return (
        <li className="flex items-center gap-2 px-2 py-1.5 font-mono text-xs text-muted-foreground">
            <Loader2 className="size-3.5 animate-spin" />
            <span className="h-3 w-32 animate-pulse rounded bg-muted" />
        </li>
    );
}
```

`resources/js/components/agent/run-timeline.tsx`:

```tsx
import { Form } from '@inertiajs/react';
import { StepRow } from '@/components/agent/step-row';
import { Button } from '@/components/ui/button';
import RunController from '@/actions/App/Http/Controllers/Agent/RunController';
import type { RunData, StepData } from '@/types/agent';

const TONE: Record<RunData['status'], string> = {
    queued: 'text-muted-foreground',
    running: 'text-primary',
    published: 'text-emerald-600 dark:text-emerald-400',
    failed: 'text-destructive',
    aborted: 'text-amber-600 dark:text-amber-400',
};

type Props = {
    run: RunData;
    steps: StepData[];
};

export function RunTimeline({ run, steps }: Props) {
    const active = run.status === 'queued' || run.status === 'running';

    return (
        <div className="space-y-3">
            <header className="flex items-center justify-between gap-2">
                <p className="font-mono text-xs">
                    run #{run.id} ·{' '}
                    <span className={`${TONE[run.status]} ${active ? 'animate-pulse' : ''}`}>
                        {run.status}
                    </span>{' '}
                    · {run.step_count} steps
                </p>

                {active ? (
                    <Form {...RunController.abort.form({ run: run.id })}>
                        {({ processing }) => (
                            <Button size="sm" variant="ghost" disabled={processing}>
                                Abort
                            </Button>
                        )}
                    </Form>
                ) : null}
            </header>

            <ol className="rounded-lg border bg-card py-1">
                {steps.map((step) => (
                    <StepRow key={step.id} step={step} />
                ))}
            </ol>

            {run.failure_reason ? (
                <p className="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">
                    {run.failure_reason}
                </p>
            ) : null}
        </div>
    );
}
```

`resources/js/components/agent/post-preview.tsx`:

```tsx
export function PostPreview({ body, handle }: { body: string; handle: string | null }) {
    return (
        <article className="rounded-xl border bg-card p-4 shadow-xs">
            <header className="flex items-center gap-3">
                <div className="size-10 rounded-full bg-muted" />
                <div className="space-y-1">
                    <p className="text-sm font-medium">{handle ?? 'Your page'}</p>
                    <p className="text-xs text-muted-foreground">now · LinkedIn</p>
                </div>
            </header>

            <p className="mt-3 whitespace-pre-wrap text-sm leading-relaxed">{body}</p>
        </article>
    );
}
```

- [ ] **Step 5: Build the workspace page**

`resources/js/pages/posts/show.tsx`:

```tsx
import { Deferred, Form, Head, usePoll } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import PostApprovalController from '@/actions/App/Http/Controllers/Agent/PostApprovalController';
import PostMessageController from '@/actions/App/Http/Controllers/Agent/PostMessageController';
import { DraftCard } from '@/components/agent/draft-card';
import { PostPreview } from '@/components/agent/post-preview';
import { RunTimeline } from '@/components/agent/run-timeline';
import { StepSkeleton } from '@/components/agent/step-row';
import { ThreadMessage } from '@/components/agent/thread-message';
import { Button } from '@/components/ui/button';
import type { DraftData, PostSummary, RunData, StepData, ThreadMessage as ThreadMessageData } from '@/types/agent';

type Props = {
    post: PostSummary;
    drafts: DraftData[];
    messages: ThreadMessageData[];
    run: RunData | null;
    steps?: StepData[];
};

export default function PostWorkspace({ post, drafts, messages, run }: Props) {
    const [selected, setSelected] = useState<DraftData | null>(drafts[0] ?? null);
    const [body, setBody] = useState(post.approved_body ?? drafts[0]?.body ?? '');

    const active = run?.status === 'queued' || run?.status === 'running';

    usePoll(active ? 1500 : 0, { only: ['run', 'steps', 'post'] });

    const selectDraft = (draft: DraftData) => {
        setSelected(draft);
        setBody(draft.body);
    };

    return (
        <>
            <Head title="Post" />

            <div className="grid h-full grid-cols-1 gap-4 p-4 lg:grid-cols-2">
                <section className="flex flex-col gap-6 overflow-y-auto rounded-xl border p-4">
                    {messages.map((message) => (
                        <ThreadMessage key={message.id} message={message}>
                            {message.kind === 'drafts' ? (
                                <div className="space-y-2">
                                    {drafts.map((draft) => (
                                        <DraftCard
                                            key={draft.id}
                                            draft={draft}
                                            selected={selected?.id === draft.id}
                                            onSelect={selectDraft}
                                        />
                                    ))}
                                </div>
                            ) : null}
                        </ThreadMessage>
                    ))}

                    {post.status === 'draft' ? (
                        <div className="mt-auto space-y-4">
                            <Form {...PostMessageController.store.form({ post: post.id })} className="flex gap-2">
                                {({ processing }) => (
                                    <>
                                        <input
                                            name="body"
                                            placeholder="Shorter. Lead with the member story."
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                        <Button size="icon" disabled={processing} aria-label="Send revision">
                                            <Send />
                                        </Button>
                                    </>
                                )}
                            </Form>

                            <Form {...PostApprovalController.store.form({ post: post.id })} className="space-y-2">
                                {({ processing, errors }) => (
                                    <>
                                        <textarea
                                            name="body"
                                            rows={5}
                                            value={body}
                                            onChange={(event) => setBody(event.target.value)}
                                            className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {body.length} characters. This exact text is the only thing the agent may type.
                                        </p>
                                        {errors.social_account_id ? (
                                            <p className="text-xs text-destructive">{errors.social_account_id}</p>
                                        ) : null}
                                        <Button type="submit" disabled={processing || body.length < 2} className="w-full">
                                            Approve & publish
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    ) : null}
                </section>

                <section className="overflow-y-auto rounded-xl border p-4">
                    {run ? (
                        <Deferred
                            data="steps"
                            fallback={
                                <ol className="rounded-lg border bg-card py-1">
                                    <StepSkeleton />
                                    <StepSkeleton />
                                    <StepSkeleton />
                                </ol>
                            }
                        >
                            {({ steps }: { steps: StepData[] }) => <RunTimeline run={run} steps={steps} />}
                        </Deferred>
                    ) : (
                        <PostPreview body={body || post.brief} handle={null} />
                    )}

                    {post.published_url ? (
                        <a
                            href={post.published_url}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-4 inline-block text-sm text-primary underline-offset-4 hover:underline"
                        >
                            View the published post
                        </a>
                    ) : null}
                </section>
            </div>
        </>
    );
}
```

- [ ] **Step 6: Run the test and the frontend gates**

```bash
php artisan test --compact --filter=PostWorkspaceTest
npm run lint && npm run types:check
```

Expected: 3 tests pass. If `Deferred`'s render-prop signature differs in the installed Inertia v3 build, match the installed API.

- [ ] **Step 7: Format and commit**

```bash
git add resources/js tests/Feature/Agent/PostWorkspaceTest.php
git commit -m "feat: add the post workspace thread and live run theatre"
```

---

### Task 17: Run history and replay

**Files:**
- Create: `resources/js/pages/runs/index.tsx`, `resources/js/pages/runs/show.tsx`
- Create: `app/Http/Controllers/Agent/ScreenshotController.php` and its route
- Test: `tests/Feature/Agent/RunReplayTest.php`

**Interfaces:**
- Consumes: `RunController@index`, `RunController@show`, `AgentStep::screenshot_path`.
- Produces: route `runs.screenshot` (`GET {current_team}/runs/{run}/steps/{step}/screenshot`) streaming a private screenshot, 404 for other teams.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Agent/RunReplayTest.php`:

```php
<?php

use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('the runs index lists the team runs', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    AgentRun::factory()->count(2)->create(['post_id' => $post->id]);
    AgentRun::factory()->create();

    $this->actingAs($user)
        ->get(route('runs.index', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('runs/index')
            ->has('runs.data', 2));
});

test('a run can be replayed step by step', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);
    AgentStep::factory()->create(['agent_run_id' => $run->id, 'index' => 0]);

    $this->actingAs($user)
        ->get(route('runs.show', [$team->slug, $run]), ['X-Inertia' => true, 'X-Inertia-Partial-Data' => 'steps', 'X-Inertia-Partial-Component' => 'runs/show'])
        ->assertInertia(fn (Assert $page): Assert => $page->has('steps', 1));
});

test('screenshots are streamed from the private disk', function (): void {
    Storage::fake('local');
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);
    $step = AgentStep::factory()->create(['agent_run_id' => $run->id, 'screenshot_path' => 'agent-screenshots/1/000.png']);
    Storage::disk('local')->put('agent-screenshots/1/000.png', 'png-bytes');

    $this->actingAs($user)
        ->get(route('runs.screenshot', [$team->slug, $run, $step]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

test('another team cannot read a run or its screenshots', function (): void {
    [$user, $team] = teamUser();
    $run = AgentRun::factory()->create();
    $step = AgentStep::factory()->create(['agent_run_id' => $run->id]);

    $this->actingAs($user)->get(route('runs.show', [$team->slug, $run]))->assertNotFound();
    $this->actingAs($user)->get(route('runs.screenshot', [$team->slug, $run, $step]))->assertNotFound();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=RunReplayTest`
Expected: FAIL — `Route [runs.screenshot] not defined.`

- [ ] **Step 3: Add the screenshot controller and route**

```php
<?php
// app/Http/Controllers/Agent/ScreenshotController.php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentRun;
use App\Models\AgentStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenshotController extends Controller
{
    /**
     * Stream the screenshot captured for a single step.
     */
    public function __invoke(Request $request, AgentRun $run, AgentStep $step): StreamedResponse
    {
        abort_unless($run->post->team_id === $request->user()->currentTeam?->id, 404);
        abort_unless($step->agent_run_id === $run->id && $step->screenshot_path, 404);

        $disk = Storage::disk(config('agent.screenshot_disk'));

        abort_unless($disk->exists($step->screenshot_path), 404);

        return $disk->response($step->screenshot_path, headers: ['content-type' => 'image/png']);
    }
}
```

Route, inside the team group:

```php
Route::get('runs/{run}/steps/{step}/screenshot', ScreenshotController::class)->name('runs.screenshot');
```

- [ ] **Step 4: Build the runs index page**

`resources/js/pages/runs/index.tsx`:

```tsx
import { Head, Link, usePage } from '@inertiajs/react';
import { History } from 'lucide-react';
import { EmptyState } from '@/components/agent/empty-state';
import Heading from '@/components/heading';
import { show } from '@/routes/runs';
import type { RunStatus } from '@/types/agent';

type RunRow = {
    id: number;
    status: RunStatus;
    stepCount: number;
    failureReason: string | null;
    startedAt: string | null;
    brief: string;
};

type Props = {
    runs: { data: RunRow[] };
};

const TONE: Record<RunStatus, string> = {
    queued: 'text-muted-foreground',
    running: 'text-primary',
    published: 'text-emerald-600 dark:text-emerald-400',
    failed: 'text-destructive',
    aborted: 'text-amber-600 dark:text-amber-400',
};

export default function RunsIndex({ runs }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Runs" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4">
                <Heading title="Runs" description="Every publishing attempt, kept in full." />

                {runs.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="No runs yet"
                        description="Approve a post and the agent's every step will be recorded here."
                    />
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {runs.data.map((run) => (
                            <li key={run.id}>
                                <Link
                                    href={show([teamSlug, run.id])}
                                    className="flex items-center gap-4 px-4 py-3 hover:bg-accent/40"
                                >
                                    <span className="font-mono text-xs text-muted-foreground">#{run.id}</span>
                                    <span className="line-clamp-1 flex-1 text-sm">{run.brief}</span>
                                    <span className="font-mono text-xs text-muted-foreground">{run.stepCount} steps</span>
                                    <span className={`font-mono text-xs ${TONE[run.status]}`}>{run.status}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
```

- [ ] **Step 5: Build the replay page**

`resources/js/pages/runs/show.tsx`:

```tsx
import { Deferred, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StepRow, StepSkeleton } from '@/components/agent/step-row';
import Heading from '@/components/heading';
import { screenshot } from '@/routes/runs';
import type { StepData } from '@/types/agent';

type Props = {
    run: {
        id: number;
        status: string;
        failureReason: string | null;
        contract: Record<string, unknown>;
        postId: number;
    };
    steps?: StepData[];
};

function Replay({ runId, steps }: { runId: number; steps: StepData[] }) {
    const [cursor, setCursor] = useState(0);
    const teamSlug = usePage().props.currentTeam?.slug ?? '';
    const current = steps[cursor];

    return (
        <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
            <ol className="h-fit rounded-lg border bg-card py-1">
                {steps.map((step, index) => (
                    <div key={step.id} onMouseEnter={() => setCursor(index)}>
                        <StepRow step={step} />
                    </div>
                ))}
            </ol>

            <div className="space-y-3">
                {current ? (
                    <>
                        <img
                            src={screenshot([teamSlug, runId, current.id]).url}
                            alt={`Step ${current.index}`}
                            className="w-full rounded-lg border"
                        />
                        <p className="font-mono text-xs text-muted-foreground">
                            step {current.index} · {current.phase}
                            {current.reasoning ? ` · “${current.reasoning}”` : ''}
                        </p>
                    </>
                ) : null}
            </div>
        </div>
    );
}

export default function RunShow({ run }: Props) {
    return (
        <>
            <Head title={`Run #${run.id}`} />

            <div className="space-y-6 p-4">
                <Heading title={`Run #${run.id}`} description={run.failureReason ?? run.status} />

                <Deferred
                    data="steps"
                    fallback={
                        <ol className="rounded-lg border bg-card py-1">
                            <StepSkeleton />
                            <StepSkeleton />
                            <StepSkeleton />
                        </ol>
                    }
                >
                    {({ steps }: { steps: StepData[] }) => <Replay runId={run.id} steps={steps} />}
                </Deferred>
            </div>
        </>
    );
}
```

- [ ] **Step 6: Run the test and the frontend gates**

```bash
php artisan test --compact --filter=RunReplayTest
npm run lint && npm run types:check
```

Expected: 4 tests pass.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http routes/web.php resources/js tests/Feature/Agent/RunReplayTest.php
git commit -m "feat: add run history and step-by-step replay"
```

---

### Task 18: Operations, navigation and final verification

**Files:**
- Create: `app/Console/Commands/AgentDoctor.php`, `app/Console/Commands/PruneAgentScreenshots.php`
- Modify: `routes/console.php` (schedule the prune), `resources/js/components/app-sidebar.tsx`, `app/Http/Responses/LoginResponse.php` or `routes/web.php` (land on Studio)
- Test: `tests/Feature/Agent/AgentDoctorTest.php`

**Interfaces:**
- Consumes: everything built so far.
- Produces: `php artisan agent:doctor` (exit code 0 when healthy, 1 otherwise) and `php artisan agent:prune-screenshots`.

- [ ] **Step 1: Write the failing doctor test**

`tests/Feature/Agent/AgentDoctorTest.php`:

```php
<?php

use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('the doctor passes when ollama, the model and the sidecar are all healthy', function (): void {
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => config('agent.model')]]]),
        '*/health' => Http::response(['ok' => true, 'chromium' => true]),
    ]);

    $this->artisan('agent:doctor')
        ->expectsOutputToContain('Ollama reachable')
        ->expectsOutputToContain('Browser sidecar reachable')
        ->assertExitCode(0);
});

test('the doctor fails when the configured model is not pulled', function (): void {
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'llama3.1:8b']]]),
        '*/health' => Http::response(['ok' => true, 'chromium' => true]),
    ]);

    $this->artisan('agent:doctor')
        ->expectsOutputToContain('ollama pull')
        ->assertExitCode(1);
});

test('the doctor fails when the sidecar is down', function (): void {
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => config('agent.model')]]]),
        '*/health' => fn () => throw new Illuminate\Http\Client\ConnectionException('Connection refused'),
    ]);

    $this->artisan('agent:doctor')
        ->expectsOutputToContain('composer run browser')
        ->assertExitCode(1);
});

test('pruning removes screenshots for old runs', function (): void {
    Storage::fake('local');
    $post = Post::factory()->create();
    $old = AgentRun::factory()->create(['post_id' => $post->id, 'created_at' => now()->subDays(40)]);
    $fresh = AgentRun::factory()->create(['post_id' => $post->id]);

    AgentStep::factory()->create(['agent_run_id' => $old->id, 'screenshot_path' => 'agent-screenshots/'.$old->id.'/000.png']);
    AgentStep::factory()->create(['agent_run_id' => $fresh->id, 'screenshot_path' => 'agent-screenshots/'.$fresh->id.'/000.png']);
    Storage::disk('local')->put('agent-screenshots/'.$old->id.'/000.png', 'x');
    Storage::disk('local')->put('agent-screenshots/'.$fresh->id.'/000.png', 'x');

    $this->artisan('agent:prune-screenshots')->assertExitCode(0);

    Storage::disk('local')->assertMissing('agent-screenshots/'.$old->id.'/000.png');
    Storage::disk('local')->assertExists('agent-screenshots/'.$fresh->id.'/000.png');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=AgentDoctorTest`
Expected: FAIL — `The command "agent:doctor" does not exist.`

- [ ] **Step 3: Implement the doctor**

```php
<?php
// app/Console/Commands/AgentDoctor.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class AgentDoctor extends Command
{
    protected $signature = 'agent:doctor';

    protected $description = 'Check that Ollama, the configured model and the browser sidecar are ready';

    /**
     * Run every readiness check and report what needs fixing.
     */
    public function handle(): int
    {
        $checks = [
            $this->checkOllama(),
            $this->checkModel(),
            $this->checkVision(),
            $this->checkSidecar(),
        ];

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Verify the Ollama daemon answers.
     */
    private function checkOllama(): bool
    {
        try {
            Http::timeout(5)->get(config('ai.providers.ollama.url').'/api/tags')->throw();
        } catch (Throwable) {
            $this->error('✗ Ollama unreachable at '.config('ai.providers.ollama.url').' — start it with "ollama serve".');

            return false;
        }

        $this->info('✓ Ollama reachable');

        return true;
    }

    /**
     * Verify the configured model has been pulled.
     */
    private function checkModel(): bool
    {
        $model = config('agent.model');

        try {
            $names = collect(Http::timeout(5)->get(config('ai.providers.ollama.url').'/api/tags')->json('models', []))
                ->pluck('name');
        } catch (Throwable) {
            return false;
        }

        if (! $names->contains($model)) {
            $this->error('✗ Model ['.$model.'] is not pulled — run "ollama pull '.$model.'".');
            $this->line('  Available: '.$names->implode(', '));

            return false;
        }

        $this->info('✓ Model ['.$model.'] available');

        return true;
    }

    /**
     * Warn when perception is set to marks but vision has not been proven.
     */
    private function checkVision(): bool
    {
        if (config('agent.perception') !== 'marks') {
            $this->line('· Perception is set to "text" — screenshots are stored but not sent to the model.');

            return true;
        }

        $this->line('· Perception is "marks". If runs fail with unusable actions, set AGENT_PERCEPTION=text.');

        return true;
    }

    /**
     * Verify the browser sidecar answers and has Chromium.
     */
    private function checkSidecar(): bool
    {
        try {
            $healthy = Http::timeout(5)->get(config('agent.sidecar_url').'/health')->json('chromium') === true;
        } catch (Throwable) {
            $this->error('✗ Browser sidecar unreachable at '.config('agent.sidecar_url').' — start it with "composer run browser".');

            return false;
        }

        if (! $healthy) {
            $this->error('✗ Chromium missing — run "cd browser && npx playwright install chromium".');

            return false;
        }

        $this->info('✓ Browser sidecar reachable');

        return true;
    }
}
```

- [ ] **Step 4: Implement the prune command and schedule it**

```php
<?php
// app/Console/Commands/PruneAgentScreenshots.php

namespace App\Console\Commands;

use App\Models\AgentRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneAgentScreenshots extends Command
{
    protected $signature = 'agent:prune-screenshots';

    protected $description = 'Delete screenshots for runs older than the configured retention window';

    /**
     * Remove the stored screenshots, leaving the step records intact.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays(config('agent.screenshot_retention_days'));
        $disk = Storage::disk(config('agent.screenshot_disk'));
        $deleted = 0;

        AgentRun::query()
            ->where('created_at', '<', $cutoff)
            ->with('steps:id,agent_run_id,screenshot_path')
            ->chunkById(100, function ($runs) use ($disk, &$deleted): void {
                foreach ($runs as $run) {
                    foreach ($run->steps as $step) {
                        if ($step->screenshot_path && $disk->delete($step->screenshot_path)) {
                            $deleted++;
                        }
                    }
                }
            });

        $this->info('Deleted '.$deleted.' screenshots.');

        return self::SUCCESS;
    }
}
```

In `routes/console.php`:

```php
Schedule::command('agent:prune-screenshots')->dailyAt('03:00');
```

- [ ] **Step 5: Add the navigation**

In `resources/js/components/app-sidebar.tsx`, extend `mainNavItems` (keeping Dashboard):

```tsx
import { History, LayoutGrid, PenLine, UserCog, Palette } from 'lucide-react';
import { index as accountsIndex } from '@/routes/accounts';
import { edit as brandEdit } from '@/routes/brand';
import { index as runsIndex } from '@/routes/runs';
import { studio } from '@/routes';

const teamSlug = page.props.currentTeam?.slug;

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboardUrl, icon: LayoutGrid },
    ...(teamSlug
        ? [
              { title: 'Studio', href: studio(teamSlug), icon: PenLine },
              { title: 'Runs', href: runsIndex(teamSlug), icon: History },
              { title: 'Accounts', href: accountsIndex(teamSlug), icon: UserCog },
              { title: 'Brand', href: brandEdit(teamSlug), icon: Palette },
          ]
        : []),
];
```

- [ ] **Step 6: Run the full suite and every gate**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run lint
npm run types:check
npm run build
cd browser && npm test
```

Expected: all green. Do not proceed past a failure — fix it.

- [ ] **Step 7: Verify the real thing by hand**

```bash
composer run dev      # terminal 1
composer run browser  # terminal 2
php artisan agent:doctor
```

Then: connect the LinkedIn account, write a brief, approve a draft, and watch the run. Confirm the post exists on LinkedIn and that `published_url` matches.

- [ ] **Step 8: Commit**

```bash
git add app/Console routes/console.php resources/js tests/Feature/Agent/AgentDoctorTest.php
git commit -m "feat: add agent doctor, screenshot pruning and studio navigation"
```

---

## Self-Review

**Spec coverage.** Every spec section maps to a task: §2 architecture → 1, 7, 8; §3 perception → 7 (marks) and 1 (`AGENT_PERCEPTION` degraded mode); §4 loop → 9, 10; §5 gate → 5; §6 content → 11; §7 data model → 2, 3; §8 interfaces → 4, 6; §9 UI → 13–17 (Studio 15, workspace 16, accounts 14, brand 13, runs 17, navigation 18); §10 error handling → 8 (sidecar down), 9 (unusable output), 10 (denials, budget, unverified), 14 (expired session); §11 testing → every task, with the full fake-driven loop in 10; §12 operations → 18.

**Known follow-ups, deliberately out of scope.** `RunMode::Step` is persisted and rendered but the per-action confirmation UI is not built — the enum exists so the runner can gain it without a migration. Screenshot storage is unbounded between prune runs. There is no pagination on the Studio board.

**Watch items during execution.**
- `laravel/ai` is 0.x: `Promptable::prompt` named arguments and the `JsonSchema` builder methods (`->enum()`, `->items()`, `->object()`) are where an installed-version difference will surface. Tests in Tasks 9–11 will catch it. The fake API was already corrected to `MyAgent::fake()` after reading the installed package.
- Inertia v3 `Deferred` render-prop shape and `usePoll` signature are the equivalent risk on the frontend, caught in Tasks 16–17.
- Task 12's route registration references controllers created as stubs in that same task; Tasks 13–15 fill them in. Running the suite between those tasks will show `posts/show` page-missing errors until Task 16 if `inertia.pages.ensure_pages_exist` is on for tests.


