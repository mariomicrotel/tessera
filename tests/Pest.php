<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

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
 * Crea o recupera una causale contabile generica per i test.
 * Richiede che 'current_tenant' sia impostato nel container.
 */
function getTestCausale(): \App\Models\CausaleContabile
{
    $tenant = app('current_tenant');

    return \App\Models\CausaleContabile::firstOrCreate(
        ['tenant_id' => $tenant->id, 'codice' => 'TEST-GEN'],
        [
            'descrizione' => 'Causale generica test',
            'tipo'        => \App\Models\CausaleContabile::TIPO_GENERICO,
            'di_sistema'  => false,
            'attivo'      => true,
        ]
    );
}

/**
 * Crea un MovimentoContabile valido per i test.
 * Gestisce automaticamente la causale_id e i campi obbligatori.
 */
function createTestMovimento(array $overrides = []): \App\Models\MovimentoContabile
{
    $causale = getTestCausale();

    return \App\Models\MovimentoContabile::create(array_merge([
        'tenant_id'          => app('current_tenant')->id,
        'anno_esercizio'     => 2024,
        'numero'             => rand(1, 99999),
        'data_registrazione' => '2024-06-01',
        'causale_id'         => $causale->id,
        'descrizione'        => 'Movimento test',
        'stato'              => \App\Models\MovimentoContabile::STATO_BOZZA,
    ], $overrides));
}

/**
 * Returns the current Inertia asset version for use in X-Inertia-Version header.
 * Avoids 409 responses from the HandleInertiaRequests middleware version check.
 */
function inertiaVersion(): string
{
    return app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()) ?? '';
}
