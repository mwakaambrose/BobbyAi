<?php

namespace App\Http\Controllers\Agent;

use App\Enums\KindSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StorePostIdeaRequest;
use App\Http\Requests\Agent\UpdatePostIdeaRequest;
use App\Jobs\ClassifyPostIdea;
use App\Models\PostIdea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostIdeaController extends Controller
{
    /**
     * Add an idea to the backlog and queue its classification.
     *
     * The idea is usable the moment it is saved. Classification only affects
     * the order the sequencer picks in, so it happens in the background and
     * an unreachable model costs nothing but ordering quality.
     */
    public function store(StorePostIdeaRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $idea = $team->postIdeas()->create([
            'body' => $request->validated('body'),
            'position' => (int) $team->postIdeas()->max('position') + 1,
        ]);

        ClassifyPostIdea::dispatch($idea);

        return back();
    }

    /**
     * Edit an idea, or correct the kind Bobby gave it.
     */
    public function update(UpdatePostIdeaRequest $request, string $currentTeam, PostIdea $idea): RedirectResponse
    {
        $this->authorizeTeam($request, $idea);

        $attributes = $request->validated();

        if (array_key_exists('kind', $attributes)) {
            $attributes['kind_source'] = $attributes['kind'] === null ? null : KindSource::User;
        }

        $idea->forceFill($attributes)->save();

        return back();
    }

    /**
     * Remove an idea from the backlog.
     */
    public function destroy(Request $request, string $currentTeam, PostIdea $idea): RedirectResponse
    {
        $this->authorizeTeam($request, $idea);

        $idea->delete();

        return back();
    }

    /**
     * Reorder the backlog.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ideas = $team->postIdeas()->whereIn('id', $validated['ids'])->pluck('id')->all();

        DB::transaction(function () use ($validated, $ideas, $team): void {
            foreach ($validated['ids'] as $position => $id) {
                if (! in_array($id, $ideas, true)) {
                    continue;
                }

                $team->postIdeas()->whereKey($id)->update(['position' => $position]);
            }
        });

        return back();
    }

    /**
     * Abort with a 404 when the idea belongs to another team.
     */
    private function authorizeTeam(Request $request, PostIdea $idea): void
    {
        abort_unless($idea->team_id === $request->user()->currentTeam?->id, 404);
    }
}
