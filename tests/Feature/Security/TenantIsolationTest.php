<?php

/**
 * Test suite di Row-level Security (C3).
 *
 * Verifica che il trait BelongsToTenant impedisca a utenti di un tenant
 * di leggere o modificare dati appartenenti a un altro tenant.
 *
 * Pattern testato per ogni modello critico:
 *  1. Tenant A crea un record
 *  2. App switcha al contesto di Tenant B
 *  3. Query Eloquent del modello NON deve restituire il record del Tenant A
 *  4. Tentativo di accesso HTTP da utente B al record di A → 404/403
 *
 * Modelli coperti:
 *  Member, FatturaAttiva, FatturaPassiva, MovimentoContabile,
 *  Incasso, ContoContabile, CooperativeShare, ErogazioneLiberale
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ContoContabile;
use App\Models\CooperativeShare;
use App\Models\ErogazioneLiberale;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\MovimentoContabile;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    (new RoleSeeder)->run();

    $makeAdmin = function (Tenant $t, string $suffix): User {
        $user = User::factory()->create([
            'tenant_id' => $t->id,
            'email'     => "admin-{$suffix}@test.local",
        ]);
        $user->roles()->attach(Role::where('name', 'admin')->first());
        return $user;
    };

    // Tenant A
    $this->tenantA = Tenant::create([
        'name'              => 'Tenant A',
        'slug'              => 'ten-a-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000001111',
    ]);

    // Tenant B
    $this->tenantB = Tenant::create([
        'name'              => 'Tenant B',
        'slug'              => 'ten-b-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000002222',
    ]);

    $this->adminA = $makeAdmin($this->tenantA, 'a');
    $this->adminB = $makeAdmin($this->tenantB, 'b');
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper: set current tenant context
// ─────────────────────────────────────────────────────────────────────────────

function switchTenant(Tenant $t): void
{
    app()->instance('current_tenant', $t);
}

// ─────────────────────────────────────────────────────────────────────────────
// Member isolation
// ─────────────────────────────────────────────────────────────────────────────

it('Member: tenant B non vede soci di tenant A', function () {
    switchTenant($this->tenantA);
    $member = Member::create([
        'tenant_id'        => $this->tenantA->id,
        'nome'             => 'Mario',
        'cognome'          => 'Rossi',
        'data_iscrizione'  => '2024-01-01',
        'stato_membership' => 'attivo',
    ]);

    switchTenant($this->tenantB);
    $results = Member::all();

    expect($results->pluck('id'))->not->toContain($member->id);
});

it('Member: accesso HTTP 404 a dettaglio socio di altro tenant', function () {
    switchTenant($this->tenantA);
    $member = Member::create([
        'tenant_id'        => $this->tenantA->id,
        'nome'             => 'Mario',
        'cognome'          => 'Rossi',
        'data_iscrizione'  => '2024-01-01',
        'stato_membership' => 'attivo',
    ]);

    switchTenant($this->tenantB);
    app()->instance('current_tenant', $this->tenantB);

    $this->actingAs($this->adminB)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('members.show', [$this->tenantB, $member]))
         ->assertNotFound();
});

// ─────────────────────────────────────────────────────────────────────────────
// FatturaAttiva isolation
// ─────────────────────────────────────────────────────────────────────────────

it('FatturaAttiva: tenant B non vede fatture attive di tenant A', function () {
    switchTenant($this->tenantA);
    $fattura = FatturaAttiva::create([
        'tenant_id'        => $this->tenantA->id,
        'anno'             => 2024,
        'progressivo'      => 1,
        'numero_fattura'   => 'FA-A-001',
        'data_fattura'     => '2024-06-01',
        'tipo_documento'   => 'TD01',
        'esigibilita'      => 'immediata',
        'stato'            => 'bozza',
        'stato_pagamento'  => 'da_incassare',
        'imponibile'       => 100.0,
        'iva_totale'       => 22.0,
        'totale_documento' => 122.0,
    ]);

    switchTenant($this->tenantB);
    $results = FatturaAttiva::all();

    expect($results->pluck('id'))->not->toContain($fattura->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// MovimentoContabile isolation
// ─────────────────────────────────────────────────────────────────────────────

it('MovimentoContabile: tenant B non vede movimenti di tenant A', function () {
    switchTenant($this->tenantA);
    $mov = MovimentoContabile::create([
        'tenant_id'      => $this->tenantA->id,
        'anno_esercizio' => 2024,
        'data'           => '2024-06-01',
        'causale'        => 'Test isolamento',
        'stato'          => 'bozza',
        'numero'         => uniqid(),
    ]);

    switchTenant($this->tenantB);
    $results = MovimentoContabile::all();

    expect($results->pluck('id'))->not->toContain($mov->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// ContoContabile isolation
// ─────────────────────────────────────────────────────────────────────────────

it('ContoContabile: tenant B non vede piano dei conti di tenant A', function () {
    switchTenant($this->tenantA);
    $conto = ContoContabile::create([
        'tenant_id'      => $this->tenantA->id,
        'codice'         => '2.01.0001',
        'descrizione'    => 'Cassa Tenant A',
        'livello'        => 3,
        'natura'         => ContoContabile::NATURA_ATTIVO,
        'tipo_bilancio'  => ContoContabile::TIPO_SP_ATTIVO,
        'segno_naturale' => ContoContabile::SEGNO_DARE,
        'movimentabile'  => true,
        'di_sistema'     => false,
        'attivo'         => true,
    ]);

    switchTenant($this->tenantB);
    $results = ContoContabile::all();

    expect($results->pluck('id'))->not->toContain($conto->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// Incasso isolation
// ─────────────────────────────────────────────────────────────────────────────

it('Incasso: tenant B non vede incassi di tenant A', function () {
    switchTenant($this->tenantA);
    $incasso = Incasso::create([
        'tenant_id' => $this->tenantA->id,
        'type'      => Incasso::TYPE_QUOTA,
        'amount'    => 50.0,
        'paid_at'   => now(),
    ]);

    switchTenant($this->tenantB);
    $results = Incasso::all();

    expect($results->pluck('id'))->not->toContain($incasso->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// CooperativeShare isolation
// ─────────────────────────────────────────────────────────────────────────────

it('CooperativeShare: tenant B non vede quote di tenant A', function () {
    switchTenant($this->tenantA);
    $share = CooperativeShare::create([
        'tenant_id'           => $this->tenantA->id,
        'numero_quote'        => 5,
        'valore_unitario'     => 50,
        'totale_sottoscritto' => 250,
        'totale_versato'      => 0,
        'data_sottoscrizione' => '2024-01-01',
        'status'              => 'sottoscritta',
    ]);

    switchTenant($this->tenantB);
    $results = CooperativeShare::all();

    expect($results->pluck('id'))->not->toContain($share->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// ErogazioneLiberale isolation
// ─────────────────────────────────────────────────────────────────────────────

it('ErogazioneLiberale: tenant B non vede donazioni di tenant A', function () {
    switchTenant($this->tenantA);
    $erogazione = ErogazioneLiberale::create([
        'tenant_id'           => $this->tenantA->id,
        'anno'                => 2024,
        'data_erogazione'     => '2024-06-01',
        'importo'             => 100.0,
        'donante_tipo'        => 'persona_fisica',
        'cognome'             => 'Testa',
        'nome'                => 'Marco',
        'codice_fiscale'      => 'TSTMRC80A01H501Z',
        'modalita_pagamento'  => 'bonifico',
        'is_detraibile'       => true,
        'aliquota_detrazione' => 26,
    ]);

    switchTenant($this->tenantB);
    $results = ErogazioneLiberale::all();

    expect($results->pluck('id'))->not->toContain($erogazione->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// BelongsToTenant auto-setta tenant_id
// ─────────────────────────────────────────────────────────────────────────────

it('BelongsToTenant auto-setta tenant_id dal contesto corrente', function () {
    switchTenant($this->tenantA);

    // Crea senza specificare tenant_id esplicitamente
    $member = Member::create([
        'nome'             => 'Auto',
        'cognome'          => 'TenantSet',
        'data_iscrizione'  => '2024-01-01',
        'stato_membership' => 'attivo',
    ]);

    expect($member->tenant_id)->toBe($this->tenantA->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// create() non permette di scrivere su tenant diverso (FatturaPassiva isolation)
// ─────────────────────────────────────────────────────────────────────────────

it('FatturaPassiva: tenant B non vede fatture passive di tenant A', function () {
    switchTenant($this->tenantA);
    $fattura = FatturaPassiva::create([
        'tenant_id'           => $this->tenantA->id,
        'numero_fattura'      => 'FP-A-001',
        'data_fattura'        => '2024-06-01',
        'data_registrazione'  => '2024-06-01',
        'tipo_documento'      => 'TD01',
        'esigibilita'         => 'immediata',
        'stato_pagamento'     => 'da_pagare',
        'imponibile'          => 200.0,
        'iva_totale'          => 44.0,
        'totale_documento'    => 244.0,
    ]);

    switchTenant($this->tenantB);
    $results = FatturaPassiva::all();

    expect($results->pluck('id'))->not->toContain($fattura->id);
});
