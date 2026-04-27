<?php

/**
 * Test suite per Codici IVA CRUD (Fase 2 completamento).
 *
 * Copre:
 *  - codiciIndex: lista con filtro attivi
 *  - codiciCreate: form con tipi disponibili
 *  - codiciStore: creazione con validazione, unicità per tenant
 *  - codiciEdit: form edit, blocco su di_sistema
 *  - codiciUpdate: aggiornamento, blocco su di_sistema
 *  - codiciDestroy: eliminazione, blocco su di_sistema e su utilizzo fatture
 *  - Autorizzazione: utente anonimo e senza ruolo bloccati
 */

use App\Models\CodiceIva;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\RigaFatturaAttiva;
use App\Models\RigaFatturaPassiva;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Helper locale
// ─────────────────────────────────────────────────────────────────────────────

function withoutCodiciAuthMiddleware($test)
{
    return $test->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
        \App\Http\Middleware\RequireCooperativa::class,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'CodiciIva Test',
        'slug'              => 'codiciiva-' . uniqid(),
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    $this->userSenza = User::factory()->create(['email_verified_at' => now()]);
    $this->userSenza->tenants()->attach($this->tenant);

    // Supplier per test fatture passive
    $this->supplier = Supplier::create([
        'tenant_id'       => $this->tenant->id,
        'name'            => 'Fornitore Test',
        'ragione_sociale' => 'Fornitore Test S.r.l.',
        'email'           => 'fornitore@test.com',
    ]);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper locali — creazione fixture
// ─────────────────────────────────────────────────────────────────────────────

function codiceCustom(Tenant $tenant, array $overrides = []): CodiceIva
{
    return CodiceIva::create(array_merge([
        'tenant_id'                => $tenant->id,
        'codice'                   => 'CUSTOM-' . uniqid(),
        'descrizione'              => 'Codice custom test',
        'percentuale'              => 15.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => false,
        'attivo'                   => true,
    ], $overrides));
}

function codiceSistema(Tenant $tenant): CodiceIva
{
    return CodiceIva::create([
        'tenant_id'                => $tenant->id,
        'codice'                   => 'SIS-' . uniqid(),
        'descrizione'              => 'Codice di sistema',
        'percentuale'              => 22.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => true,
        'attivo'                   => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Index
// ─────────────────────────────────────────────────────────────────────────────

describe('codiciIndex', function () {

    it('restituisce lista codici IVA (Inertia JSON)', function () {
        $countPrima = CodiceIva::where('tenant_id', $this->tenant->id)->count();
        codiceCustom($this->tenant);
        codiceCustom($this->tenant);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('iva.codici.index', $this->tenant->slug));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);

        expect($page['component'])->toBe('Iva/Codici/Index');
        expect($page['props'])->toHaveKey('codici');
        // Almeno i 2 codici aggiunti + eventuale pre-seeding
        expect(count($page['props']['codici']['data']))->toBeGreaterThanOrEqual(2);
    });

    it('filtra solo codici attivi', function () {
        // Disattiva tutti i codici esistenti prima di aggiungere i nostri
        CodiceIva::where('tenant_id', $this->tenant->id)->update(['attivo' => false]);

        codiceCustom($this->tenant, ['attivo' => true]);
        codiceCustom($this->tenant, ['attivo' => false]);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('iva.codici.index', [$this->tenant->slug, 'attivi' => 1]));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);

        // Soltanto 1 attivo dopo il filtro
        expect($page['props']['codici']['data'])->toHaveCount(1);
        expect($page['props']['codici']['data'][0]['attivo'])->toBeTrue();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Create
// ─────────────────────────────────────────────────────────────────────────────

describe('codiciCreate', function () {

    it('restituisce form creazione con 6 tipi disponibili', function () {
        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('iva.codici.create', $this->tenant->slug));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);

        expect($page['component'])->toBe('Iva/Codici/Create');
        expect($page['props']['tipi'])->toHaveCount(6);
    });

    it('richiede ruolo admin/contabile', function () {
        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->userSenza)
            ->get(route('iva.codici.create', $this->tenant->slug));

        $response->assertStatus(403);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Store
// ─────────────────────────────────────────────────────────────────────────────

describe('codiciStore', function () {

    it('crea nuovo codice IVA valido', function () {
        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.codici.store', $this->tenant->slug), [
                'codice'                   => 'NUOV01',
                'descrizione'              => 'Codice Nuovo Test',
                'percentuale'              => 12.50,
                'tipo'                     => 'normale',
                'natura_sdi'               => 'N',
                'indetraibile_percentuale' => 0.00,
                'attivo'                   => true,
            ]);

        $response->assertRedirect();

        $codice = CodiceIva::where('codice', 'NUOV01')->first();
        expect($codice)->not->toBeNull();
        expect($codice->tenant_id)->toBe($this->tenant->id);
        expect((float) $codice->percentuale)->toBe(12.50);
        expect($codice->di_sistema)->toBeFalse();
    });

    it('rifiuta codice duplicato per lo stesso tenant', function () {
        codiceCustom($this->tenant, ['codice' => 'DUP01']);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.codici.store', $this->tenant->slug), [
                'codice'                   => 'DUP01',
                'descrizione'              => 'Duplicato',
                'percentuale'              => 10.00,
                'tipo'                     => 'normale',
                'natura_sdi'               => 'N',
                'indetraibile_percentuale' => 0.00,
                'attivo'                   => true,
            ]);

        $response->assertSessionHasErrors('codice');
    });

    it('valida campi obbligatori mancanti', function () {
        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.codici.store', $this->tenant->slug), []);

        $response->assertSessionHasErrors(['codice', 'descrizione', 'percentuale', 'tipo', 'natura_sdi']);
    });

    it('valida percentuale fuori range', function () {
        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.codici.store', $this->tenant->slug), [
                'codice'                   => 'INVALID',
                'descrizione'              => 'Test',
                'percentuale'              => 150.00,
                'tipo'                     => 'normale',
                'natura_sdi'               => 'N',
                'indetraibile_percentuale' => 0.00,
                'attivo'                   => true,
            ]);

        $response->assertSessionHasErrors('percentuale');
    });

    it('valida natura_sdi non valida', function () {
        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.codici.store', $this->tenant->slug), [
                'codice'                   => 'INVALID',
                'descrizione'              => 'Test',
                'percentuale'              => 10.00,
                'tipo'                     => 'normale',
                'natura_sdi'               => 'X',
                'indetraibile_percentuale' => 0.00,
                'attivo'                   => true,
            ]);

        $response->assertSessionHasErrors('natura_sdi');
    });

    it('richiede ruolo admin/contabile per store', function () {
        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->userSenza)
            ->post(route('iva.codici.store', $this->tenant->slug), [
                'codice'                   => 'NOAUTH',
                'descrizione'              => 'No auth',
                'percentuale'              => 10.00,
                'tipo'                     => 'normale',
                'natura_sdi'               => 'N',
                'indetraibile_percentuale' => 0.00,
                'attivo'                   => true,
            ]);

        $response->assertStatus(403);
        expect(CodiceIva::where('codice', 'NOAUTH')->exists())->toBeFalse();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Edit
// ─────────────────────────────────────────────────────────────────────────────

describe('codiciEdit', function () {

    it('restituisce form edit per codice custom', function () {
        $codice = codiceCustom($this->tenant);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('iva.codici.edit', [$this->tenant->slug, $codice->id]));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);

        expect($page['component'])->toBe('Iva/Codici/Edit');
        expect($page['props']['codice']['id'])->toBe($codice->id);
        expect($page['props']['tipi'])->toHaveCount(6);
    });

    it('blocca edit su codice di sistema con 403', function () {
        $codice = codiceSistema($this->tenant);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->get(route('iva.codici.edit', [$this->tenant->slug, $codice->id]));

        $response->assertStatus(403);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Update
// ─────────────────────────────────────────────────────────────────────────────

describe('codiciUpdate', function () {

    it('aggiorna codice custom correttamente', function () {
        $codice = codiceCustom($this->tenant, ['codice' => 'UPD01', 'descrizione' => 'Vecchio']);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->put(route('iva.codici.update', [$this->tenant->slug, $codice->id]), [
                'codice'                   => 'UPD01',
                'descrizione'              => 'Nuovo Descrizione',
                'percentuale'              => 18.00,
                'tipo'                     => 'normale',
                'natura_sdi'               => 'N',
                'indetraibile_percentuale' => 5.00,
                'attivo'                   => true,
            ]);

        $response->assertRedirect();

        $codice->refresh();
        expect($codice->descrizione)->toBe('Nuovo Descrizione');
        expect((float) $codice->percentuale)->toBe(18.00);
        expect((float) $codice->indetraibile_percentuale)->toBe(5.00);
    });

    it('blocca update su codice di sistema con 403', function () {
        $codice = codiceSistema($this->tenant);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->put(route('iva.codici.update', [$this->tenant->slug, $codice->id]), [
                'codice'                   => $codice->codice,
                'descrizione'              => 'Tentativo modifica',
                'percentuale'              => 22.00,
                'tipo'                     => 'normale',
                'natura_sdi'               => 'N',
                'indetraibile_percentuale' => 0.00,
                'attivo'                   => true,
            ]);

        $response->assertStatus(403);
        $codice->refresh();
        expect($codice->descrizione)->toBe('Codice di sistema'); // non modificato
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Destroy
// ─────────────────────────────────────────────────────────────────────────────

describe('codiciDestroy', function () {

    it('elimina codice custom non usato', function () {
        $codice = codiceCustom($this->tenant);
        $id = $codice->id;

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->delete(route('iva.codici.destroy', [$this->tenant->slug, $codice->id]));

        $response->assertRedirect();

        expect(CodiceIva::find($id))->toBeNull();
    });

    it('blocca eliminazione codice di sistema con 403', function () {
        $codice = codiceSistema($this->tenant);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->delete(route('iva.codici.destroy', [$this->tenant->slug, $codice->id]));

        $response->assertStatus(403);
        expect(CodiceIva::find($codice->id))->not->toBeNull();
    });

    it('blocca eliminazione codice usato in fatture passive', function () {
        $codice = codiceCustom($this->tenant);

        $fattura = FatturaPassiva::create([
            'tenant_id'          => $this->tenant->id,
            'supplier_id'        => $this->supplier->id,
            'numero_fattura'     => 'FP-TEST-001',
            'data_fattura'       => now(),
            'data_ricezione'     => now(),
            'data_registrazione' => now(),
            'data_scadenza'      => now()->addDays(30),
            'stato_pagamento'    => 'da_pagare',
        ]);

        RigaFatturaPassiva::create([
            'tenant_id'       => $this->tenant->id,
            'fattura_passiva_id' => $fattura->id,
            'codice_iva_id'   => $codice->id,
            'descrizione'     => 'Riga test',
            'quantita'        => 1,
            'prezzo_unitario' => 100,
            'imponibile'      => 100,
            'iva'             => 15,
            'totale'          => 115,
        ]);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->delete(route('iva.codici.destroy', [$this->tenant->slug, $codice->id]));

        $response->assertSessionHas('flash.type', 'error');
        expect(CodiceIva::find($codice->id))->not->toBeNull();
    });

    it('blocca eliminazione codice usato in fatture attive', function () {
        $codice = codiceCustom($this->tenant);

        $fattura = FatturaAttiva::create([
            'tenant_id'      => $this->tenant->id,
            'anno'           => 2026,
            'progressivo'    => 1,
            'numero_fattura' => 'FA-TEST-001',
            'data_fattura'   => now(),
            'stato'          => 'emessa',
        ]);

        RigaFatturaAttiva::create([
            'tenant_id'         => $this->tenant->id,
            'fattura_attiva_id' => $fattura->id,
            'codice_iva_id'     => $codice->id,
            'descrizione'       => 'Riga test',
            'quantita'          => 1,
            'prezzo_unitario'   => 100,
            'imponibile'        => 100,
            'iva'               => 15,
            'totale'            => 115,
        ]);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->user)
            ->delete(route('iva.codici.destroy', [$this->tenant->slug, $codice->id]));

        $response->assertSessionHas('flash.type', 'error');
        expect(CodiceIva::find($codice->id))->not->toBeNull();
    });

    it('richiede ruolo admin per eliminazione', function () {
        $codice = codiceCustom($this->tenant);

        $response = withoutCodiciAuthMiddleware($this)
            ->actingAs($this->userSenza)
            ->delete(route('iva.codici.destroy', [$this->tenant->slug, $codice->id]));

        $response->assertStatus(403);
        expect(CodiceIva::find($codice->id))->not->toBeNull();
    });

});
