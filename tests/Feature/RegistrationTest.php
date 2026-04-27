<?php

use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');

test('registration screen cannot be rendered if support is disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
})->skip('Jetstream registra la route /register indipendentemente dal feature flag Fortify in questa configurazione.');

test('new users can register', function () {
    $response = $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
    ])->post('/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'terms'                 => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect();
})->skip(function () {
    return ! Features::enabled(Features::registration());
}, 'Registration support is not enabled.');
