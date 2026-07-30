<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a user who owns, and is currently switched into, a fresh team.
 *
 * @return array{0: \App\Models\User, 1: \App\Models\Team}
 */
function teamUser(): array
{
    $user = App\Models\User::factory()->create();
    $team = App\Models\Team::factory()->create();

    $team->members()->attach($user, ['role' => App\Enums\TeamRole::Owner->value]);
    $user->forceFill(['current_team_id' => $team->id])->save();

    return [$user->fresh(), $team];
}

/**
 * Perform an Inertia partial reload, which is how a deferred prop is fetched.
 */
function partialReload(object $test, string $url, string $component, string $only)
{
    return $test->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) (new App\Http\Middleware\HandleInertiaRequests)->version(request()),
        'X-Inertia-Partial-Data' => $only,
        'X-Inertia-Partial-Component' => $component,
    ]);
}
