<?php

use App\Models\User;

it('sends guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sends players from the dashboard to their games', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('games.index'));
});
