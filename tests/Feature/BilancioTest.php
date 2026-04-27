<?php

/**
 * Test suite per Bilancio CEE / Rendiconto Gestionale ETS (G5).
 *
 * Copre:
 *  - BilancioService::statoPatrimoniale (struttura attivo/passivo, saldi)
 *  - BilancioService::contoEconomico    (sezioni A-E, risultato esercizio)
 *  - BilancioService::rendicontoGestionale (aree, totali)
 *  - BilancioController: index (Inertia), export PDF SP/CE/Rendiconto, export CSV
 */

use App\Models\ContoContabile;
use App\Models\RigaMovimentoContabile;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BilancioService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Bilancio Test',
        'slug'              => 'ets-bil-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000006666',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();

    $this->admin = User::factory()->create([
        'email' => 'admin-bil@test.local',
    ]);
    $this->admin->roles()->attach($roleAdmin);
    $this->admin->tenants()->attach($this->tenant);

    $this->service = app(BilancioService::class);

    // Crea piano dei conti minimo
    $this->contoAttivo = ContoContabile::create([
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

    $this->contoPassivo = ContoContabile::create([
        'tenant_id'      => $this->tenant->id,
        'codice'         => '3.01.0001',
        'descrizione'    => 'Debiti vs fornitori',
        'livello'        => 3,
        'natura'         => ContoContabile::NATURA_PASSIVO,
        'tipo_bilancio'  => ContoContabile::TIPO_SP_PASSIVO,
        'segno_naturale' => ContoContabile::SEGNO_AVERE,
        'movimentabile'  => true,
        'di_sistema'     => false,
        'attivo'         => true,
    ]);

    $this->contoRicavo = ContoContabile::create([
        'tenant_id'      => $this->tenant->id,
        'codice'         => '5.01.0001',
        'descrizione'    => 'Quote associative',
        'livello'        => 3,
        'natura'         => ContoContabile::NATURA_RICAVO,
        'tipo_bilancio'  => ContoContabile::TIPO_CE_VALORE_PRODUZIONE,
        'segno_naturale' => ContoContabile::SEGNO_AVERE,
        'movimentabile'  => true,
        'di_sistema'     => false,
        'attivo'         => true,
    ]);

    $this->contoCosto = ContoContabile::create([
        'tenant_id'      => $this->tenant->id,
        'codice'         => '6.01.0001',
        'descrizione'    => 'Spese generali',
        'livello'        => 3,
        'natura'         => ContoContabile::NATURA_COSTO,
        'tipo_bilancio'  => ContoContabile::TIPO_CE_COSTI_PRODUZIONE,
        'segno_naturale' => ContoContabile::SEGNO_DARE,
        'movimentabile'  => true,
        'di_sistema'     => false,
        'attivo'         => true,
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Crea un movimento contabile confermato con 2 righe (DARE/AVERE). */
function creaMovimentoBilancio(string $tenantId, int $anno, int $contoDareId, int $contoAvereId, float $importo, string $gestione = null): \App\Models\MovimentoContabile
{
    $mov = createTestMovimento([
        'tenant_id'          => $tenantId,
        'anno_esercizio'     => $anno,
        'data_registrazione' => "{$anno}-06-01",
        'descrizione'        => 'Test movement',
        'stato'              => 'confermato',
    ]);

    RigaMovimentoContabile::create([
        'movimento_id'       => $mov->id,
        'conto_contabile_id' => $contoDareId,
        'ordine'             => 1,
        'importo_dare'       => $importo,
        'importo_avere'      => 0,
        'gestione'           => $gestione,
    ]);

    RigaMovimentoContabile::create([
        'movimento_id'       => $mov->id,
        'conto_contabile_id' => $contoAvereId,
        'ordine'             => 2,
        'importo_dare'       => 0,
        'importo_avere'      => $importo,
        'gestione'           => $gestione,
    ]);

    return $mov;
}

// ─────────────────────────────────────────────────────────────────────────────
// Service: statoPatrimoniale
// ─────────────────────────────────────────────────────────────────────────────

it('statoPatrimoniale restituisce chiavi strutturali', function () {
    $sp = $this->service->statoPatrimoniale($this->tenant, 2024, 2023);

    expect($sp)->toHaveKeys(['attivo', 'passivo', 'totale_attivo', 'totale_passivo', 'differenza', 'anno', 'anno_prec']);
});

it('statoPatrimoniale calcola saldo attivo corretto', function () {
    // Ricavi 1000 → avere su ricavo, dare su cassa (attivo)
    creaMovimentoBilancio($this->tenant->id, 2024, $this->contoAttivo->id, $this->contoRicavo->id, 1000.0);

    $sp = $this->service->statoPatrimoniale($this->tenant, 2024, 2023);

    // Cassa (attivo) deve avere saldo 1000 dare - 0 avere = 1000
    expect($sp['totale_attivo'])->toBe(1000.0);
});

it('statoPatrimoniale calcola saldo passivo corretto', function () {
    // Costo 500 → dare su costo, avere su passivo (debito)
    creaMovimentoBilancio($this->tenant->id, 2024, $this->contoCosto->id, $this->contoPassivo->id, 500.0);

    $sp = $this->service->statoPatrimoniale($this->tenant, 2024, 2023);

    expect($sp['totale_passivo'])->toBe(500.0);
});

it('statoPatrimoniale ignora movimenti di altri anni', function () {
    creaMovimentoBilancio($this->tenant->id, 2023, $this->contoAttivo->id, $this->contoRicavo->id, 999.0);

    $sp = $this->service->statoPatrimoniale($this->tenant, 2024, 2023);

    expect($sp['totale_attivo'])->toBe(0.0);
});

it('statoPatrimoniale ignora movimenti non confermati', function () {
    createTestMovimento([
        'anno_esercizio'     => 2024,
        'data_registrazione' => '2024-06-01',
        'descrizione'        => 'Bozza',
        'stato'              => 'bozza',
    ]);

    $sp = $this->service->statoPatrimoniale($this->tenant, 2024, 2023);
    expect($sp['totale_attivo'])->toBe(0.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: contoEconomico
// ─────────────────────────────────────────────────────────────────────────────

it('contoEconomico restituisce chiavi strutturali', function () {
    $ce = $this->service->contoEconomico($this->tenant, 2024, 2023);

    expect($ce)->toHaveKeys([
        'sezioni', 'valore_produzione', 'costi_produzione',
        'risultato_operativo', 'risultato_esercizio',
    ]);
    expect($ce['sezioni'])->toHaveKeys(['A', 'B', 'C', 'D', 'E']);
});

it('contoEconomico calcola risultato avanzo', function () {
    // Ricavo 800 → dare cassa, avere ricavo
    creaMovimentoBilancio($this->tenant->id, 2024, $this->contoAttivo->id, $this->contoRicavo->id, 800.0);
    // Costo 300 → dare costo, avere passivo
    creaMovimentoBilancio($this->tenant->id, 2024, $this->contoCosto->id, $this->contoPassivo->id, 300.0);

    $ce = $this->service->contoEconomico($this->tenant, 2024, 2023);

    expect($ce['valore_produzione'])->toBe(800.0);
    expect($ce['costi_produzione'])->toBe(300.0);
    expect($ce['risultato_operativo'])->toBe(500.0);
    expect($ce['risultato_esercizio'])->toBe(500.0);
});

it('contoEconomico calcola risultato disavanzo', function () {
    creaMovimentoBilancio($this->tenant->id, 2024, $this->contoAttivo->id, $this->contoRicavo->id, 200.0);
    creaMovimentoBilancio($this->tenant->id, 2024, $this->contoCosto->id, $this->contoPassivo->id, 600.0);

    $ce = $this->service->contoEconomico($this->tenant, 2024, 2023);

    expect($ce['risultato_esercizio'])->toBe(-400.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: rendicontoGestionale
// ─────────────────────────────────────────────────────────────────────────────

it('rendicontoGestionale restituisce chiavi strutturali', function () {
    $r = $this->service->rendicontoGestionale($this->tenant, 2024, 2023);

    expect($r)->toHaveKeys(['anno', 'anno_prec', 'aree', 'tot_entrate', 'tot_uscite', 'risultato_netto']);
    expect($r['aree'])->toHaveKey('istituzionale');
    expect($r['aree'])->toHaveKey('commerciale');
});

it('rendicontoGestionale separa entrate per gestione', function () {
    // Ricavo istituzionale
    creaMovimentoBilancio($this->tenant->id, 2024, $this->contoAttivo->id, $this->contoRicavo->id, 500.0, 'istituzionale');
    // Ricavo commerciale
    creaMovimentoBilancio($this->tenant->id, 2024, $this->contoAttivo->id, $this->contoRicavo->id, 200.0, 'commerciale');

    $r = $this->service->rendicontoGestionale($this->tenant, 2024, 2023);

    expect($r['aree']['istituzionale']['tot_entrate'])->toBe(500.0);
    expect($r['aree']['commerciale']['tot_entrate'])->toBe(200.0);
    expect($r['tot_entrate'])->toBe(700.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: index
// ─────────────────────────────────────────────────────────────────────────────

it('index restituisce vista Inertia con sp, ce, rendiconto', function () {
    $response = $this->actingAs($this->admin)
         ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
         ->get(route('bilancio.cee.index', $this->tenant));

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'])->toBe('Bilancio/CEE/Index');
    expect($page['props'])->toHaveKey('sp');
    expect($page['props'])->toHaveKey('ce');
    expect($page['props'])->toHaveKey('rendiconto');
    expect($page['props'])->toHaveKey('anno');
});

it('index accetta parametro anno', function () {
    $response = $this->actingAs($this->admin)
         ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
         ->get(route('bilancio.cee.index', [$this->tenant, 'anno' => 2022]));

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['props']['anno'])->toBe(2022);
});

// ─────────────────────────────────────────────────────────────────────────────
// Export PDF
// ─────────────────────────────────────────────────────────────────────────────

it('exportPdfSp restituisce HTTP 200 con Content-Type PDF', function () {
    $this->actingAs($this->admin)
         ->get(route('bilancio.cee.pdf-sp', $this->tenant))
         ->assertOk()
         ->assertHeader('Content-Type', 'application/pdf');
});

it('exportPdfCe restituisce HTTP 200 con Content-Type PDF', function () {
    $this->actingAs($this->admin)
         ->get(route('bilancio.cee.pdf-ce', $this->tenant))
         ->assertOk()
         ->assertHeader('Content-Type', 'application/pdf');
});

it('exportPdfRendiconto restituisce HTTP 200 con Content-Type PDF', function () {
    $this->actingAs($this->admin)
         ->get(route('bilancio.cee.pdf-rendiconto', $this->tenant))
         ->assertOk()
         ->assertHeader('Content-Type', 'application/pdf');
});

// ─────────────────────────────────────────────────────────────────────────────
// Export CSV
// ─────────────────────────────────────────────────────────────────────────────

it('exportCsv restituisce HTTP 200 con Content-Type CSV', function () {
    $this->actingAs($this->admin)
         ->get(route('bilancio.cee.csv', $this->tenant))
         ->assertOk()
         ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});
