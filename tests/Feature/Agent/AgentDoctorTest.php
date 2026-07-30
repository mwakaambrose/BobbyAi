<?php

use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
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
        '*/health' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $this->artisan('agent:doctor')
        ->expectsOutputToContain('composer run browser')
        ->assertExitCode(1);
});

test('pruning removes screenshots for old runs only', function (): void {
    Storage::fake('local');

    $post = Post::factory()->create();
    $old = AgentRun::factory()->create(['post_id' => $post->id, 'created_at' => now()->subDays(40)]);
    $fresh = AgentRun::factory()->create(['post_id' => $post->id]);

    AgentStep::factory()->create([
        'agent_run_id' => $old->id,
        'screenshot_path' => 'agent-screenshots/'.$old->id.'/000.png',
    ]);
    AgentStep::factory()->create([
        'agent_run_id' => $fresh->id,
        'screenshot_path' => 'agent-screenshots/'.$fresh->id.'/000.png',
    ]);

    Storage::disk('local')->put('agent-screenshots/'.$old->id.'/000.png', 'x');
    Storage::disk('local')->put('agent-screenshots/'.$fresh->id.'/000.png', 'x');

    $this->artisan('agent:prune-screenshots')->assertExitCode(0);

    Storage::disk('local')->assertMissing('agent-screenshots/'.$old->id.'/000.png');
    Storage::disk('local')->assertExists('agent-screenshots/'.$fresh->id.'/000.png');
});
