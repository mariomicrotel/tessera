<?php

use App\Models\User;

test('other browser sessions can be logged out', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
    ])->delete('/user/other-browser-sessions', [
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
});
