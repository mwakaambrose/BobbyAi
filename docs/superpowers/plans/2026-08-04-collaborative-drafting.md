# Collaborative Drafting and the Run Theatre Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a writer correct one sentence of a draft by selecting it and telling Bobby what is wrong, and let them watch a publishing run as narrated prose with the live screenshot instead of a polling log.

**Architecture:** Two features over one existing private broadcast channel. Span refinement queues a job that streams a replacement for a selected fragment over Reverb; accepting it splices the buffer and records the exchange as thread turns. The run theatre broadcasts each step as `PublishRunner` persists it, narrated by a backend `StepNarrator` shared by the live panel and the after-the-fact replay.

**Tech Stack:** PHP 8.4, Laravel 13, Laravel AI v0, Reverb, Pest 5, Inertia v3, React 19, Tailwind v4, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-08-04-collaborative-drafting-design.md`

## Global Constraints

- **No new dependencies.** Neither composer nor npm. CLAUDE.md requires approval; this design was chosen to need none.
- **Every change is tested.** A new or updated test per task, run before commit.
- **Run `vendor/bin/pint --dirty --format agent`** after touching any PHP file, before committing.
- **Run `php artisan wayfinder:generate`** after adding or changing a route, before importing the generated action in TypeScript.
- **Tests:** `php artisan test --compact --filter=<Name>`. Full suite `composer test` runs pint, phpstan and the suite.
- **Frontend verification:** `npm run types:check && npm run lint:check`. There is no JS test runner in this project; frontend correctness is asserted through Pest Inertia assertions on props plus a typecheck.
- **Follow sibling files.** Constructor property promotion, explicit return types, PHPDoc over inline comments, TitleCase enum cases.
- **Copy voice:** lowercase eyebrows, sentence-case prose, no exclamation marks. Match `thread-column.tsx` and `draft-canvas.tsx`.
- Broadcast events that carry a stream implement `ShouldBroadcastNow`, never `ShouldBroadcast`.

## File Structure

**Phase A — correction loop, backend**

| File | Responsibility |
| --- | --- |
| `app/Ai/Agents/EditorAgent.php` | Plain-text agent that rewrites one fragment |
| `app/Agent/Reviser.php` | Builds the prompt, streams deltas, returns the replacement |
| `app/Events/RefineChunk.php` | `refine.chunk` — one delta |
| `app/Events/RefineCompleted.php` | `refine.completed` — the settled replacement |
| `app/Events/RefineFailed.php` | `refine.failed` — the reason |
| `app/Jobs/RefineDraftSpan.php` | Runs the reviser, announces completion or failure |
| `app/Http/Requests/Agent/StoreRefinementRequest.php` | Validates the three segments and the instruction |
| `app/Http/Requests/Agent/AcceptRefinementRequest.php` | Validates the accepted pair |
| `app/Http/Controllers/Agent/PostRefinementController.php` | `store` queues, `accept` records |
| `app/Enums/MessageKind.php` | Gains `Edit` |

**Phase B — correction loop, frontend**

| File | Responsibility |
| --- | --- |
| `resources/js/hooks/use-post-channel.ts` | Owns joining and leaving `posts.{id}` |
| `resources/js/hooks/use-span-refinement.ts` | Requests a refinement, accumulates the stream, times out |
| `resources/js/components/agent/draft-editor.tsx` | Textarea, selection capture, prompt bar |
| `resources/js/components/agent/span-proposal.tsx` | Read-only diff, accept and reject |
| `resources/js/components/agent/draft-canvas.tsx` | Shrinks to a shell: angles, char count, approve |
| `resources/js/components/agent/thread-column.tsx` | Renders the `edit` message kind |

**Phase C — theatre and replay**

| File | Responsibility |
| --- | --- |
| `database/migrations/*_add_target_to_agent_steps_table.php` | `target` — what the ref pointed at |
| `app/Agent/StepNarrator.php` | One `AgentStep` to one prose line |
| `app/Events/RunStepRecorded.php` | `run.step` — a step as it is persisted |
| `app/Agent/PublishRunner.php` | Fills `target`, broadcasts each step |
| `resources/js/hooks/use-run-stream.ts` | Merges streamed steps with server props |
| `resources/js/components/agent/step-narration.tsx` | One narrated line, shared by both surfaces |
| `resources/js/components/agent/run-theatre.tsx` | Screenshot plus narration; replaces `run-timeline.tsx` |
| `resources/js/pages/runs/show.tsx` | Replay reads as narration |

Phase A and B must land in order. Phase C is independent of A and B and may be built first if preferred.

---

### Task 1: The editor agent and reviser

**Files:**
- Create: `app/Ai/Agents/EditorAgent.php`
- Create: `app/Agent/Reviser.php`
- Test: `tests/Feature/Agent/ReviserTest.php`

**Interfaces:**
- Consumes: `App\Models\BrandProfile` (existing).
- Produces: `Reviser::streamSpan(string $before, string $selection, string $after, string $instruction, ?BrandProfile $brand, Closure $onDelta): string` — returns the trimmed replacement for the span only. `$onDelta` receives `string`, returns `void`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Agent/ReviserTest.php`:

```php
<?php

use App\Agent\Reviser;
use App\Ai\Agents\EditorAgent;
use App\Models\BrandProfile;
use App\Models\User;

test('the reviser returns only the replacement for the span', function (): void {
    EditorAgent::fake(['Four features. Zero all-nighters.']);

    $replacement = app(Reviser::class)->streamSpan(
        before: 'Last quarter we shipped four features. ',
        selection: 'It was a big deal for the team.',
        after: ' Here is what we learned.',
        instruction: 'make this land harder',
        brand: null,
        onDelta: fn (string $delta) => null,
    );

    expect($replacement)->toBe('Four features. Zero all-nighters.');
});

test('each delta is handed to the caller as it arrives', function (): void {
    EditorAgent::fake(['Three word replacement']);

    $deltas = [];

    app(Reviser::class)->streamSpan(
        before: '',
        selection: 'the old sentence',
        after: '',
        instruction: 'tighten it',
        brand: null,
        onDelta: function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        },
    );

    expect($deltas)->toHaveCount(3)
        ->and($deltas[0])->toBe('Three');
});

test('the brand profile conditions the rewrite', function (): void {
    EditorAgent::fake(['A replacement.']);

    $user = User::factory()->create();
    $brand = BrandProfile::factory()->for($user->currentTeam)->create([
        'voice' => 'Plain and direct',
        'banned_terms' => ['synergy'],
    ]);

    app(Reviser::class)->streamSpan(
        before: '',
        selection: 'the old sentence',
        after: '',
        instruction: 'tighten it',
        brand: $brand,
        onDelta: fn (string $delta) => null,
    );

    EditorAgent::assertPrompted(fn (string $prompt): bool => str_contains($prompt, 'Plain and direct')
        && str_contains($prompt, 'synergy'));
});
```

If `EditorAgent::assertPrompted` does not exist on this version of Laravel AI, check how `ComposerAgent` is asserted in `tests/Feature/Agent/DraftPostContentTest.php` and mirror it; if there is no prompt assertion available, drop the third test and instead assert the prompt string through a public `Reviser::promptFor(...)` you expose for that purpose.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ReviserTest`
Expected: FAIL — `Class "App\Ai\Agents\EditorAgent" not found`.

- [ ] **Step 3: Write the agent**

Create `app/Ai/Agents/EditorAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Rewrites one fragment of a post in place. Plain text so it can be streamed
 * into the proposal as it is written, and scoped to the fragment so the text
 * around the writer's selection never moves.
 */
class EditorAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You revise one fragment of a LinkedIn post. You are given the text before
        the fragment, the fragment itself, the text after it, and an instruction
        describing what is wrong with the fragment.

        Constraints:
        - Return a replacement for the fragment and nothing else. No preamble, no
          explanation, no quotation marks around it.
        - Do not rewrite, repeat or extend the text before or after the fragment.
          It is context only.
        - Match the surrounding voice, tense and person.
        - Plain text only. No markdown, no bullet characters, no emoji spam.
        - Never use a banned term you are given.
        - Do not invent facts, statistics, product features or testimonials.
        - If the instruction cannot be honoured, return the fragment unchanged.
        TEXT;
    }
}
```

- [ ] **Step 4: Write the reviser**

Create `app/Agent/Reviser.php`:

```php
<?php

namespace App\Agent;

use App\Ai\Agents\EditorAgent;
use App\Models\BrandProfile;
use Closure;
use Laravel\Ai\Streaming\Events\TextDelta;

/**
 * Streams a replacement for one selected fragment of a draft, handing each
 * delta to the caller so it can be broadcast while the model is still writing.
 */
final class Reviser
{
    public function __construct(private readonly EditorAgent $agent) {}

    /**
     * Stream a replacement for the selected fragment.
     *
     * @param  Closure(string): void  $onDelta
     */
    public function streamSpan(
        string $before,
        string $selection,
        string $after,
        string $instruction,
        ?BrandProfile $brand,
        Closure $onDelta,
    ): string {
        $replacement = '';

        $stream = $this->agent->stream(
            $this->prompt($before, $selection, $after, $instruction, $brand),
            provider: config('agent.provider'),
            model: config('agent.model'),
            timeout: config('agent.inference_timeout'),
        );

        foreach ($stream as $event) {
            if (! $event instanceof TextDelta) {
                continue;
            }

            $replacement .= $event->delta;

            $onDelta($event->delta);
        }

        return trim($replacement);
    }

    /**
     * Build the brand conditioned prompt for a single fragment.
     */
    private function prompt(
        string $before,
        string $selection,
        string $after,
        string $instruction,
        ?BrandProfile $brand,
    ): string {
        return implode("\n\n", array_filter([
            $brand?->voice ? 'VOICE: '.$brand->voice : null,
            $brand?->audience ? 'AUDIENCE: '.$brand->audience : null,
            $brand?->banned_terms ? 'NEVER USE: '.implode(', ', $brand->banned_terms) : null,
            $before === '' ? null : "TEXT BEFORE:\n".$before,
            "FRAGMENT TO REPLACE:\n".$selection,
            $after === '' ? null : "TEXT AFTER:\n".$after,
            'INSTRUCTION: '.$instruction,
        ]));
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ReviserTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Agents/EditorAgent.php app/Agent/Reviser.php tests/Feature/Agent/ReviserTest.php
git commit -m "feat: teach bobby to rewrite one fragment in place"
```

---

### Task 2: Streaming the refinement

**Files:**
- Create: `app/Events/RefineChunk.php`, `app/Events/RefineCompleted.php`, `app/Events/RefineFailed.php`
- Create: `app/Jobs/RefineDraftSpan.php`
- Test: `tests/Feature/Agent/RefineDraftSpanTest.php`

**Interfaces:**
- Consumes: `Reviser::streamSpan(...)` from Task 1.
- Produces: `new RefineDraftSpan(Post $post, string $before, string $selection, string $after, string $instruction)`; `handle(Reviser $reviser): void`. Broadcast names `refine.chunk` (`{delta}`), `refine.completed` (`{replacement}`), `refine.failed` (`{reason}`), all on `private-posts.{postId}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Agent/RefineDraftSpanTest.php`:

```php
<?php

use App\Agent\Reviser;
use App\Ai\Agents\EditorAgent;
use App\Events\RefineChunk;
use App\Events\RefineCompleted;
use App\Events\RefineFailed;
use App\Jobs\RefineDraftSpan;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function refineJob(Post $post): RefineDraftSpan
{
    return new RefineDraftSpan(
        post: $post,
        before: 'Last quarter we shipped four features. ',
        selection: 'It was a big deal for the team.',
        after: '',
        instruction: 'make this land harder',
    );
}

test('the replacement is broadcast as it is written', function (): void {
    Event::fake([RefineChunk::class, RefineCompleted::class]);
    EditorAgent::fake(['Three word replacement']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    refineJob($post)->handle(app(Reviser::class));

    Event::assertDispatchedTimes(RefineChunk::class, 3);
    Event::assertDispatched(RefineChunk::class, fn (RefineChunk $event): bool => $event->postId === $post->id
        && $event->delta === 'Three');
});

test('completion carries the settled replacement', function (): void {
    Event::fake([RefineCompleted::class]);
    EditorAgent::fake(['Four features. Zero all-nighters.']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    refineJob($post)->handle(app(Reviser::class));

    Event::assertDispatched(RefineCompleted::class, fn (RefineCompleted $event): bool => $event->postId === $post->id
        && $event->replacement === 'Four features. Zero all-nighters.');
});

test('nothing is persisted by a refinement', function (): void {
    Event::fake();
    EditorAgent::fake(['A replacement.']);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    refineJob($post)->handle(app(Reviser::class));

    expect($post->drafts()->count())->toBe(0)
        ->and($post->messages()->count())->toBe(0);
});

test('a failure is announced without leaving a thread turn behind', function (): void {
    Event::fake([RefineFailed::class]);

    $user = User::factory()->create();
    $post = Post::factory()->for($user->currentTeam)->create(['author_id' => $user->id]);

    refineJob($post)->failed(new RuntimeException('provider down'));

    expect($post->messages()->count())->toBe(0);

    Event::assertDispatched(RefineFailed::class, fn (RefineFailed $event): bool => $event->postId === $post->id
        && $event->reason === 'provider down');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=RefineDraftSpanTest`
Expected: FAIL — `Class "App\Events\RefineChunk" not found`.

- [ ] **Step 3: Write the three events**

Create `app/Events/RefineChunk.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A slice of the replacement as the model writes it. Broadcast immediately
 * rather than queued, since putting each delta behind the queue would defeat
 * the point.
 */
class RefineChunk implements ShouldBroadcastNow
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
        return 'refine.chunk';
    }
}
```

Create `app/Events/RefineCompleted.php` — identical shape, constructor `(public readonly int $postId, public readonly string $replacement)`, `broadcastAs(): 'refine.completed'`, docblock: *"The settled replacement. The client trusts this over whatever it accumulated from the deltas."*

Create `app/Events/RefineFailed.php` — constructor `(public readonly int $postId, public readonly string $reason)`, `broadcastAs(): 'refine.failed'`, docblock: *"The refinement did not produce a replacement. Nothing was persisted, so the editor simply comes back."*

- [ ] **Step 4: Write the job**

Create `app/Jobs/RefineDraftSpan.php`:

```php
<?php

namespace App\Jobs;

use App\Agent\Reviser;
use App\Events\RefineChunk;
use App\Events\RefineCompleted;
use App\Events\RefineFailed;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Rewrites one selected fragment of a draft, broadcasting the replacement as it
 * is composed. Nothing is persisted: the proposal only becomes part of the
 * record if the writer accepts it.
 */
class RefineDraftSpan implements ShouldQueue
{
    use Queueable;

    /**
     * A retry would stream a second replacement into a proposal the writer is
     * already reading, so a refinement gets one attempt.
     */
    public int $tries = 1;

    public function __construct(
        public readonly Post $post,
        public readonly string $before,
        public readonly string $selection,
        public readonly string $after,
        public readonly string $instruction,
    ) {}

    public function handle(Reviser $reviser): void
    {
        $replacement = $reviser->streamSpan(
            $this->before,
            $this->selection,
            $this->after,
            $this->instruction,
            $this->post->team->brandProfile,
            fn (string $delta) => RefineChunk::dispatch($this->post->id, $delta),
        );

        RefineCompleted::dispatch($this->post->id, $replacement);
    }

    /**
     * Leave the failure on the canvas rather than a proposal that appears to
     * still be thinking.
     */
    public function failed(Throwable $exception): void
    {
        RefineFailed::dispatch($this->post->id, $exception->getMessage());
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=RefineDraftSpanTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Events/Refine*.php app/Jobs/RefineDraftSpan.php tests/Feature/Agent/RefineDraftSpanTest.php
git commit -m "feat: stream a fragment rewrite over the post channel"
```

---

### Task 3: The refinement endpoints

**Files:**
- Modify: `app/Enums/MessageKind.php`
- Create: `app/Http/Requests/Agent/StoreRefinementRequest.php`, `app/Http/Requests/Agent/AcceptRefinementRequest.php`
- Create: `app/Http/Controllers/Agent/PostRefinementController.php`
- Modify: `routes/web.php:33` (after the alternatives route)
- Test: `tests/Feature/Agent/PostRefinementTest.php`

**Interfaces:**
- Consumes: `RefineDraftSpan` from Task 2.
- Produces: routes `posts.refinements.store` (`POST {current_team}/posts/{post}/refinements`) and `posts.refinements.accept` (`POST {current_team}/posts/{post}/refinements/accept`). `MessageKind::Edit` with value `edit` and `payload: {before: string, after: string}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Agent/PostRefinementTest.php`:

```php
<?php

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Jobs\RefineDraftSpan;
use App\Models\Post;
use Illuminate\Support\Facades\Queue;

test('asking for a fragment rewrite queues the job', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('posts.refinements.store', [$team->slug, $post]), [
            'before' => 'Last quarter we shipped four features. ',
            'selection' => 'It was a big deal for the team.',
            'after' => '',
            'instruction' => 'make this land harder',
        ])
        ->assertRedirect();

    Queue::assertPushed(RefineDraftSpan::class, fn (RefineDraftSpan $job): bool => $job->post->is($post)
        && $job->selection === 'It was a big deal for the team.'
        && $job->instruction === 'make this land harder');
});

test('an empty selection is refused before anything is queued', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('posts.refinements.store', [$team->slug, $post]), [
            'before' => 'Some text.',
            'selection' => '',
            'after' => '',
            'instruction' => 'make this land harder',
        ])
        ->assertSessionHasErrors('selection');

    Queue::assertNothingPushed();
});

test('accepting a rewrite records both sides of the exchange', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('posts.refinements.accept', [$team->slug, $post]), [
            'instruction' => 'make this land harder',
            'before' => 'It was a big deal for the team.',
            'after' => 'Four features. Zero all-nighters.',
        ])
        ->assertRedirect();

    $messages = $post->messages()->orderBy('id')->get();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->role)->toBe(MessageRole::User)
        ->and($messages[0]->kind)->toBe(MessageKind::Text)
        ->and($messages[0]->body)->toBe('make this land harder')
        ->and($messages[1]->role)->toBe(MessageRole::Agent)
        ->and($messages[1]->kind)->toBe(MessageKind::Edit)
        ->and($messages[1]->payload['before'])->toBe('It was a big deal for the team.')
        ->and($messages[1]->payload['after'])->toBe('Four features. Zero all-nighters.');
});

test('another team cannot refine a post', function (): void {
    Queue::fake();
    [$user, $team] = teamUser();
    $post = Post::factory()->create();

    $this->actingAs($user)
        ->post(route('posts.refinements.store', [$team->slug, $post]), [
            'before' => '',
            'selection' => 'a fragment',
            'after' => '',
            'instruction' => 'tighten it',
        ])
        ->assertNotFound();

    Queue::assertNothingPushed();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PostRefinementTest`
Expected: FAIL — `Route [posts.refinements.store] not defined`.

- [ ] **Step 3: Add the message kind**

In `app/Enums/MessageKind.php`, add to the enum:

```php
    case Edit = 'edit';
```

- [ ] **Step 4: Write the form requests**

Create `app/Http/Requests/Agent/StoreRefinementRequest.php`:

```php
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefinementRequest extends FormRequest
{
    /**
     * The buffer arrives as three segments rather than a body and two offsets:
     * the browser counts UTF-16 code units and PHP counts codepoints, so any
     * post containing an emoji would slice in the wrong place.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'before' => ['present', 'string'],
            'selection' => ['required', 'string', 'min:1', 'max:1200'],
            'after' => ['present', 'string'],
            'instruction' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }
}
```

Create `app/Http/Requests/Agent/AcceptRefinementRequest.php`:

```php
<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class AcceptRefinementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'instruction' => ['required', 'string', 'min:2', 'max:500'],
            'before' => ['required', 'string', 'max:1200'],
            'after' => ['required', 'string', 'max:1200'],
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

Create `app/Http/Controllers/Agent/PostRefinementController.php`:

```php
<?php

namespace App\Http\Controllers\Agent;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AcceptRefinementRequest;
use App\Http\Requests\Agent\StoreRefinementRequest;
use App\Jobs\RefineDraftSpan;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostRefinementController extends Controller
{
    /**
     * Queue a rewrite of the selected fragment.
     */
    public function store(StoreRefinementRequest $request, string $currentTeam, Post $post): RedirectResponse
    {
        $this->authorizeTeam($request, $post);

        RefineDraftSpan::dispatch(
            $post,
            $request->validated('before'),
            $request->validated('selection'),
            $request->validated('after'),
            $request->validated('instruction'),
        );

        return back();
    }

    /**
     * Record an accepted rewrite as a turn on each side of the thread, so a
     * later request arrives with the edit history behind it.
     */
    public function accept(AcceptRefinementRequest $request, string $currentTeam, Post $post): RedirectResponse
    {
        $this->authorizeTeam($request, $post);

        $post->messages()->create([
            'role' => MessageRole::User,
            'kind' => MessageKind::Text,
            'body' => $request->validated('instruction'),
        ]);

        $post->messages()->create([
            'role' => MessageRole::Agent,
            'kind' => MessageKind::Edit,
            'body' => __('Edited one fragment.'),
            'payload' => [
                'before' => $request->validated('before'),
                'after' => $request->validated('after'),
            ],
        ]);

        return back();
    }

    /**
     * Abort with a 404 when the post belongs to another team.
     */
    protected function authorizeTeam(Request $request, Post $post): void
    {
        abort_unless($post->team_id === $request->user()->currentTeam?->id, 404);
    }
}
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, add the import alongside the other Agent controllers and these two lines directly after the `posts.alternatives.store` route:

```php
        Route::post('posts/{post}/refinements', [PostRefinementController::class, 'store'])->name('posts.refinements.store');
        Route::post('posts/{post}/refinements/accept', [PostRefinementController::class, 'accept'])->name('posts.refinements.accept');
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact --filter=PostRefinementTest`
Expected: PASS, 4 tests.

- [ ] **Step 8: Regenerate Wayfinder actions**

Run: `php artisan wayfinder:generate`
Expected: `resources/js/actions/App/Http/Controllers/Agent/PostRefinementController.ts` now exists.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/MessageKind.php app/Http/Requests/Agent app/Http/Controllers/Agent/PostRefinementController.php routes/web.php resources/js/actions tests/Feature/Agent/PostRefinementTest.php
git commit -m "feat: accept a fragment rewrite into the thread"
```

---

### Task 4: One owner for the post channel

**Files:**
- Create: `resources/js/hooks/use-post-channel.ts`
- Modify: `resources/js/hooks/use-post-stream.ts:47-51`
- Modify: `resources/js/pages/posts/show.tsx`

**Interfaces:**
- Produces: `usePostChannel(postId: number): void` — joins `posts.{id}` on mount, leaves on unmount. Every other hook on this channel adds listeners and removes them with `stopListening`, never `leave`.

**Why:** three features now share `posts.{id}`. `usePostStream` currently calls `echo().leave()` when drafting ends, which would tear the channel out from under the refinement and run streams.

- [ ] **Step 1: Write the hook**

Create `resources/js/hooks/use-post-channel.ts`:

```ts
import { useEffect } from 'react';
import echo from '@/echo';

/**
 * Owns the lifetime of the post's private channel.
 *
 * Drafting, refinement and the run theatre all listen on `posts.{id}`. Each of
 * them adds and removes its own listeners; leaving the channel belongs here, at
 * the page, or one feature unmounting would disconnect the others.
 */
export function usePostChannel(postId: number) {
    useEffect(() => {
        echo().private(`posts.${postId}`);

        return () => {
            echo().leave(`posts.${postId}`);
        };
    }, [postId]);
}
```

- [ ] **Step 2: Stop the drafting hook from leaving the channel**

In `resources/js/hooks/use-post-stream.ts`, replace the cleanup return with:

```ts
        return () => {
            window.clearInterval(fallback);
            channel.stopListening('.draft.chunk');
            channel.stopListening('.draft.completed');
            channel.stopListening('.draft.failed');
        };
```

- [ ] **Step 3: Call it from the page**

In `resources/js/pages/posts/show.tsx`, import the hook and call it above `usePostStream`:

```tsx
    usePostChannel(post.id);

    const { streamed, failed } = usePostStream(post.id, isDrafting);
```

- [ ] **Step 4: Verify types and lint**

Run: `npm run types:check && npm run lint:check`
Expected: both pass with no output.

- [ ] **Step 5: Commit**

```bash
git add resources/js/hooks/use-post-channel.ts resources/js/hooks/use-post-stream.ts resources/js/pages/posts/show.tsx
git commit -m "refactor: give the post channel a single owner"
```

---

### Task 5: The refinement hook

**Files:**
- Create: `resources/js/hooks/use-span-refinement.ts`
- Modify: `resources/js/types/agent.ts`

**Interfaces:**
- Consumes: `PostRefinementController` from Task 3, `usePostChannel` from Task 4.
- Produces:
  - `type SpanProposal = { before: string; selection: string; after: string; instruction: string; replacement: string; settled: boolean }` exported from `@/types/agent`.
  - `useSpanRefinement(postId: number, teamSlug: string): { proposal: SpanProposal | null; failed: string | null; request(before: string, selection: string, after: string, instruction: string): void; reject(): void }`.

- [ ] **Step 1: Add the type**

In `resources/js/types/agent.ts`, add `'edit'` to the `ThreadMessage['kind']` union and append:

```ts
export type SpanProposal = {
    before: string;
    selection: string;
    after: string;
    instruction: string;
    replacement: string;
    settled: boolean;
};
```

- [ ] **Step 2: Write the hook**

Create `resources/js/hooks/use-span-refinement.ts`:

```ts
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import PostRefinementController from '@/actions/App/Http/Controllers/Agent/PostRefinementController';
import echo from '@/echo';
import type { SpanProposal } from '@/types/agent';

/**
 * Nothing about a refinement is persisted until it is accepted, so there is no
 * server state to fall back to. A stream that never arrives is given this long
 * before the editor comes back with the failure on it.
 */
const TIMEOUT_MS = 30_000;

export function useSpanRefinement(postId: number, teamSlug: string) {
    const [proposal, setProposal] = useState<SpanProposal | null>(null);
    const [failed, setFailed] = useState<string | null>(null);
    const timer = useRef<number | null>(null);

    const clearTimer = useCallback(() => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }
    }, []);

    useEffect(() => {
        const channel = echo().private(`posts.${postId}`);

        channel.listen('.refine.chunk', (event: { delta: string }) => {
            setProposal((current) =>
                current && !current.settled
                    ? { ...current, replacement: current.replacement + event.delta }
                    : current,
            );
        });

        // The settled replacement is trusted over the accumulated deltas, so a
        // dropped chunk cannot leave a hole in the text the writer accepts.
        channel.listen('.refine.completed', (event: { replacement: string }) => {
            clearTimer();
            setProposal((current) =>
                current
                    ? { ...current, replacement: event.replacement, settled: true }
                    : current,
            );
        });

        channel.listen('.refine.failed', (event: { reason: string }) => {
            clearTimer();
            setProposal(null);
            setFailed(event.reason);
        });

        return () => {
            clearTimer();
            channel.stopListening('.refine.chunk');
            channel.stopListening('.refine.completed');
            channel.stopListening('.refine.failed');
        };
    }, [postId, clearTimer]);

    const request = useCallback(
        (before: string, selection: string, after: string, instruction: string) => {
            setFailed(null);
            setProposal({
                before,
                selection,
                after,
                instruction,
                replacement: '',
                settled: false,
            });

            router.post(
                PostRefinementController.store.url({
                    current_team: teamSlug,
                    post: postId,
                }),
                { before, selection, after, instruction },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onError: () => {
                        clearTimer();
                        setProposal(null);
                        setFailed('That selection could not be sent.');
                    },
                },
            );

            clearTimer();
            timer.current = window.setTimeout(() => {
                setProposal(null);
                setFailed('Bobby did not answer. Try that again.');
            }, TIMEOUT_MS);
        },
        [postId, teamSlug, clearTimer],
    );

    const reject = useCallback(() => {
        clearTimer();
        setProposal(null);
    }, [clearTimer]);

    return { proposal, failed, request, reject };
}
```

- [ ] **Step 3: Verify types and lint**

Run: `npm run types:check && npm run lint:check`
Expected: both pass. If the import of `PostRefinementController` fails to resolve, run `php artisan wayfinder:generate` (Task 3, Step 8).

- [ ] **Step 4: Commit**

```bash
git add resources/js/hooks/use-span-refinement.ts resources/js/types/agent.ts
git commit -m "feat: hold a fragment rewrite while it streams in"
```

---

### Task 6: Selecting, asking, and reviewing

**Files:**
- Create: `resources/js/components/agent/draft-editor.tsx`
- Create: `resources/js/components/agent/span-proposal.tsx`
- Modify: `resources/js/components/agent/draft-canvas.tsx`

**Interfaces:**
- Consumes: `useSpanRefinement` from Task 5, `PostRefinementController.accept` from Task 3.
- Produces:
  - `DraftEditor({ body, onChange, disabled, onAsk })` where `onAsk: (before: string, selection: string, after: string, instruction: string) => void`.
  - `SpanProposalView({ proposal, onAccept, onReject })` where both callbacks take no arguments.

- [ ] **Step 1: Write the editor**

Create `resources/js/components/agent/draft-editor.tsx`:

```tsx
import { Sparkles } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    body: string;
    onChange: (body: string) => void;
    disabled: boolean;
    onAsk: (
        before: string,
        selection: string,
        after: string,
        instruction: string,
    ) => void;
};

/**
 * The draft as an ordinary textarea, plus a prompt bar that appears while a
 * fragment is selected. The buffer is split here rather than sent as offsets:
 * the browser counts UTF-16 code units and the server counts codepoints, so an
 * emoji anywhere in the post would make the two disagree.
 */
export function DraftEditor({ body, onChange, disabled, onAsk }: Props) {
    const textarea = useRef<HTMLTextAreaElement>(null);
    const [span, setSpan] = useState<{ start: number; end: number } | null>(null);
    const [instruction, setInstruction] = useState('');

    const capture = () => {
        const element = textarea.current;

        if (!element) {
            return;
        }

        const { selectionStart: start, selectionEnd: end } = element;

        setSpan(end > start ? { start, end } : null);
    };

    const ask = () => {
        if (!span || instruction.trim().length < 2) {
            return;
        }

        onAsk(
            body.slice(0, span.start),
            body.slice(span.start, span.end),
            body.slice(span.end),
            instruction.trim(),
        );

        setInstruction('');
        setSpan(null);
    };

    return (
        <div className="space-y-2">
            <textarea
                ref={textarea}
                name="body"
                rows={8}
                value={body}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                onSelect={capture}
                onBlur={() => setInstruction('')}
                aria-label="The exact text to publish"
                className="min-h-40 w-full resize-none rounded-xl border border-input bg-transparent px-3 py-2 text-sm leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-60"
            />

            {span && !disabled && (
                <div className="space-y-2 rounded-xl border border-agent/40 bg-accent/40 px-3 py-2">
                    <p className="text-label text-muted-foreground">
                        <Sparkles className="mr-1 inline size-3" aria-hidden />
                        “{body.slice(span.start, span.end)}”
                    </p>

                    <div className="flex gap-2">
                        <input
                            value={instruction}
                            onChange={(event) => setInstruction(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    ask();
                                }
                            }}
                            placeholder="What is wrong with this?"
                            aria-label="What is wrong with the selected text"
                            className="h-8 w-full rounded-md border border-input bg-background px-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                        <Button
                            type="button"
                            size="sm"
                            disabled={instruction.trim().length < 2}
                            onClick={ask}
                        >
                            Ask
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 2: Write the proposal view**

Create `resources/js/components/agent/span-proposal.tsx`:

```tsx
import { Button } from '@/components/ui/button';
import type { SpanProposal } from '@/types/agent';

type Props = {
    proposal: SpanProposal;
    onAccept: () => void;
    onReject: () => void;
};

/**
 * The rewrite in place. Only the fragment changes; the text around it is
 * rendered exactly where it already was, so nothing on screen jumps while the
 * writer decides.
 */
export function SpanProposalView({ proposal, onAccept, onReject }: Props) {
    return (
        <div className="space-y-3">
            <p className="text-sm leading-relaxed whitespace-pre-wrap">
                {proposal.before}
                <del className="text-muted-foreground decoration-destructive/60">
                    {proposal.selection}
                </del>
                <ins className="bg-agent/15 text-agent-foreground no-underline">
                    {proposal.replacement}
                    {!proposal.settled && (
                        <span
                            className="ml-0.5 inline-block h-4 w-px animate-pulse bg-agent align-middle"
                            aria-hidden
                        />
                    )}
                </ins>
                {proposal.after}
            </p>

            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    disabled={!proposal.settled}
                    onClick={onAccept}
                >
                    Accept
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onReject}
                >
                    Reject
                </Button>
            </div>
        </div>
    );
}
```

If `text-agent-foreground` is not a token in `resources/css/app.css`, use `text-foreground` instead — check the file before assuming.

- [ ] **Step 3: Wire both into the canvas**

In `resources/js/components/agent/draft-canvas.tsx`:

Add the imports:

```tsx
import { router } from '@inertiajs/react';
import PostRefinementController from '@/actions/App/Http/Controllers/Agent/PostRefinementController';
import { DraftEditor } from '@/components/agent/draft-editor';
import { SpanProposalView } from '@/components/agent/span-proposal';
import { useSpanRefinement } from '@/hooks/use-span-refinement';
```

Inside the component, after the existing `streaming`/`shown` derivations:

```tsx
    const {
        proposal,
        failed: refineFailed,
        request,
        reject,
    } = useSpanRefinement(post.id, teamSlug);

    const accept = () => {
        if (!proposal) {
            return;
        }

        setBody(proposal.before + proposal.replacement + proposal.after);

        router.post(
            PostRefinementController.accept.url({
                current_team: teamSlug,
                post: post.id,
            }),
            {
                instruction: proposal.instruction,
                before: proposal.selection,
                after: proposal.replacement,
            },
            { preserveScroll: true },
        );

        reject();
    };
```

Replace the `<textarea>` inside the approval `<Form>` with:

```tsx
                            {proposal ? (
                                <SpanProposalView
                                    proposal={proposal}
                                    onAccept={accept}
                                    onReject={reject}
                                />
                            ) : (
                                <DraftEditor
                                    body={body}
                                    onChange={setBody}
                                    disabled={isDrafting}
                                    onAsk={request}
                                />
                            )}
```

Render the refinement failure beside the existing one:

```tsx
            {refineFailed && (
                <p className="text-meta text-destructive">{refineFailed}</p>
            )}
```

And disable approval while a proposal is open by extending the submit button's `disabled`:

```tsx
                                    disabled={
                                        processing ||
                                        proposal !== null ||
                                        body.trim().length < 2
                                    }
```

- [ ] **Step 4: Verify types and lint**

Run: `npm run types:check && npm run lint:check`
Expected: both pass.

- [ ] **Step 5: Check it in the browser**

Run `composer dev` if it is not already running. Open a post workspace with a draft, select a sentence, type an instruction, press enter. Expect the prompt bar to appear on selection, the replacement to stream into the highlighted span, Accept to splice it into the textarea, and Reject to leave the draft untouched.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/agent
git commit -m "feat: correct a draft one fragment at a time"
```

---

### Task 7: Edits in the thread

**Files:**
- Modify: `resources/js/components/agent/thread-column.tsx:49-57`
- Test: `tests/Feature/Agent/PostWorkspaceTest.php`

**Interfaces:**
- Consumes: `MessageKind::Edit` and its `{before, after}` payload from Task 3.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Agent/PostWorkspaceTest.php` (add any missing imports):

```php
test('an accepted edit reaches the workspace as a thread turn', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);

    $post->messages()->create([
        'role' => MessageRole::Agent,
        'kind' => MessageKind::Edit,
        'body' => 'Edited one fragment.',
        'payload' => ['before' => 'the old line', 'after' => 'the new line'],
    ]);

    $this->actingAs($user)
        ->get(route('posts.show', [$team->slug, $post]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('posts/show')
            ->where('messages.0.kind', 'edit')
            ->where('messages.0.payload.after', 'the new line')
            ->etc());
});
```

- [ ] **Step 2: Run the test to verify it passes or fails**

Run: `php artisan test --compact --filter=PostWorkspaceTest`
Expected: PASS — the controller already selects `kind` and `payload`, so this test guards the contract the component depends on rather than driving new backend code. If it fails, the message kind cast from Task 3 is missing.

- [ ] **Step 3: Render the edit turn**

In `resources/js/components/agent/thread-column.tsx`, extend the message map:

```tsx
            {messages.map((message) => (
                <ThreadMessage key={message.id} message={message}>
                    {message.kind === 'error' && (
                        <p className="text-meta text-destructive">
                            {message.payload?.reason as string}
                        </p>
                    )}

                    {message.kind === 'edit' && (
                        <div className="space-y-1 rounded-lg border border-border px-3 py-2 text-sm leading-relaxed">
                            <p className="text-muted-foreground line-through">
                                {message.payload?.before as string}
                            </p>
                            <p>{message.payload?.after as string}</p>
                        </div>
                    )}
                </ThreadMessage>
            ))}
```

- [ ] **Step 4: Verify types and lint**

Run: `npm run types:check && npm run lint:check`
Expected: both pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/agent/thread-column.tsx tests/Feature/Agent/PostWorkspaceTest.php
git commit -m "feat: keep accepted edits in the thread"
```

---

### Task 8: Remember what the agent clicked

**Files:**
- Create: `database/migrations/2026_08_04_000001_add_target_to_agent_steps_table.php`
- Modify: `app/Models/AgentStep.php` (PHPDoc only)
- Modify: `app/Agent/PublishRunner.php:162-190`
- Modify: `database/factories/AgentStepFactory.php`
- Test: `tests/Feature/Agent/PublishRunnerTest.php`

**Interfaces:**
- Produces: `agent_steps.target` — nullable string, the accessible name of the element the action targeted, or null.

**Why:** `agent_steps` stores the numeric `ref` but not what it pointed at, and the `Observation` that could resolve it is gone by the time anything is replayed. Without this the narration in Task 9 can only say "element 12".

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Agent/PublishRunnerTest.php`:

```php
test('a step remembers the element the action targeted', function (): void {
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 7, 'text' => null, 'key' => null, 'why' => 'open the composer'],
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'submitted'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'Visible in feed.', 'url' => null],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    expect($run->steps()->first()->target)->toBe('Start a post')
        ->and($run->steps()->whereNull('action')->first()?->target)->toBeNull();
});
```

`fakeDriver()`, `runnableRun()` and `composerPage()` are existing helpers at the top of that file; ref 7 is the "Start a post" button they script.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PublishRunnerTest`
Expected: FAIL — unknown column `target`.

- [ ] **Step 3: Create the migration**

Run: `php artisan make:migration add_target_to_agent_steps_table --no-interaction`

Fill it in:

```php
    public function up(): void
    {
        Schema::table('agent_steps', function (Blueprint $table): void {
            $table->string('target')->nullable()->after('ref');
        });
    }

    public function down(): void
    {
        Schema::table('agent_steps', function (Blueprint $table): void {
            $table->dropColumn('target');
        });
    }
```

- [ ] **Step 4: Record it**

In `app/Agent/PublishRunner.php`, inside `record()`, add to the `steps()->create([...])` array directly after `'ref' => $action?->ref,`:

```php
            'target' => $action?->ref === null
                ? null
                : $observation->element($action->ref)?->name,
```

Add `@property string|null $target` to the `AgentStep` docblock in `app/Models/AgentStep.php`, after `$ref`.

In `database/factories/AgentStepFactory.php`, add `'target' => null,` to the definition so existing tests keep passing unchanged.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact --filter=PublishRunnerTest`
Expected: PASS, including the new test.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Agent/PublishRunner.php app/Models/AgentStep.php database/factories/AgentStepFactory.php tests/Feature/Agent/PublishRunnerTest.php
git commit -m "feat: record what the agent was aiming at"
```

---

### Task 9: The narrator

**Files:**
- Create: `app/Agent/StepNarrator.php`
- Test: `tests/Feature/Agent/StepNarratorTest.php`

**Interfaces:**
- Consumes: `agent_steps.target` from Task 8.
- Produces: `StepNarrator::describe(AgentStep $step): string` — one sentence describing what the agent did. It describes the *attempt*; a gate reason or error is rendered alongside by the caller, not folded into this string.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Agent/StepNarratorTest.php`:

```php
<?php

use App\Agent\StepNarrator;
use App\Enums\GateVerdict;
use App\Enums\StepPhase;
use App\Models\AgentStep;

/**
 * The narrator reads attributes only, so an unsaved model is enough — and it
 * keeps these cases free of a run, a post and a team none of them need.
 *
 * @param  array<string, mixed>  $attributes
 */
function narratableStep(array $attributes): AgentStep
{
    return new AgentStep($attributes + ['phase' => StepPhase::Act]);
}

test('a click names what it clicked', function (): void {
    $narration = app(StepNarrator::class)->describe(narratableStep([
        'action' => ['action' => 'click', 'ref' => 12, 'text' => null, 'key' => null, 'why' => ''],
        'target' => 'Start a post',
    ]));

    expect($narration)->toBe('Clicked “Start a post”');
});

test('a click with no remembered target still reads as a sentence', function (): void {
    $narration = app(StepNarrator::class)->describe(narratableStep([
        'action' => ['action' => 'click', 'ref' => 12, 'text' => null, 'key' => null, 'why' => ''],
        'target' => null,
    ]));

    expect($narration)->toBe('Clicked the page');
});

test('typing is narrated without spilling the post into the timeline', function (): void {
    $narration = app(StepNarrator::class)->describe(narratableStep([
        'action' => ['action' => 'type', 'ref' => 8, 'text' => 'The whole approved post body.', 'key' => null, 'why' => ''],
        'target' => 'Text editor',
    ]));

    expect($narration)->toBe('Typed the approved text into “Text editor”');
});

test('a keypress names the key', function (): void {
    $narration = app(StepNarrator::class)->describe(narratableStep([
        'action' => ['action' => 'press', 'ref' => null, 'text' => null, 'key' => 'Enter', 'why' => ''],
        'target' => null,
    ]));

    expect($narration)->toBe('Pressed Enter');
});

test('every action type is narrated', function (string $action, string $expected): void {
    $narration = app(StepNarrator::class)->describe(narratableStep([
        'action' => ['action' => $action, 'ref' => null, 'text' => null, 'key' => null, 'why' => ''],
        'target' => null,
    ]));

    expect($narration)->toBe($expected);
})->with([
    ['scroll', 'Scrolled the page'],
    ['wait', 'Waited for the page to settle'],
    ['finish', 'Finished'],
    ['abort', 'Stopped'],
]);

test('a step with no action is narrated by its phase', function (): void {
    $narration = app(StepNarrator::class)->describe(narratableStep([
        'action' => null,
        'phase' => StepPhase::Observe,
        'target' => null,
    ]));

    expect($narration)->toBe('Looked at the page');
});

test('a denied step still narrates the attempt it was denied', function (): void {
    $narration = app(StepNarrator::class)->describe(narratableStep([
        'action' => ['action' => 'click', 'ref' => 3, 'text' => null, 'key' => null, 'why' => ''],
        'target' => 'Delete post',
        'gate_decision' => GateVerdict::Denied,
        'gate_reason' => 'outside the contract',
    ]));

    expect($narration)->toBe('Clicked “Delete post”');
});
```

Note the curly quotes — they match the typographic style already used in `step-row.tsx` and `runs/show.tsx`.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=StepNarratorTest`
Expected: FAIL — `Class "App\Agent\StepNarrator" not found`.

- [ ] **Step 3: Write the narrator**

Create `app/Agent/StepNarrator.php`:

```php
<?php

namespace App\Agent;

use App\Enums\ActionType;
use App\Enums\StepPhase;
use App\Models\AgentStep;

/**
 * Turns one recorded step into a line a person can read.
 *
 * It lives on the server rather than in the components so the live theatre and
 * the replay say exactly the same thing, from one tested function. It describes
 * the attempt only: a gate reason or an error is rendered beside it.
 */
final class StepNarrator
{
    /**
     * Describe what the agent did at this step.
     */
    public function describe(AgentStep $step): string
    {
        $action = $step->action;

        if ($action === null) {
            return $this->describePhase($step->phase);
        }

        $type = ActionType::tryFrom((string) ($action['action'] ?? ''));
        $target = $step->target;

        return match ($type) {
            ActionType::Click => $target === null
                ? 'Clicked the page'
                : 'Clicked “'.$target.'”',
            ActionType::Type => $target === null
                ? 'Typed the approved text'
                : 'Typed the approved text into “'.$target.'”',
            ActionType::Press => 'Pressed '.($action['key'] ?? 'a key'),
            ActionType::Scroll => 'Scrolled the page',
            ActionType::Wait => 'Waited for the page to settle',
            ActionType::Finish => 'Finished',
            ActionType::Abort => 'Stopped',
            default => 'Took an action nobody recognises',
        };
    }

    /**
     * Describe a step that carries no action of its own.
     */
    private function describePhase(StepPhase $phase): string
    {
        return match ($phase) {
            StepPhase::Observe => 'Looked at the page',
            StepPhase::Think => 'Worked out what to do next',
            StepPhase::Gate => 'Checked the action against the contract',
            StepPhase::Act => 'Acted on the page',
            StepPhase::Verify => 'Checked the post is live',
        };
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=StepNarratorTest`
Expected: PASS, 10 tests including the dataset rows.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Agent/StepNarrator.php tests/Feature/Agent/StepNarratorTest.php
git commit -m "feat: say what the agent did in plain words"
```

---

### Task 10: Broadcasting each step

**Files:**
- Create: `app/Events/RunStepRecorded.php`
- Modify: `app/Agent/PublishRunner.php` (constructor and `record()`)
- Modify: `app/Http/Controllers/Agent/PostController.php:57-60`
- Modify: `app/Http/Controllers/Agent/RunController.php:50-53`
- Modify: `resources/js/types/agent.ts`
- Test: `tests/Feature/Agent/PublishRunnerTest.php`

**Interfaces:**
- Consumes: `StepNarrator::describe()` from Task 9.
- Produces: `RunStepRecorded(int $postId, int $runId, string $runStatus, array $step)` broadcast as `run.step` on `private-posts.{postId}`. The `step` array shape is exactly the `StepData` type below, and both controllers now serve steps in that same shape.

```ts
export type StepData = {
    id: number;
    index: number;
    phase: 'observe' | 'think' | 'gate' | 'act' | 'verify';
    action: { action: string; ref: number | null; why: string } | null;
    narration: string;
    reasoning: string | null;
    gate_decision: 'allowed' | 'denied' | null;
    gate_reason: string | null;
    latency_ms: number | null;
    error: string | null;
    screenshot_path: string | null;
};
```

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Agent/PublishRunnerTest.php` (add `use App\Events\RunStepRecorded;` and `use Illuminate\Support\Facades\Event;` if absent):

```php
test('every step is broadcast as it is recorded', function (): void {
    Event::fake([RunStepRecorded::class]);
    fakeDriver();

    ActionPlannerAgent::fake([
        ['action' => 'click', 'ref' => 7, 'text' => null, 'key' => null, 'why' => 'open the composer'],
        ['action' => 'finish', 'ref' => null, 'text' => null, 'key' => null, 'why' => 'submitted'],
    ]);

    PublishVerifierAgent::fake([
        ['published' => true, 'evidence' => 'Visible in feed.', 'url' => null],
    ]);

    $run = app(PublishRunner::class)->run(runnableRun());

    Event::assertDispatchedTimes(RunStepRecorded::class, $run->steps()->count());
    Event::assertDispatched(RunStepRecorded::class, fn (RunStepRecorded $event): bool => $event->postId === $run->post_id
        && $event->runId === $run->id
        && $event->step['narration'] === 'Clicked “Start a post”');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=PublishRunnerTest`
Expected: FAIL — `Class "App\Events\RunStepRecorded" not found`.

- [ ] **Step 3: Write the event**

Create `app/Events/RunStepRecorded.php`:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One step of a publishing run, sent the moment it is persisted. It rides the
 * post's channel rather than one of its own: the workspace is already there.
 */
class RunStepRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $step
     */
    public function __construct(
        public readonly int $postId,
        public readonly int $runId,
        public readonly string $runStatus,
        public readonly array $step,
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
        return 'run.step';
    }
}
```

- [ ] **Step 4: Broadcast from the runner**

In `app/Agent/PublishRunner.php`, add `StepNarrator $narrator` to the promoted constructor properties (`private readonly StepNarrator $narrator`) and `use App\Events\RunStepRecorded;` to the imports.

In `record()`, capture the created step and broadcast it. Replace `$run->steps()->create([...]);` with `$step = $run->steps()->create([...]);` and add, after the `$run->forceFill(...)` line at the end of the method:

```php
        RunStepRecorded::dispatch($run->post_id, $run->id, $run->status->value, [
            'id' => $step->id,
            'index' => $step->index,
            'phase' => $step->phase->value,
            'action' => $step->action,
            'narration' => $this->narrator->describe($step),
            'reasoning' => $step->reasoning,
            'gate_decision' => $step->gate_decision?->value,
            'gate_reason' => $step->gate_reason,
            'latency_ms' => $step->latency_ms,
            'error' => $step->error,
            'screenshot_path' => $step->screenshot_path,
        ]);
```

- [ ] **Step 5: Serve the same shape from both controllers**

In `app/Http/Controllers/Agent/PostController.php`, inject the narrator into `show()` and map the deferred steps through it:

```php
    public function show(Request $request, string $currentTeam, Post $post, StepNarrator $narrator): Response
    {
        // ...
            'steps' => Inertia::defer(fn (): array => $run
                ? $run->steps()->get()->map(fn (AgentStep $step): array => [
                    'id' => $step->id,
                    'index' => $step->index,
                    'phase' => $step->phase->value,
                    'action' => $step->action,
                    'narration' => $narrator->describe($step),
                    'reasoning' => $step->reasoning,
                    'gate_decision' => $step->gate_decision?->value,
                    'gate_reason' => $step->gate_reason,
                    'latency_ms' => $step->latency_ms,
                    'error' => $step->error,
                    'screenshot_path' => $step->screenshot_path,
                ])->all()
                : []),
```

Apply the identical mapping in `RunController::show()`. Both now emit the `StepData` shape above, so the theatre and the replay consume one thing.

- [ ] **Step 6: Update the TypeScript type**

In `resources/js/types/agent.ts`, replace `StepData` with the shape given in the Interfaces block above — `narration` is required, `screenshot_path` is no longer optional.

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact --filter="PublishRunnerTest|RunReplayTest|PostWorkspaceTest"`
Expected: PASS. `RunReplayTest` asserts `props.steps.0.index`, which the mapping preserves.

- [ ] **Step 8: Format, typecheck and commit**

```bash
vendor/bin/pint --dirty --format agent
npm run types:check
git add app/Events/RunStepRecorded.php app/Agent/PublishRunner.php app/Http/Controllers/Agent resources/js/types/agent.ts tests/Feature/Agent/PublishRunnerTest.php
git commit -m "feat: send each step as it happens"
```

`npm run types:check` will fail here on `run-timeline.tsx` and `runs/show.tsx` if they read `screenshot_path` as optional. That is expected and fixed in Tasks 11 and 12 — if it blocks the commit, do Steps 1-3 of Task 11 before committing.

---

### Task 11: The theatre

**Files:**
- Create: `resources/js/hooks/use-run-stream.ts`
- Create: `resources/js/components/agent/step-narration.tsx`
- Create: `resources/js/components/agent/run-theatre.tsx`
- Delete: `resources/js/components/agent/run-timeline.tsx`
- Modify: `resources/js/pages/posts/show.tsx`

**Interfaces:**
- Consumes: `RunStepRecorded` (`run.step`) from Task 10, `usePostChannel` from Task 4, `StepData.narration`.
- Produces:
  - `useRunStream(postId: number, active: boolean, serverSteps: StepData[]): StepData[]` — server steps merged with streamed ones, deduplicated by `id`, ordered by `index`.
  - `StepNarration({ step, last })` — one narrated line.
  - `RunTheatre({ run, steps, teamSlug })` — same props `RunTimeline` took.

- [ ] **Step 1: Write the stream hook**

Create `resources/js/hooks/use-run-stream.ts`:

```ts
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import echo from '@/echo';
import type { StepData } from '@/types/agent';

const RELOAD_PROPS = ['run', 'steps', 'post'];

/**
 * Renders the run as it happens.
 *
 * The stream is enhancement only. Every step is persisted before it is
 * broadcast, so a page whose socket never connects still fills in from the
 * fallback reload — which is exactly the polling this replaced, only slower and
 * rarer.
 */
export function useRunStream(
    postId: number,
    active: boolean,
    serverSteps: StepData[],
): StepData[] {
    const [streamed, setStreamed] = useState<StepData[]>([]);

    useEffect(() => {
        if (!active) {
            return;
        }

        const channel = echo().private(`posts.${postId}`);

        channel.listen(
            '.run.step',
            (event: { runStatus: string; step: StepData }) => {
                setStreamed((current) => [...current, event.step]);

                // A terminal status is the one thing the stream cannot carry:
                // the post's own status and published URL change with it.
                if (event.runStatus !== 'running' && event.runStatus !== 'queued') {
                    router.reload({ only: RELOAD_PROPS });
                }
            },
        );

        const fallback = window.setInterval(() => {
            router.reload({ only: RELOAD_PROPS });
        }, 8000);

        return () => {
            window.clearInterval(fallback);
            channel.stopListening('.run.step');
        };
    }, [postId, active]);

    const byId = new Map<number, StepData>();

    for (const step of [...serverSteps, ...streamed]) {
        byId.set(step.id, step);
    }

    return [...byId.values()].sort((a, b) => a.index - b.index);
}
```

- [ ] **Step 2: Write the narration line**

Create `resources/js/components/agent/step-narration.tsx`:

```tsx
import type { StepData } from '@/types/agent';

/**
 * One thing the agent did, as a sentence. The vertical rule runs through every
 * line; a denied step breaks it, so an interrupted run is legible at a glance.
 */
export function StepNarration({
    step,
    last = false,
}: {
    step: StepData;
    last?: boolean;
}) {
    const denied = step.gate_decision === 'denied';
    const errored = Boolean(step.error);

    const node = denied
        ? 'border-gate bg-background'
        : errored
          ? 'border-destructive bg-background'
          : step.phase === 'verify'
            ? 'border-verified bg-verified'
            : 'border-agent bg-agent';

    return (
        <li className="relative space-y-1 pb-3 pl-8">
            {!last && (
                <span
                    aria-hidden
                    className={`absolute top-4 bottom-0 left-[0.4375rem] w-px ${
                        denied
                            ? 'border-l border-dashed border-gate bg-transparent'
                            : 'bg-border'
                    }`}
                />
            )}

            <span
                aria-hidden
                className={`absolute top-2 left-1 size-2.5 rounded-full border-2 ${node}`}
            />

            <p className="text-sm leading-relaxed">
                {step.narration}
                {step.latency_ms !== null && (
                    <span className="ml-2 font-mono text-[11px] text-muted-foreground tabular-nums">
                        {step.latency_ms}ms
                    </span>
                )}
            </p>

            {step.reasoning && (
                <p className="text-meta text-muted-foreground">
                    “{step.reasoning}”
                </p>
            )}

            {denied && step.gate_reason && (
                <p className="text-meta text-gate">blocked · {step.gate_reason}</p>
            )}

            {step.error && (
                <p className="text-meta text-destructive">{step.error}</p>
            )}
        </li>
    );
}
```

- [ ] **Step 3: Write the theatre**

Create `resources/js/components/agent/run-theatre.tsx`:

```tsx
import { Form } from '@inertiajs/react';
import RunController from '@/actions/App/Http/Controllers/Agent/RunController';
import { StepNarration } from '@/components/agent/step-narration';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { screenshot } from '@/routes/runs';
import type { RunData, StepData } from '@/types/agent';

const STATUS_VARIANT: Record<RunData['status'], 'gate' | 'verified' | 'live'> = {
    queued: 'live',
    running: 'live',
    published: 'verified',
    failed: 'gate',
    aborted: 'gate',
};

type Props = {
    run: RunData;
    steps: StepData[];
    teamSlug: string;
};

/**
 * What the agent is looking at, and what it is doing about it. The most recent
 * screenshot stays at the top so the page it is working on is never more than a
 * glance away, with the narration accumulating beneath it.
 */
export function RunTheatre({ run, steps, teamSlug }: Props) {
    const active = run.status === 'queued' || run.status === 'running';
    const latest = [...steps].reverse().find((step) => step.screenshot_path);

    return (
        <div className="space-y-4">
            <header className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                        run {run.id} · {run.step_count} steps
                    </span>
                    <Badge variant={STATUS_VARIANT[run.status]}>
                        {run.status}
                    </Badge>
                </div>

                {active && (
                    <Form
                        {...RunController.abort.form({
                            current_team: teamSlug,
                            run: run.id,
                        })}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button size="sm" variant="ghost" disabled={processing}>
                                Stop the agent
                            </Button>
                        )}
                    </Form>
                )}
            </header>

            {latest && (
                <img
                    src={screenshot([teamSlug, run.id, latest.id]).url}
                    alt={`What the agent saw at step ${latest.index}`}
                    className="w-full rounded-md border"
                />
            )}

            <ol>
                {steps.map((step, index) => (
                    <StepNarration
                        key={step.id}
                        step={step}
                        last={index === steps.length - 1}
                    />
                ))}

                {steps.length === 0 && (
                    <li className="text-meta text-muted-foreground">
                        <span className="mr-2 inline-block size-1.5 animate-pulse rounded-full bg-agent align-middle" />
                        Waiting for the first look at the page…
                    </li>
                )}
            </ol>

            {run.failure_reason && (
                <p className="rounded-lg bg-destructive/10 px-3 py-2 font-mono text-xs text-destructive">
                    {run.failure_reason}
                </p>
            )}
        </div>
    );
}
```

- [ ] **Step 4: Wire it into the page**

In `resources/js/pages/posts/show.tsx`:

Remove the `usePoll` import, the `usePoll` call and the `useEffect` that starts and stops it (lines 48-62), and the `RunTimeline` import. Add:

```tsx
import { RunTheatre } from '@/components/agent/run-theatre';
import { useRunStream } from '@/hooks/use-run-stream';
```

and, after the `active` derivation:

```tsx
    const liveSteps = useRunStream(post.id, active, steps ?? []);
```

Replace the `<RunTimeline ... />` element with:

```tsx
                                <RunTheatre
                                    run={run}
                                    steps={liveSteps}
                                    teamSlug={teamSlug}
                                />
```

- [ ] **Step 5: Delete the old timeline**

```bash
git rm resources/js/components/agent/run-timeline.tsx
```

Then `grep -rn "run-timeline\|RunTimeline" resources/js` and expect no results.

- [ ] **Step 6: Verify**

Run: `npm run types:check && npm run lint:check && php artisan test --compact --filter=PostWorkspaceTest`
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add resources/js
git commit -m "feat: watch the agent work instead of refreshing a table"
```

---

### Task 12: The narrated replay

**Files:**
- Modify: `resources/js/pages/runs/show.tsx:29-70`
- Test: `tests/Feature/Agent/RunReplayTest.php`

**Interfaces:**
- Consumes: `StepNarration` from Task 11, `StepData.narration` from Task 10.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Agent/RunReplayTest.php`:

```php
test('the replay is narrated rather than logged', function (): void {
    [$user, $team] = teamUser();
    $post = Post::factory()->create(['team_id' => $team->id]);
    $run = AgentRun::factory()->create(['post_id' => $post->id]);
    AgentStep::factory()->create([
        'agent_run_id' => $run->id,
        'index' => 0,
        'action' => ['action' => 'click', 'ref' => 4, 'text' => null, 'key' => null, 'why' => ''],
        'target' => 'Start a post',
    ]);

    partialReload($this->actingAs($user), route('runs.show', [$team->slug, $run]), 'runs/show', 'steps')
        ->assertOk()
        ->assertJsonPath('props.steps.0.narration', 'Clicked “Start a post”');
});
```

- [ ] **Step 2: Run the test to verify it passes**

Run: `php artisan test --compact --filter=RunReplayTest`
Expected: PASS — Task 10 already added the mapping to `RunController::show()`. If it fails, that mapping was missed.

- [ ] **Step 3: Narrate the replay**

In `resources/js/pages/runs/show.tsx`, replace the `StepRow` import with `StepNarration` and swap the list inside `Replay`:

```tsx
            <ol
                className="h-fit rounded-md border bg-card px-3 py-4"
                onMouseLeave={() => setCursor(0)}
            >
                {steps.map((step, index) => (
                    <div
                        key={step.id}
                        onMouseEnter={() => setCursor(index)}
                        onFocus={() => setCursor(index)}
                    >
                        <StepNarration
                            step={step}
                            last={index === steps.length - 1}
                        />
                    </div>
                ))}
            </ol>
```

Update the figure caption to lead with the narration rather than the raw phase:

```tsx
                    <figcaption className="space-y-1">
                        <p className="text-sm">{current.narration}</p>
                        <p className="font-mono text-xs text-muted-foreground">
                            step {String(current.index).padStart(2, '0')} ·{' '}
                            {current.phase}
                            {current.latency_ms !== null &&
                                ` · ${current.latency_ms}ms`}
                        </p>
                    </figcaption>
```

Guard the image against a step with no screenshot:

```tsx
                    {current.screenshot_path && (
                        <img
                            src={screenshot([teamSlug, runId, current.id]).url}
                            alt={`What the agent saw at step ${current.index}`}
                            className="w-full rounded-md border"
                        />
                    )}
```

- [ ] **Step 4: Check `StepRow` is still used**

Run: `grep -rn "step-row\|StepRow\|StepSkeleton" resources/js`
Expected: `StepSkeleton` is still imported by `posts/show.tsx` and `runs/show.tsx` as the deferred fallback, so `step-row.tsx` stays. If `StepRow` itself has no remaining callers, delete just that export and keep `StepSkeleton`.

- [ ] **Step 5: Verify**

Run: `npm run types:check && npm run lint:check && php artisan test --compact --filter=RunReplayTest`
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/runs/show.tsx resources/js/components/agent/step-row.tsx tests/Feature/Agent/RunReplayTest.php
git commit -m "feat: read the replay as prose"
```

---

### Task 13: Full verification

**Files:** none.

- [ ] **Step 1: Run the whole check suite**

Run: `composer ci:check`
Expected: eslint, prettier, tsc, phpstan and the full Pest suite all pass. Fix anything that does not — in particular phpstan may want a docblock on the `RunStepRecorded::$step` array shape or on the controller step mappings.

- [ ] **Step 2: Walk both features in the browser**

Run `composer dev` (this starts Vite, the queue worker and Reverb — all three are required; the stream is silent without Reverb and the queue).

1. Open a post workspace, select a sentence, ask for a change, watch it stream into the highlighted span, accept it. The thread gains your instruction and the before/after pair.
2. Reject one. The draft is untouched and the thread gains nothing.
3. Approve a post and watch the theatre: the screenshot updates, narrated lines accumulate, no 1.5s page flicker.
4. Open `/{team}/runs/{run}` afterwards and confirm the replay reads as prose and scrubbing still works.

- [ ] **Step 3: Commit anything the checks fixed**

```bash
git add -A
git commit -m "chore: satisfy the full check suite"
```
