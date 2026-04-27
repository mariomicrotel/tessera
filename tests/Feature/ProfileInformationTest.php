<?php

use App\Models\User;

test('profile information can be updated', function () {
    $this->actingAs($user = User::factory()->create());

    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
    ])->put('/user/profile-information', [
        'name'  => 'Test Name',
        'email' => 'test@example.com',
    ]);

    expect($user->fresh())
        ->name->toEqual('Test Name')
        ->email->toEqual('test@example.com');
});
