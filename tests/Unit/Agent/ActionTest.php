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
