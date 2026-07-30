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
