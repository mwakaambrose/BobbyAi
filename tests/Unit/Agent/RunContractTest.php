<?php

use App\Agent\Values\RunContract;
use App\Models\Post;

test('it is built from the approved body of a post', function (): void {
    $post = Post::factory()->approved('Exactly this copy.')->make();

    $contract = RunContract::forPost($post);

    expect($contract->approvedBody)->toBe('Exactly this copy.')
        ->and($contract->allowedHost)->toBe('www.linkedin.com')
        ->and($contract->stepBudget)->toBe(18)
        ->and($contract->allowedRoles)->toBe(['button', 'textbox', 'link']);
});

test('it round trips through an array', function (): void {
    $contract = new RunContract('www.linkedin.com', 'Copy.', ['button'], 5);

    expect(RunContract::fromArray($contract->toArray()))->toEqual($contract);
});
