<?php

/**
 * Test suite per Erogazioni Liberali ETS (G4).
 *
 * Copre:
 *  - ErogazioneLiberaleService::crea (detraibilità auto, aliquota 26%/30%)
 *  - ErogazioneLiberaleService::aggiorna (ricalcolo aliquota)
 *  - ErogazioneLiberaleService::riepilogoAnno (totali, per_tipo, per_modalita, per_donante)
 *  - ErogazioneLiberaleService::generaCsv (header + righe solo detraibili)
 *  - ErogazioneLiberaleService::generaXml (struttura DOMDocument)
 *  - ErogazioneLiberaleController: index, create, store, show, edit, update, destroy
 *  - Export CSV e XML (HTTP status + Content-Type)
 *  - Riepilogo controller
 *  - Model helpers: nomeCompleto, importoDetrazione, codiceModalitaAde, calcolaAliquota
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ErogazioneLiberale;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ErogazioneLiberaleService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Erogazioni Test',
        'slug'              => 'ets-erog-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000007777',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email'     => 'admin-erog@test.local',
    ]);
    $this->admin->roles()->attach($roleAdmin);

    $this->service = app(ErogazioneLiberaleService::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function erogazioneBase(Tenant $tenant, array $override = []): ErogazioneLiberale
{
    return ErogazioneLiberale::create(array_merge([
        'tenant_id'          => $tenant->id,
        'anno'               => 2024,
        'donante_tipo'       => ErogazioneLiberale::TIPO_PERSONA_FISICA,
        'donante_cf'         => 'RSSMRA80A01H501U',
        'donante_cognome'    => 'Rossi',
        'donante_nome'       => 'Mario',
        'importo'            => 500.00,
        'data_erogazione'    => '2024-05-10',
        'modalita_pagamento' => 'bonifico',
        'is_detraibile'      => true,
        'aliquota_detrazione' => 26,
    ], $override));
}

// ─────────────────────────────────────────────────────────────────────────────
// Model helpers
// ─────────────────────────────────────────────────────────────────────────────

it('nomeCompleto restituisce nome per persona fisica', function () {
    $e = erogazioneBase($this->tenant);
    expect($e->nomeCompleto())->toBe('Rossi Mario');
});

it('nomeCompleto restituisce ragione sociale per ente', function () {
    $e = erogazioneBase($this->tenant, [
        'donante_tipo'             => ErogazioneLiberale::TIPO_ENTE,
        'donante_ragione_sociale'  => 'Fondazione XYZ',
        'donante_cognome'          => null,
        'donante_nome'             => null,
    ]);
    expect($e->nomeCompleto())->toBe('Fondazione XYZ');
});

it('importoDetrazione calcola 26% per persona fisica', function () {
    $e = erogazioneBase($this->tenant);
    expect($e->importoDetrazione())->toBe(130.0); // 26% di 500
});

it('importoDetrazione restituisce 0 se non detraibile', function () {
    $e = erogazioneBase($this->tenant, [
        'is_detraibile'      => false,
        'aliquota_detrazione' => null,
    ]);
    expect($e->importoDetrazione())->toBe(0.0);
});

it('codiceModalitaAde restituisce BO per bonifico', function () {
    $e = erogazioneBase($this->tenant, ['modalita_pagamento' => 'bonifico']);
    expect($e->codiceModalitaAde())->toBe('BO');
});

it('codiceModalitaAde restituisce CN per contante', function () {
    $e = erogazioneBase($this->tenant, ['modalita_pagamento' => 'contante']);
    expect($e->codiceModalitaAde())->toBe('CN');
});

it('calcolaAliquota restituisce 26 per PF tracciabile', function () {
    expect(ErogazioneLiberale::calcolaAliquota('persona_fisica', 'bonifico'))->toBe(26);
});

it('calcolaAliquota restituisce 30 per ente tracciabile', function () {
    expect(ErogazioneLiberale::calcolaAliquota('ente', 'carta_credito'))->toBe(30);
});

it('calcolaAliquota restituisce null per contante', function () {
    expect(ErogazioneLiberale::calcolaAliquota('persona_fisica', 'contante'))->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: crea
// ─────────────────────────────────────────────────────────────────────────────

it('crea imposta detraibile=true e aliquota=26 per PF con bonifico', function () {
    $e = $this->service->crea($this->tenant, [
        'anno'               => 2024,
        'donante_tipo'       => 'persona_fisica',
        'donante_cf'         => 'VRDFNC80A01H501X',
        'donante_cognome'    => 'Verdi',
        'donante_nome'       => 'Franco',
        'importo'            => 200.00,
        'data_erogazione'    => '2024-03-01',
        'modalita_pagamento' => 'bonifico',
    ]);

    expect($e->is_detraibile)->toBeTrue();
    expect($e->aliquota_detrazione)->toBe(26);
});

it('crea imposta detraibile=true e aliquota=30 per ente con bonifico', function () {
    $e = $this->service->crea($this->tenant, [
        'anno'                    => 2024,
        'donante_tipo'            => 'ente',
        'donante_cf'              => '01234567890',
        'donante_ragione_sociale' => 'Fondazione Aiuto',
        'importo'                 => 1000.00,
        'data_erogazione'         => '2024-04-15',
        'modalita_pagamento'      => 'assegno_circolare',
    ]);

    expect($e->is_detraibile)->toBeTrue();
    expect($e->aliquota_detrazione)->toBe(30);
});

it('crea imposta detraibile=false per contante', function () {
    $e = $this->service->crea($this->tenant, [
        'anno'               => 2024,
        'donante_tipo'       => 'persona_fisica',
        'donante_cf'         => 'BNCMSR75A01H501Z',
        'importo'            => 50.00,
        'data_erogazione'    => '2024-01-05',
        'modalita_pagamento' => 'contante',
    ]);

    expect($e->is_detraibile)->toBeFalse();
    expect($e->aliquota_detrazione)->toBeNull();
});

it('crea lancia eccezione per importo zero', function () {
    expect(fn () => $this->service->crea($this->tenant, [
        'anno'               => 2024,
        'donante_tipo'       => 'persona_fisica',
        'donante_cf'         => 'RSSMRA80A01H501U',
        'importo'            => 0,
        'data_erogazione'    => '2024-01-01',
        'modalita_pagamento' => 'bonifico',
    ]))->toThrow(\InvalidArgumentException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: aggiorna
// ─────────────────────────────────────────────────────────────────────────────

it('aggiorna ricalcola aliquota se cambia tipo donante', function () {
    $e = erogazioneBase($this->tenant); // PF, 26%

    $updated = $this->service->aggiorna($e, ['donante_tipo' => 'ente']);

    expect($updated->aliquota_detrazione)->toBe(30);
});

it('aggiorna rende non detraibile se cambia in contante', function () {
    $e = erogazioneBase($this->tenant);

    $updated = $this->service->aggiorna($e, ['modalita_pagamento' => 'contante']);

    expect($updated->is_detraibile)->toBeFalse();
    expect($updated->aliquota_detrazione)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: riepilogoAnno
// ─────────────────────────────────────────────────────────────────────────────

it('riepilogoAnno restituisce totali corretti', function () {
    erogazioneBase($this->tenant, ['importo' => 500.00]);
    erogazioneBase($this->tenant, ['importo' => 300.00, 'donante_cf' => 'GRNLCA85M01H501Y']);
    erogazioneBase($this->tenant, ['importo' => 100.00, 'donante_cf' => 'BLBSTN90A01H501K', 'modalita_pagamento' => 'contante', 'is_detraibile' => false, 'aliquota_detrazione' => null]);

    $r = $this->service->riepilogoAnno($this->tenant, 2024);

    expect($r['totale_importo'])->toBe(900.0);
    expect($r['totale_detraibili'])->toBe(800.0);
    expect($r['totale_non_detraibili'])->toBe(100.0);
    expect($r['count'])->toBe(3);
    expect($r['count_detraibili'])->toBe(2);
});

it('riepilogoAnno raggruppa per donante', function () {
    erogazioneBase($this->tenant, ['importo' => 200.00]);
    erogazioneBase($this->tenant, ['importo' => 150.00]); // same CF

    $r = $this->service->riepilogoAnno($this->tenant, 2024);

    // Should group by CF
    $donante = $r['per_donante']->firstWhere('donante_cf', 'RSSMRA80A01H501U');
    expect((float) $donante->totale_versato)->toBe(350.0);
    expect($donante->n_versamenti)->toBe(2);
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: generaCsv
// ─────────────────────────────────────────────────────────────────────────────

it('generaCsv produce intestazione e righe corrette', function () {
    erogazioneBase($this->tenant); // detraibile
    erogazioneBase($this->tenant, [
        'donante_cf'         => 'BLBSTN90A01H501K',
        'modalita_pagamento' => 'contante',
        'is_detraibile'      => false,
        'aliquota_detrazione' => null,
    ]); // NON detraibile

    $csv = $this->service->generaCsv($this->tenant, 2024);

    // Header row
    expect($csv)->toContain('CF_ENTE_BENEFICIARIO');
    // Only 1 detraibile row (+ header)
    $lines = array_filter(explode("\r\n", trim($csv)));
    expect(count($lines))->toBe(2); // 1 header + 1 data row
    // CF donante presente
    expect($csv)->toContain('RSSMRA80A01H501U');
    // Importo
    expect($csv)->toContain('500.00');
});

it('generaCsv esclude donazioni non detraibili', function () {
    erogazioneBase($this->tenant, [
        'modalita_pagamento' => 'contante',
        'is_detraibile'      => false,
        'aliquota_detrazione' => null,
    ]);

    $csv = $this->service->generaCsv($this->tenant, 2024);

    $lines = array_filter(explode("\r\n", trim($csv)));
    expect(count($lines))->toBe(1); // Only header
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: generaXml
// ─────────────────────────────────────────────────────────────────────────────

it('generaXml produce XML valido con struttura corretta', function () {
    erogazioneBase($this->tenant);

    $xml = $this->service->generaXml($this->tenant, 2024);

    expect($xml)->toStartWith('<?xml');
    expect($xml)->toContain('ErogazioniLiberali');
    expect($xml)->toContain('<Testata>');
    expect($xml)->toContain('<Erogazione>');
    expect($xml)->toContain('RSSMRA80A01H501U');
    expect($xml)->toContain('500.00');
    // Codice modalita BO
    expect($xml)->toContain('<ModalitaPagamento>BO</ModalitaPagamento>');
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: index
// ─────────────────────────────────────────────────────────────────────────────

it('index restituisce vista con kpi e paginator', function () {
    erogazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('erogazioni-liberali.index', $this->tenant))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Bilancio/ErogazioniLiberali/Index')
             ->has('erogazioni')
             ->has('kpi')
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: create
// ─────────────────────────────────────────────────────────────────────────────

it('create restituisce vista con modalita e tipiDonante', function () {
    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('erogazioni-liberali.create', $this->tenant))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Bilancio/ErogazioniLiberali/Create')
             ->has('modalita')
             ->has('tipiDonante')
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: store
// ─────────────────────────────────────────────────────────────────────────────

it('store crea erogazione e reindirizza a show', function () {
    $this->actingAs($this->admin)
         ->post(route('erogazioni-liberali.store', $this->tenant), [
             'anno'               => 2024,
             'donante_tipo'       => 'persona_fisica',
             'donante_cf'         => 'RSSMRA80A01H501U',
             'donante_cognome'    => 'Rossi',
             'donante_nome'       => 'Mario',
             'importo'            => 250.00,
             'data_erogazione'    => '2024-06-01',
             'modalita_pagamento' => 'bonifico',
         ])
         ->assertRedirect();

    $this->assertDatabaseHas('erogazioni_liberali', [
        'tenant_id'   => $this->tenant->id,
        'donante_cf'  => 'RSSMRA80A01H501U',
        'importo'     => 250.00,
    ]);
});

it('store respinge importo negativo con errore di validazione', function () {
    $this->actingAs($this->admin)
         ->post(route('erogazioni-liberali.store', $this->tenant), [
             'anno'               => 2024,
             'donante_tipo'       => 'persona_fisica',
             'donante_cf'         => 'RSSMRA80A01H501U',
             'importo'            => -10,
             'data_erogazione'    => '2024-06-01',
             'modalita_pagamento' => 'bonifico',
         ])
         ->assertSessionHasErrors('importo');
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: show
// ─────────────────────────────────────────────────────────────────────────────

it('show restituisce vista con dati erogazione', function () {
    $e = erogazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('erogazioni-liberali.show', [$this->tenant, $e]))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Bilancio/ErogazioniLiberali/Show')
             ->has('erogazione')
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: update
// ─────────────────────────────────────────────────────────────────────────────

it('update modifica importo e ricalcola detrazione', function () {
    $e = erogazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->put(route('erogazioni-liberali.update', [$this->tenant, $e]), [
             'anno'               => 2024,
             'donante_tipo'       => 'ente',
             'donante_cf'         => 'RSSMRA80A01H501U',
             'importo'            => 1000.00,
             'data_erogazione'    => '2024-05-10',
             'modalita_pagamento' => 'carta_credito',
         ])
         ->assertRedirect(route('erogazioni-liberali.show', [$this->tenant, $e]));

    $updated = ErogazioneLiberale::find($e->id);
    expect((float) $updated->importo)->toBe(1000.0);
    expect($updated->aliquota_detrazione)->toBe(30);
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: destroy
// ─────────────────────────────────────────────────────────────────────────────

it('destroy elimina erogazione', function () {
    $e = erogazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->delete(route('erogazioni-liberali.destroy', [$this->tenant, $e]))
         ->assertRedirect(route('erogazioni-liberali.index', $this->tenant));

    $this->assertDatabaseMissing('erogazioni_liberali', ['id' => $e->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: riepilogo
// ─────────────────────────────────────────────────────────────────────────────

it('riepilogo restituisce vista con dati aggregati', function () {
    erogazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('erogazioni-liberali.riepilogo', $this->tenant))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Bilancio/ErogazioniLiberali/Riepilogo')
             ->has('riepilogo')
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Export CSV / XML (HTTP)
// ─────────────────────────────────────────────────────────────────────────────

it('exportCsv restituisce HTTP 200 con Content-Type CSV', function () {
    erogazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->get(route('erogazioni-liberali.csv', [$this->tenant, 'anno' => 2024]))
         ->assertOk()
         ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('exportXml restituisce HTTP 200 con Content-Type XML', function () {
    erogazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->get(route('erogazioni-liberali.xml', [$this->tenant, 'anno' => 2024]))
         ->assertOk()
         ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
});
