<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are shown the landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

test('a signed in member sees the landing page with their current team', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('currentTeam.slug', $user->currentTeam->slug)
            ->where('auth.user.id', $user->id)
        );
});
