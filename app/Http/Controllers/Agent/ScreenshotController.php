<?php

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
    public function __invoke(Request $request, string $currentTeam, AgentRun $run, AgentStep $step): StreamedResponse
    {
        abort_unless($run->post->team_id === $request->user()->currentTeam?->id, 404);
        abort_unless($step->agent_run_id === $run->id && $step->screenshot_path, 404);

        $disk = Storage::disk(config('agent.screenshot_disk'));

        abort_unless($disk->exists($step->screenshot_path), 404);

        return $disk->response($step->screenshot_path, headers: ['content-type' => 'image/png']);
    }
}
