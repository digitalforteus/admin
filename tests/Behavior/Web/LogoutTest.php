<?php

use App\Models\User;
use App\Routes\Web;

test('a logout ends the session and regenerates its id and token', function (): void {
    $User = User::factory()->createOne();
    $this->actingAs($User);

    $sessionId = session()->getId();
    $token = session()->token();

    $this->get(Web::logout->value)
        ->assertRedirect(Web::home->value);

    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionId)
        ->and(session()->token())->not->toBe($token);
});

test('a guest asking to log out is sent home', function (): void {
    $this->get(Web::logout->value)
        ->assertRedirect(Web::home->value);

    $this->assertGuest();
});
