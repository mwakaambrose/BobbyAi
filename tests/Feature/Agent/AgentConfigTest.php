<?php

test('agent configuration exposes the documented defaults', function (): void {
    expect(config('agent.step_cap'))->toBe(18)
        ->and(config('agent.run_timeout'))->toBe(1800)
        ->and(config('agent.browser_timeout'))->toBe(20)
        ->and(config('agent.inference_timeout'))->toBe(300)
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

test('a run has time for a full step cap of planning calls', function (): void {
    expect(config('agent.run_timeout'))
        ->toBeGreaterThan(config('agent.step_cap') * config('agent.browser_timeout'));
});

test('the queue does not hand a running publish job to a second worker', function (): void {
    expect(config('queue.connections.database.retry_after'))
        ->toBeGreaterThan(config('agent.run_timeout'));
});
