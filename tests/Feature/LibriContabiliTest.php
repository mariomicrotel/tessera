<?php

/**
 * Test suite per Libro Giornale e Registro Vendite (E4).
 *
 * Copre:
 *  - libroGiornale: filtra movimenti definitivi del periodo, totali Dare/Avere
 *  - exportLibroGiornale: CSV con header BOM, una riga per ogni riga di movimento
 *  - registroVendite: filtra fatture emesse del periodo, riepilogo per aliquota
 *  - exportRegistroVendite: CSV con breakdown per aliquota
 *  - middleware role:admin,contabile
 */

use App\Models\CausaleContabile;
use App\Models\CodiceIva;
use App\Models\ContoContabile;
use App\Models\FatturaAttiva;
use App\Models\RigaFatturaAttiva;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MovimentoContabileService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup globale
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Libri Test',
        'slug'              => 'ets-libri-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();
    $this->user = User::factory()->create([
        'email'             => 'admin@libri.it',
        'email_verified_at' => now(),
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    $this->userSenza = User::factory()->create([
        'email'             => 'nessuno@libri.it',
        'email_verified_at' => now(),
    ]);
    $this->userSenza->tenants()->attach($this->tenant);

    // Conti contabili
    $this->contoBanca = ContoContabile::create([
        'codice'        => '1.30.10.001',
        'descrizione'   => 'Banca c/c',
        'livello'       => 4,
        'natura'        => ContoContabile::NATURA_ATTIVO,
        'segno_naturale' => 'dare',
        'movimentabile' => true,
        'di_sistema'    => false,
        'attivo'        => true,
    ]);

    $this->contoRicavo = ContoContabile::create([
        'codice'        => '4.10.01.001',
        'descrizione'   => 'Ricavi vendite',
        'livello'       => 4,
        'natura'        => ContoContabile::NATURA_RICAVO,
        'segno_naturale' => 'avere',
        'movimentabile' => true,
        'di_sistema'    => false,
        'attivo'        => true,
    ]);

    $this->causale = CausaleContabile::firstOrCreate(
        ['tipo' => CausaleContabile::TIPO_GENERICO, 'di_sistema' => true],
        ['codice' => 'GEN', 'descrizione' => 'Generico', 'attivo' => true]
    );

    // Codice IVA 22%
    $this->iva22 = CodiceIva::firstOrCreate(
        ['codice' => '22'],
        [
            'descrizione' => 'IVA 22%',
            'percentuale' => 22.00,
            'tipo'        => 'imponibile',
            'attivo'      => true,
        ]
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper: crea movimento definitivo
// ─────────────────────────────────────────────────────────────────────────────

function creaMovimentoLibri(\Tests\TestCase $test, string $data, float $importo, string $descr = 'Test')
{
    $svc = app(MovimentoContabileService::class);
    $mov = $svc->crea($test->tenant, [
        'data_registrazione' => $data,
        'causale_id'         => $test->causale->id,
        'descrizione'        => $descr,
        'anno_esercizio'     => (int) date('Y', strtotime($data)),
    ], [
        ['conto_contabile_id' => $test->contoBanca->id,  'importo_dare' => $importo, 'importo_avere' => 0],
        ['conto_contabile_id' => $test->contoRicavo->id, 'importo_dare' => 0,        'importo_avere' => $importo],
    ]);

    return $svc->conferma($mov);
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: crea fattura attiva emessa
// ─────────────────────────────────────────────────────────────────────────────

function creaFatturaEmessa(\Tests\TestCase $test, string $data, float $imponibile, int $progressivo = 1)
{
    $iva = round($imponibile * 0.22, 2);
    $f = FatturaAttiva::create([
        'tenant_id'         => $test->tenant->id,
        'cliente_id'        => 999,
        'sezionale'         => '',
        'anno'              => (int) date('Y', strtotime($data)),
        'progressivo'       => $progressivo,
        'numero_fattura'    => date('Y', strtotime($data)) . '/' . str_pad((string) $progressivo, 4, '0', STR_PAD_LEFT),
        'data_fattura'      => $data,
        'imponibile_totale' => $imponibile,
        'iva_totale'        => $iva,
        'totale_documento'  => $imponibile + $iva,
        'tipo_documento'    => 'TD01',
        'stato'             => FatturaAttiva::STATO_EMESSA,
        'stato_pagamento'   => FatturaAttiva::STATO_PAG_DA_INCASSARE,
    ]);

    RigaFatturaAttiva::create([
        'tenant_id'         => $test->tenant->id,
        'fattura_attiva_id' => $f->id,
        'codice_iva_id'     => $test->iva22->id,
        'descrizione'       => 'Vendita test',
        'quantita'          => 1,
        'prezzo_unitario'   => $imponibile,
        'sconto_percentuale' => 0,
        'imponibile'        => $imponibile,
        'iva'               => $iva,
        'totale'            => $imponibile + $iva,
    ]);

    return $f;
}

// ─────────────────────────────────────────────────────────────────────────────
// Service-level: query corretta dei movimenti / fatture
// ─────────────────────────────────────────────────────────────────────────────

describe('Libro Giornale → Service-level', function () {

    it('include solo movimenti definitivi del periodo', function () {
        creaMovimentoLibri($this, '2025-03-10', 100.0);
        creaMovimentoLibri($this, '2025-06-20', 200.0);
        creaMovimentoLibri($this, '2025-09-05', 300.0);
        creaMovimentoLibri($this, '2024-12-31', 999.0); // fuori periodo

        $svc = app(MovimentoContabileService::class);
        $bozza = $svc->crea($this->tenant, [
            'data_registrazione' => '2025-04-01',
            'causale_id'         => $this->causale->id,
            'descrizione'        => 'bozza',
            'anno_esercizio'     => 2025,
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,  'importo_dare' => 50, 'importo_avere' => 0],
            ['conto_contabile_id' => $this->contoRicavo->id, 'importo_dare' => 0,  'importo_avere' => 50],
        ]);
        // Non confermato → resta bozza, escluso da definitivi

        $count = \App\Models\MovimentoContabile::definitivi()
            ->perPeriodo('2025-01-01', '2025-12-31')
            ->count();

        expect($count)->toBe(3);
        expect($bozza->isBozza())->toBeTrue();
    });

});

describe('Registro Vendite → Service-level', function () {

    it('include solo fatture emesse del periodo', function () {
        creaFatturaEmessa($this, '2025-02-15', 1000.0, 1);
        creaFatturaEmessa($this, '2025-08-20', 500.0,  2);
        $bozza = creaFatturaEmessa($this, '2025-03-01', 999.0, 3);
        $bozza->update(['stato' => FatturaAttiva::STATO_BOZZA]);

        $f = FatturaAttiva::nelPeriodo('2025-01-01', '2025-12-31')
            ->whereIn('stato', [
                FatturaAttiva::STATO_EMESSA,
                FatturaAttiva::STATO_INVIATA_SDI,
                FatturaAttiva::STATO_ACCETTATA,
            ])
            ->get();

        expect($f)->toHaveCount(2);
        expect((float) $f->sum('imponibile_totale'))->toBe(1500.0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: index libro giornale e registro vendite
// ─────────────────────────────────────────────────────────────────────────────

describe('AccountingReportController → Libro Giornale', function () {

    it('libroGiornale ritorna movimenti del periodo con totali corretti', function () {
        creaMovimentoLibri($this, '2025-03-10', 100.0, 'Mov A');
        creaMovimentoLibri($this, '2025-09-15', 250.0, 'Mov B');

        $inertiaVersion = app(\App\Http\Middleware\HandleInertiaRequests::class)
            ->version(request()) ?? '';

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
            ->get(route('reports.libro-giornale', [
                'tenant' => $this->tenant->slug,
                'from'   => '2025-01-01',
                'to'     => '2025-12-31',
            ]));

        $response->assertStatus(200);

        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('Reports/LibroGiornale');
        expect($page['props']['movimenti'])->toHaveCount(2);
        expect((float) $page['props']['totale_dare'])->toBe(350.0);
        expect((float) $page['props']['totale_avere'])->toBe(350.0);
    });

    it('exportLibroGiornale produce CSV con header e righe', function () {
        creaMovimentoLibri($this, '2025-04-10', 150.0, 'Mov X');

        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->get(route('reports.libro-giornale.export', [
                'tenant' => $this->tenant->slug,
                'from'   => '2025-01-01',
                'to'     => '2025-12-31',
            ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        expect($content)->toContain('Numero;Data;Causale');
        expect($content)->toContain('150,00'); // Dare
    });

    it('libroGiornale richiede ruolo admin/contabile', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSenza)
            ->get(route('reports.libro-giornale', ['tenant' => $this->tenant->slug]));

        $response->assertStatus(403);
    });

});

describe('AccountingReportController → Registro Vendite', function () {

    it('registroVendite ritorna fatture del periodo con riepilogo aliquote', function () {
        creaFatturaEmessa($this, '2025-02-10', 1000.0, 1);
        creaFatturaEmessa($this, '2025-07-22', 500.0,  2);

        $inertiaVersion = app(\App\Http\Middleware\HandleInertiaRequests::class)
            ->version(request()) ?? '';

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
            ->get(route('reports.registro-vendite', [
                'tenant' => $this->tenant->slug,
                'from'   => '2025-01-01',
                'to'     => '2025-12-31',
            ]));

        $response->assertStatus(200);

        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('Reports/RegistroVendite');
        expect($page['props']['fatture'])->toHaveCount(2);
        expect((float) $page['props']['totale_imponibile'])->toBe(1500.0);
        expect((float) $page['props']['totale_iva'])->toBe(330.0);
        expect($page['props']['per_aliquota'])->toHaveCount(1);
        expect((string) $page['props']['per_aliquota'][0]['codice'])->toBe('22');
    });

    it('exportRegistroVendite produce CSV con righe per aliquota', function () {
        creaFatturaEmessa($this, '2025-05-10', 800.0, 1);

        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->get(route('reports.registro-vendite.export', [
                'tenant' => $this->tenant->slug,
                'from'   => '2025-01-01',
                'to'     => '2025-12-31',
            ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        expect($content)->toContain('Numero;Data;Tipo');
        expect($content)->toContain('800,00');  // imponibile
        expect($content)->toContain('176,00');  // iva 22%
    });

    it('registroVendite richiede ruolo admin/contabile', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSenza)
            ->get(route('reports.registro-vendite', ['tenant' => $this->tenant->slug]));

        $response->assertStatus(403);
    });

});
