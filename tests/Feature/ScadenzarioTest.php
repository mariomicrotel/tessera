<?php

/**
 * Test suite per Scadenza e ScadenzaController.
 *
 * Copre:
 *  - Scadenza model: CRUD, scopes (aperte, scadute, inScadenza, perTipo)
 *  - Accessor is_scaduta e giorni_alla_scadenza
 *  - ScadenzaController::store (crea, valida)
 *  - ScadenzaController::update (modifica)
 *  - ScadenzaController::destroy (elimina)
 *  - ScadenzaController::markPagata (stato, idempotenza)
 *  - ScadenzaController::riprendi (anno precedente, idempotenza)
 *  - ScadenzaController::fornitori (view fatture passive con data_scadenza)
 *  - Middleware role:admin,contabile
 */

use App\Models\CodiceIva;
use App\Models\FatturaPassiva;
use App\Models\Role;
use App\Models\Scadenza;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FatturaPassivaService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Scadenzario Test',
        'slug'              => 'ets-scad-test-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    // Admin user
    $roleAdmin = Role::where('name', 'admin')->first();
    $this->user = User::factory()->create([
        'email'             => 'admin@scad.it',
        'email_verified_at' => now(),
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    // Utente senza ruolo
    $this->userSenza = User::factory()->create([
        'email'             => 'nessuno@scad.it',
        'email_verified_at' => now(),
    ]);
    $this->userSenza->tenants()->attach($this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// Scadenza model — scopes e accessor
// ─────────────────────────────────────────────────────────────────────────────

describe('Scadenza model → scopes e accessor', function () {

    it('scope aperte filtra solo state aperte', function () {
        Scadenza::create([
            'tipo'          => 'fiscale',
            'descrizione'   => 'IVA Q1',
            'data_scadenza' => Carbon::today()->addDays(10),
            'stato'         => Scadenza::STATO_APERTA,
        ]);
        Scadenza::create([
            'tipo'          => 'fiscale',
            'descrizione'   => 'IVA Q2 pagata',
            'data_scadenza' => Carbon::today()->addDays(40),
            'stato'         => Scadenza::STATO_PAGATA,
        ]);

        expect(Scadenza::aperte()->count())->toBe(1);
    });

    it('scope scadute filtra aperte con data passata', function () {
        Scadenza::create([
            'tipo'          => 'contributi',
            'descrizione'   => 'INPS scaduto',
            'data_scadenza' => Carbon::today()->subDays(5),
            'stato'         => Scadenza::STATO_APERTA,
        ]);
        Scadenza::create([
            'tipo'          => 'contributi',
            'descrizione'   => 'INPS futuro',
            'data_scadenza' => Carbon::today()->addDays(20),
            'stato'         => Scadenza::STATO_APERTA,
        ]);

        expect(Scadenza::scadute()->count())->toBe(1);
    });

    it('scope inScadenza filtra aperte entro 30 giorni', function () {
        Scadenza::create([
            'tipo'          => 'affitto',
            'descrizione'   => 'Affitto prossimo',
            'data_scadenza' => Carbon::today()->addDays(15),
            'stato'         => Scadenza::STATO_APERTA,
        ]);
        Scadenza::create([
            'tipo'          => 'affitto',
            'descrizione'   => 'Affitto lontano',
            'data_scadenza' => Carbon::today()->addDays(60),
            'stato'         => Scadenza::STATO_APERTA,
        ]);

        expect(Scadenza::inScadenza(30)->count())->toBe(1);
    });

    it('accessor is_scaduta true per scadenza aperta con data passata', function () {
        $s = Scadenza::create([
            'tipo'          => 'altra',
            'descrizione'   => 'Passata',
            'data_scadenza' => Carbon::today()->subDay(),
            'stato'         => Scadenza::STATO_APERTA,
        ]);

        expect($s->is_scaduta)->toBeTrue();
    });

    it('accessor is_scaduta false per scadenza pagata', function () {
        $s = Scadenza::create([
            'tipo'          => 'altra',
            'descrizione'   => 'Pagata ma data passata',
            'data_scadenza' => Carbon::today()->subDay(),
            'stato'         => Scadenza::STATO_PAGATA,
        ]);

        expect($s->is_scaduta)->toBeFalse();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ScadenzaController → store
// ─────────────────────────────────────────────────────────────────────────────

describe('ScadenzaController → store', function () {

    it('crea scadenza valida con redirect a index', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('scadenze.store', $this->tenant), [
                'tipo'          => 'fiscale',
                'descrizione'   => 'Pagamento IVA mensile',
                'importo'       => 1500.00,
                'data_scadenza' => Carbon::today()->addMonth()->toDateString(),
                'ricorrente'    => true,
            ]);

        $response->assertRedirect(route('scadenze.index', $this->tenant));

        $s = Scadenza::where('descrizione', 'Pagamento IVA mensile')->first();
        expect($s)->not->toBeNull()
            ->and($s->stato)->toBe(Scadenza::STATO_APERTA)
            ->and((float) $s->importo)->toBe(1500.0)
            ->and($s->ricorrente)->toBeTrue();
    });

    it('store fallisce senza descrizione', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('scadenze.store', $this->tenant), [
                'tipo'          => 'fiscale',
                'data_scadenza' => Carbon::today()->addDay()->toDateString(),
            ]);

        $response->assertSessionHasErrors('descrizione');
        expect(Scadenza::count())->toBe(0);
    });

    it('store fallisce senza ruolo admin/contabile', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSenza)
            ->post(route('scadenze.store', $this->tenant), [
                'tipo'          => 'altra',
                'descrizione'   => 'Non autorizzato',
                'data_scadenza' => Carbon::today()->addDay()->toDateString(),
            ])
            ->assertForbidden();

        expect(Scadenza::count())->toBe(0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ScadenzaController → update e destroy
// ─────────────────────────────────────────────────────────────────────────────

describe('ScadenzaController → update e destroy', function () {

    beforeEach(function () {
        $this->scadenza = Scadenza::create([
            'tipo'          => 'affitto',
            'descrizione'   => 'Canone affitto',
            'importo'       => 800.00,
            'data_scadenza' => Carbon::today()->addDays(5),
            'stato'         => Scadenza::STATO_APERTA,
        ]);
    });

    it('update modifica descrizione e importo', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->put(route('scadenze.update', [$this->tenant, $this->scadenza]), [
                'tipo'          => 'affitto',
                'descrizione'   => 'Canone aggiornato',
                'importo'       => 900.00,
                'data_scadenza' => Carbon::today()->addDays(5)->toDateString(),
                'stato'         => Scadenza::STATO_APERTA,
            ])
            ->assertRedirect(route('scadenze.index', $this->tenant));

        $this->scadenza->refresh();
        expect($this->scadenza->descrizione)->toBe('Canone aggiornato')
            ->and((float) $this->scadenza->importo)->toBe(900.0);
    });

    it('destroy elimina la scadenza', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->delete(route('scadenze.destroy', [$this->tenant, $this->scadenza]))
            ->assertRedirect(route('scadenze.index', $this->tenant));

        expect(Scadenza::find($this->scadenza->id))->toBeNull();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ScadenzaController → markPagata
// ─────────────────────────────────────────────────────────────────────────────

describe('ScadenzaController → markPagata', function () {

    beforeEach(function () {
        $this->scadenza = Scadenza::create([
            'tipo'          => 'fiscale',
            'descrizione'   => 'IVA da pagare',
            'importo'       => 2000.00,
            'data_scadenza' => Carbon::today(),
            'stato'         => Scadenza::STATO_APERTA,
        ]);
    });

    it('marca una scadenza come pagata con data_pagamento', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('scadenze.mark-pagata', [$this->tenant, $this->scadenza]), [
                'data_pagamento' => Carbon::today()->toDateString(),
            ]);

        $this->scadenza->refresh();
        expect($this->scadenza->stato)->toBe(Scadenza::STATO_PAGATA)
            ->and($this->scadenza->data_pagamento->format('Y-m-d'))->toBe(Carbon::today()->toDateString());
    });

    it('markPagata su scadenza già pagata non cambia nulla (idempotente)', function () {
        $this->scadenza->update([
            'stato'          => Scadenza::STATO_PAGATA,
            'data_pagamento' => Carbon::yesterday(),
        ]);

        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('scadenze.mark-pagata', [$this->tenant, $this->scadenza]), [
                'data_pagamento' => Carbon::today()->toDateString(),
            ]);

        $this->scadenza->refresh();
        // La data di pagamento rimane quella originale (ieri)
        expect($this->scadenza->data_pagamento->format('Y-m-d'))->toBe(Carbon::yesterday()->toDateString());
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ScadenzaController → riprendi
// ─────────────────────────────────────────────────────────────────────────────

describe('ScadenzaController → riprendi', function () {

    it('riprende scadenze ricorrenti dell\'anno precedente', function () {
        $annoPrecedente = now()->year - 1;

        // Scadenza ricorrente anno precedente
        Scadenza::create([
            'tipo'          => 'fiscale',
            'descrizione'   => 'IVA annuale ricorrente',
            'importo'       => 1200.00,
            'data_scadenza' => Carbon::create($annoPrecedente, 6, 16),
            'stato'         => Scadenza::STATO_PAGATA,
            'ricorrente'    => true,
        ]);

        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('scadenze.riprendi', $this->tenant));

        // Deve esistere la nuova scadenza per l'anno corrente
        $nuova = Scadenza::where('descrizione', 'IVA annuale ricorrente')
            ->whereYear('data_scadenza', now()->year)
            ->first();

        expect($nuova)->not->toBeNull()
            ->and($nuova->stato)->toBe(Scadenza::STATO_APERTA)
            ->and($nuova->ricorrente)->toBeTrue()
            ->and($nuova->origine_anno_precedente)->not->toBeNull();
    });

    it('riprendi è idempotente: non crea duplicati', function () {
        $annoPrecedente = now()->year - 1;

        Scadenza::create([
            'tipo'          => 'contributi',
            'descrizione'   => 'INPS ricorrente',
            'importo'       => 500.00,
            'data_scadenza' => Carbon::create($annoPrecedente, 3, 16),
            'stato'         => Scadenza::STATO_PAGATA,
            'ricorrente'    => true,
        ]);

        // Prima chiamata
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('scadenze.riprendi', $this->tenant));

        // Seconda chiamata
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('scadenze.riprendi', $this->tenant));

        expect(
            Scadenza::where('descrizione', 'INPS ricorrente')
                ->whereYear('data_scadenza', now()->year)
                ->count()
        )->toBe(1);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ScadenzaController → fornitori
// ─────────────────────────────────────────────────────────────────────────────

describe('ScadenzaController → fornitori', function () {

    it('restituisce fatture passive con data_scadenza in stato da_pagare', function () {
        $supplier = Supplier::create([
            'name'          => 'Fornitore Scad Test',
            'ragione_sociale' => 'Fornitore Scad Test SRL',
            'partita_iva'   => '11223344556',
            'attivo'        => true,
        ]);
        $codiceIva = CodiceIva::create([
            'codice'      => 'IVA22T',
            'descrizione' => 'IVA 22% test',
            'percentuale' => 22.00,
            'tipo'        => 'acquisto',
            'indetraibile_percentuale' => 0,
            'attivo'      => true,
            'di_sistema'  => false,
        ]);

        $svc = app(FatturaPassivaService::class);
        $svc->registra(
            testata: [
                'supplier_id'        => $supplier->id,
                'numero_fattura'     => 'FT-SCAD-001',
                'data_fattura'       => '2025-01-10',
                'data_registrazione' => '2025-01-10',
                'data_scadenza'      => Carbon::today()->subDays(3)->toDateString(),
                'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
            ],
            righe: [[
                'codice_iva_id'   => $codiceIva->id,
                'descrizione'     => 'Fornitura test',
                'quantita'        => 1,
                'prezzo_unitario' => 500.00,
            ]]
        );

        // Forziamo una risposta Inertia JSON pura (senza rendering Blade/Vite),
        // altrimenti @vite() in app.blade.php cerca i file Vue nel manifest
        // e i nuovi componenti non ancora compilati causano un 500.
        $inertiaVersion = md5_file(public_path('build/manifest.json')) ?? '';

        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->withHeaders([
                'X-Inertia'         => 'true',
                'X-Inertia-Version' => $inertiaVersion,
            ])
            ->get(route('scadenze.fornitori', $this->tenant));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Scadenze/Fornitori')
            ->has('fatture.data', 1)
        );
    });

});
