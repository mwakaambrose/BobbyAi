<?php

namespace App\Console\Commands;

use App\Models\AgentRun;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
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
            ->chunkById(100, function (Collection $runs) use ($disk, &$deleted): void {
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
