<?php

use App\Models\CodiceIva;
use App\Models\Customer;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\RigaFatturaAttiva;
use App\Models\RigaFatturaPassiva;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'CodiciIva Test',
        'slug'              => 'codici-test-' . uniqid(),
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();
    $roleContabile = Role::where('name', 'contabile')->first();

    $this->user = User::factory()->create([
        'email' => 'test-codici@example.com',
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    // Crea supplier per i test di fatturazione
    $this->supplier = Supplier::create([
        'tenant_id'       => $this->tenant->id,
        'name'            => 'Test Supplier',
        'ragione_sociale' => 'Test Supplier S.r.l.',
        'email'           => 'supplier@test.com',
    ]);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ──────────────────────────────────────────────────────────────────────
// Index - Elenco Codici IVA
// ──────────────────────────────────────────────────────────────────────

test('codici_index_page_loads_without_filters', function () {
    CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'TEST01',
        'descrizione'              => 'IVA Test',
        'percentuale'              => 22.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'attivo'                   => true,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('iva.codici.index', ['tenant' => $this->tenant->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Iva/Codici/Index')
            ->has('codici.data')
            ->has('codici.links')
        );
});

test('codici_index_filters_by_attivi', function () {
    CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'ACTIVE',
        'descrizione'              => 'Active Code',
        'percentuale'              => 22.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'attivo'                   => true,
    ]);

    CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'INACTIVE',
        'descrizione'              => 'Inactive Code',
        'percentuale'              => 10.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'attivo'                   => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('iva.codici.index', ['tenant' => $this->tenant->slug, 'attivi' => 1]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('codici.data')
            ->where('filters.attivi', 1)
        );
});

test('codici_create_page_loads', function () {
    $response = $this->actingAs($this->user)
        ->get(route('iva.codici.create', ['tenant' => $this->tenant->slug]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Iva/Codici/Create')
            ->has('tipi', 6)
        );
});

test('store_creates_new_codice_iva', function () {
    $response = $this->actingAs($this->user)
        ->post(route('iva.codici.store', ['tenant' => $this->tenant->slug]), [
            'codice'                   => 'CUSTOM',
            'descrizione'              => 'Custom IVA Code',
            'percentuale'              => 15.00,
            'tipo'                     => 'normale',
            'natura_sdi'               => 'N',
            'indetraibile_percentuale' => 0.00,
            'attivo'                   => true,
        ]);

    $response->assertRedirect(route('iva.codici.index', ['tenant' => $this->tenant->slug]))
        ->assertSessionHas('flash.type', 'success');

    expect(CodiceIva::where('codice', 'CUSTOM')->first())
        ->not->toBeNull()
        ->descrizione->toBe('Custom IVA Code')
        ->percentuale->toBe(15.00);
});

test('store_validates_required_fields', function () {
    $response = $this->actingAs($this->user)
        ->post(route('iva.codici.store', ['tenant' => $this->tenant->slug]), []);

    $response->assertSessionHasErrors(['codice', 'descrizione', 'percentuale', 'tipo', 'natura_sdi']);
});

test('store_validates_unique_codice_per_tenant', function () {
    CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'DUP',
        'descrizione'              => 'Duplicate Code',
        'percentuale'              => 15.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('iva.codici.store', ['tenant' => $this->tenant->slug]), [
            'codice'                   => 'DUP',
            'descrizione'              => 'Another Code',
            'percentuale'              => 20.00,
            'tipo'                     => 'normale',
            'natura_sdi'               => 'N',
            'indetraibile_percentuale' => 0.00,
            'attivo'                   => true,
        ]);

    $response->assertSessionHasErrors('codice');
});

test('edit_page_loads_for_custom_codice', function () {
    $codice = CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'EDITABLE',
        'descrizione'              => 'Editable Code',
        'percentuale'              => 15.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('iva.codici.edit', ['tenant' => $this->tenant->slug, 'codiceIva' => $codice]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Iva/Codici/Edit')
            ->has('codice')
            ->has('tipi', 6)
        );
});

test('edit_blocks_di_sistema_codes', function () {
    $codice = CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'SISTEMA',
        'descrizione'              => 'System Code',
        'percentuale'              => 22.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => true,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('iva.codici.edit', ['tenant' => $this->tenant->slug, 'codiceIva' => $codice]));

    $response->assertForbidden();
});

test('update_modifies_custom_codice', function () {
    $codice = CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'CUSTOM',
        'descrizione'              => 'Original',
        'percentuale'              => 15.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => false,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('iva.codici.update', ['tenant' => $this->tenant->slug, 'codiceIva' => $codice]), [
            'codice'                   => 'CUSTOM',
            'descrizione'              => 'Updated',
            'percentuale'              => 18.00,
            'tipo'                     => 'normale',
            'natura_sdi'               => 'N',
            'indetraibile_percentuale' => 10.00,
            'attivo'                   => true,
        ]);

    $response->assertRedirect(route('iva.codici.index', ['tenant' => $this->tenant->slug]))
        ->assertSessionHas('flash.type', 'success');

    $codice->refresh();
    expect($codice->descrizione)->toBe('Updated')
        ->and($codice->percentuale)->toBe(18.00)
        ->and($codice->indetraibile_percentuale)->toBe(10.00);
});

test('update_blocks_di_sistema_codes', function () {
    $codice = CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'SISTEMA',
        'descrizione'              => 'System Code',
        'percentuale'              => 22.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => true,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('iva.codici.update', ['tenant' => $this->tenant->slug, 'codiceIva' => $codice]), [
            'codice'                   => 'SISTEMA',
            'descrizione'              => 'Modified',
            'percentuale'              => 22.00,
            'tipo'                     => 'normale',
            'natura_sdi'               => 'N',
            'indetraibile_percentuale' => 0.00,
            'attivo'                   => true,
        ]);

    $response->assertForbidden();

    $codice->refresh();
    expect($codice->descrizione)->toBe('System Code');
});

test('destroy_deletes_unused_custom_codice', function () {
    $codice = CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'DELETEME',
        'descrizione'              => 'Delete This',
        'percentuale'              => 15.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => false,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('iva.codici.destroy', ['tenant' => $this->tenant->slug, 'codiceIva' => $codice]));

    $response->assertRedirect(route('iva.codici.index', ['tenant' => $this->tenant->slug]))
        ->assertSessionHas('flash.type', 'success');

    expect(CodiceIva::find($codice->id))->toBeNull();
});

test('destroy_blocks_di_sistema_codes', function () {
    $codice = CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'SISTEMA',
        'descrizione'              => 'System Code',
        'percentuale'              => 22.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => true,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('iva.codici.destroy', ['tenant' => $this->tenant->slug, 'codiceIva' => $codice]));

    $response->assertForbidden();

    expect(CodiceIva::find($codice->id))->not->toBeNull();
});

test('destroy_blocks_codice_used_in_fatture_passive', function () {
    $codice = CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'USED_PASSIVE',
        'descrizione'              => 'Used in Passive',
        'percentuale'              => 15.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => false,
    ]);

    $fattura = FatturaPassiva::create([
        'tenant_id'           => $this->tenant->id,
        'supplier_id'         => $this->supplier->id,
        'numero_fattura'      => 'FP-TEST-001',
        'data_fattura'        => now(),
        'data_ricezione'      => now(),
        'data_registrazione'  => now(),
        'data_scadenza'       => now()->addDays(30),
        'stato_pagamento'     => 'da_pagare',
    ]);

    RigaFatturaPassiva::create([
        'tenant_id'       => $this->tenant->id,
        'fattura_passiva_id' => $fattura->id,
        'codice_iva_id'   => $codice->id,
        'descrizione'     => 'Test Row',
        'quantita'        => 1,
        'prezzo_unitario' => 100,
        'imponibile'      => 100,
        'iva'             => 15,
        'totale'          => 115,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('iva.codici.destroy', ['tenant' => $this->tenant->slug, 'codiceIva' => $codice]));

    $response->assertSessionHas('flash.type', 'error');

    expect(CodiceIva::find($codice->id))->not->toBeNull();
});

test('destroy_blocks_codice_used_in_fatture_attive', function () {
    $codice = CodiceIva::create([
        'tenant_id'                => $this->tenant->id,
        'codice'                   => 'USED_ACTIVE',
        'descrizione'              => 'Used in Active',
        'percentuale'              => 15.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'di_sistema'               => false,
    ]);

    $fattura = FatturaAttiva::create([
        'tenant_id'        => $this->tenant->id,
        'numero_fattura'   => 'FA-TEST-001',
        'data_fattura'     => now(),
        'stato'            => 'emessa',
    ]);

    RigaFatturaAttiva::create([
        'tenant_id'         => $this->tenant->id,
        'fattura_attiva_id' => $fattura->id,
        'codice_iva_id'     => $codice->id,
        'descrizione'       => 'Test Row',
        'quantita'          => 1,
        'prezzo_unitario'   => 100,
        'imponibile'        => 100,
        'iva'               => 15,
        'totale'            => 115,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('iva.codici.destroy', ['tenant' => $this->tenant->slug, 'codiceIva' => $codice]));

    $response->assertSessionHas('flash.type', 'error');

    expect(CodiceIva::find($codice->id))->not->toBeNull();
});

test('anonymous_user_cannot_access_codici_index', function () {
    $response = $this->get(route('iva.codici.index', ['tenant' => $this->tenant->slug]));
    $response->assertRedirect(route('login'));
});

test('anonymous_user_cannot_create_codice', function () {
    $response = $this->post(route('iva.codici.store', ['tenant' => $this->tenant->slug]), [
        'codice'                   => 'TEST',
        'descrizione'              => 'Test',
        'percentuale'              => 15.00,
        'tipo'                     => 'normale',
        'natura_sdi'               => 'N',
        'indetraibile_percentuale' => 0.00,
        'attivo'                   => true,
    ]);

    $response->assertRedirect(route('login'));
    expect(CodiceIva::where('codice', 'TEST')->first())->toBeNull();
});
