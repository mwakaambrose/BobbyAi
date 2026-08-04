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
