<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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
            $this->checkPerception(),
            $this->checkSidecar(),
        ];

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Verify the Ollama daemon answers.
     */
    private function checkOllama(): bool
    {
        if ($this->models() === null) {
            $this->error('Ollama unreachable at '.config('ai.providers.ollama.url').'. Start it with "ollama serve".');

            return false;
        }

        $this->info('Ollama reachable at '.config('ai.providers.ollama.url'));

        return true;
    }

    /**
     * Verify the configured model has been pulled.
     */
    private function checkModel(): bool
    {
        $model = config('agent.model');
        $names = $this->models();

        if ($names === null) {
            return false;
        }

        if (! $names->contains($model)) {
            $this->error('Model ['.$model.'] is not pulled. Run "ollama pull '.$model.'".');
            $this->line('  Available: '.($names->implode(', ') ?: 'none'));

            return false;
        }

        $this->info('Model ['.$model.'] available');

        return true;
    }

    /**
     * Report which perception mode the agent will use.
     */
    private function checkPerception(): bool
    {
        if (config('agent.perception') !== 'marks') {
            $this->line('Perception is "text". Screenshots are still recorded, but the model does not see them.');

            return true;
        }

        $this->line('Perception is "marks". If runs fail with unusable actions, set AGENT_PERCEPTION=text.');

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
            $this->error('Browser sidecar unreachable at '.config('agent.sidecar_url').'. Start it with "composer run browser".');

            return false;
        }

        if (! $healthy) {
            $this->error('Chromium is missing. Run "cd browser && npx playwright install chromium".');

            return false;
        }

        $this->info('Browser sidecar reachable at '.config('agent.sidecar_url'));

        return true;
    }

    /**
     * Get the models Ollama has pulled, or null when it cannot be reached.
     *
     * @return Collection<int, string>|null
     */
    private function models(): ?Collection
    {
        try {
            return (new Collection(
                Http::timeout(5)->get(config('ai.providers.ollama.url').'/api/tags')->throw()->json('models', [])
            ))->pluck('name');
        } catch (Throwable) {
            return null;
        }
    }
}
