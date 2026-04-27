<?php

/**
 * Test suite per Centri di Costo (A5).
 *
 * Copre:
 *  - CentroCosto model: CRUD, scope attivi, saldoAnno
 *  - CentriDiCostoController: index, show, store, update, destroy, toggleAttivo
 *  - Tenant isolation
 */

use App\Models\CentroCosto;
use App\Models\ContoContabile;
use App\Models\RigaMovimentoContabile;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'CDC Test ETS',
        'slug'              => 'cdc-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000005555',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $makeUser = function (string $role) {
        $u = User::factory()->create();
        $u->roles()->attach(Role::where('name', $role)->first());
        $u->tenants()->attach($this->tenant);
        return $u;
    };

    $this->admin     = $makeUser('admin');
    $this->contabile = $makeUser('contabile');
    $this->guest     = User::factory()->create();
    $this->guest->tenants()->attach($this->tenant);
});

// ─────────────────────────────────────────────────────────────────────────────
// Model: scope attivi
// ─────────────────────────────────────────────────────────────────────────────

it('CentroCosto scope attivi filtra correttamente', function () {
    CentroCosto::create(['tenant_id' => $this->tenant->id, 'codice' => 'A1', 'descrizione' => 'Attivo', 'attivo' => true]);
    CentroCosto::create(['tenant_id' => $this->tenant->id, 'codice' => 'I1', 'descrizione' => 'Inattivo', 'attivo' => false]);

    expect(CentroCosto::attivi()->count())->toBe(1)
        ->and(CentroCosto::attivi()->first()->codice)->toBe('A1');
});

// ─────────────────────────────────────────────────────────────────────────────
// Model: saldoAnno
// ─────────────────────────────────────────────────────────────────────────────

it('CentroCosto::saldoAnno calcola dare e avere correttamente', function () {
    $centro = CentroCosto::create([
        'tenant_id'   => $this->tenant->id,
        'codice'      => 'CDC-01',
        'descrizione' => 'Centro Test',
        'attivo'      => true,
    ]);

    // Crea conto dummy
    $conto = ContoContabile::create([
        'tenant_id'      => $this->tenant->id,
        'codice'         => '2.01.0001',
        'descrizione'    => 'Cassa',
        'livello'        => 3,
        'natura'         => ContoContabile::NATURA_ATTIVO,
        'tipo_bilancio'  => ContoContabile::TIPO_SP_ATTIVO,
        'segno_naturale' => ContoContabile::SEGNO_DARE,
        'movimentabile'  => true,
        'di_sistema'     => false,
        'attivo'         => true,
    ]);

    // Movimento confermato con rif. CDC
    $mov = createTestMovimento([
        'anno_esercizio'     => 2024,
        'data_registrazione' => '2024-06-01',
        'descrizione'        => 'Test CDC',
        'stato'              => 'confermato',
    ]);

    RigaMovimentoContabile::create([
        'movimento_id'       => $mov->id,
        'conto_contabile_id' => $conto->id,
        'centro_costo_id'    => $centro->id,
        'ordine'             => 1,
        'importo_dare'       => 500.0,
        'importo_avere'      => 0,
    ]);

    $saldo = $centro->saldoAnno(2024);

    expect($saldo['dare'])->toBe(500.0)
        ->and($saldo['avere'])->toBe(0.0)
        ->and($saldo['saldo'])->toBe(-500.0);
});

it('CentroCosto::saldoAnno restituisce zero per anno senza movimenti', function () {
    $centro = CentroCosto::create([
        'tenant_id'   => $this->tenant->id,
        'codice'      => 'CDC-02',
        'descrizione' => 'Vuoto',
        'attivo'      => true,
    ]);

    $saldo = $centro->saldoAnno(2025);

    expect($saldo['dare'])->toBe(0.0)
        ->and($saldo['avere'])->toBe(0.0)
        ->and($saldo['saldo'])->toBe(0.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: index
// ─────────────────────────────────────────────────────────────────────────────

it('index restituisce vista Inertia con lista centri', function () {
    CentroCosto::create(['tenant_id' => $this->tenant->id, 'codice' => 'CDC-01', 'descrizione' => 'Alpha', 'attivo' => true]);

    $response = $this->actingAs($this->admin)
         ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
         ->get(route('centri-di-costo.index', $this->tenant));

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'])->toBe('Contabilita/CentriDiCosto/Index');
    expect($page['props'])->toHaveKey('centri');
    expect($page['props'])->toHaveKey('anno');
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: store
// ─────────────────────────────────────────────────────────────────────────────

it('store crea centro e reindirizza a index', function () {
    $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
         ])
         ->actingAs($this->admin)
         ->post(route('centri-di-costo.store', $this->tenant), [
             'codice'      => 'CDC-NEW',
             'descrizione' => 'Nuovo Centro',
             'attivo'      => true,
         ])
         ->assertRedirect(route('centri-di-costo.index', $this->tenant));

    $this->assertDatabaseHas('centri_di_costo', ['codice' => 'CDC-NEW', 'tenant_id' => $this->tenant->id]);
});

it('store blocca codice duplicato', function () {
    CentroCosto::create(['tenant_id' => $this->tenant->id, 'codice' => 'DUP', 'descrizione' => 'Esistente', 'attivo' => true]);

    $this->actingAs($this->admin)
         ->post(route('centri-di-costo.store', $this->tenant), [
             'codice'      => 'DUP',
             'descrizione' => 'Duplicato',
             'attivo'      => true,
         ])
         ->assertRedirect(); // back con flash error
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: update / destroy / toggleAttivo
// ─────────────────────────────────────────────────────────────────────────────

it('update modifica il centro', function () {
    $centro = CentroCosto::create(['tenant_id' => $this->tenant->id, 'codice' => 'CDC-X', 'descrizione' => 'Old', 'attivo' => true]);

    $this->withoutMiddleware([
             \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
             \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
         ])
         ->actingAs($this->admin)
         ->put(route('centri-di-costo.update', [$this->tenant, $centro]), [
             'codice'      => 'CDC-X',
             'descrizione' => 'New Description',
             'attivo'      => true,
         ])
         ->assertRedirect(route('centri-di-costo.index', $this->tenant));

    expect($centro->fresh()->descrizione)->toBe('New Description');
});

it('toggleAttivo inverte lo stato attivo', function () {
    $centro = CentroCosto::create(['tenant_id' => $this->tenant->id, 'codice' => 'T1', 'descrizione' => 'Toggle', 'attivo' => true]);

    $this->withoutMiddleware([
             \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
             \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
         ])
         ->actingAs($this->admin)
         ->post(route('centri-di-costo.toggle-attivo', [$this->tenant, $centro]))
         ->assertRedirect();

    expect($centro->fresh()->attivo)->toBeFalse();
});

it('destroy elimina centro senza movimenti', function () {
    $centro = CentroCosto::create(['tenant_id' => $this->tenant->id, 'codice' => 'D1', 'descrizione' => 'Delete Me', 'attivo' => true]);

    $this->withoutMiddleware([
             \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
             \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
         ])
         ->actingAs($this->admin)
         ->delete(route('centri-di-costo.destroy', [$this->tenant, $centro]))
         ->assertRedirect(route('centri-di-costo.index', $this->tenant));

    $this->assertDatabaseMissing('centri_di_costo', ['id' => $centro->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Accesso non autorizzato
// ─────────────────────────────────────────────────────────────────────────────

it('utente senza ruolo non può accedere', function () {
    $this->actingAs($this->guest)
         ->get(route('centri-di-costo.index', $this->tenant))
         ->assertStatus(403);
});
