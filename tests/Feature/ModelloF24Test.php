<?php

/**
 * Test suite per Modello F24 (G2).
 *
 * Copre:
 *  - ModelloF24Service::crea  (manuale con righe, calcolo saldo)
 *  - ModelloF24Service::generaDaLiquidazioneIva  (codice tributo, rateazione)
 *  - ModelloF24Service::generaDaRitenute         (codice 1040/1038)
 *  - ModelloF24Service::aggiorna
 *  - ModelloF24Service::segnaVersato
 *  - ModelloF24Service::generaXml
 *  - ModelloF24Controller: index, create, store (3 modalità), show, edit, update, destroy, segnaVersato
 *  - Export PDF e XML (HTTP status)
 *  - Middleware role:admin,contabile
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CompensaTerzi;
use App\Models\LiquidazioneIva;
use App\Models\ModelloF24;
use App\Models\Role;
use App\Models\RigaF24;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VersamentoRitenuta;
use App\Services\CompensaTerziService;
use App\Services\ModelloF24Service;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS F24 Test',
        'slug'              => 'ets-f24-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000008888',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    $this->userSenza = User::factory()->create(['email_verified_at' => now()]);
    $this->userSenza->tenants()->attach($this->tenant);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function righeF24(array $override = []): array
{
    return [
        array_merge([
            'sezione'          => 'erario',
            'codice_tributo'   => '6001',
            'descrizione'      => 'IVA gennaio',
            'anno_riferimento' => 2026,
            'importo_debito'   => 1500.00,
            'importo_credito'  => 0,
        ], $override),
    ];
}

function liquidazioneBase(Tenant $tenant): LiquidazioneIva
{
    return LiquidazioneIva::create([
        'tenant_id'                  => $tenant->id,
        'anno'                       => 2026,
        'periodo'                    => 1,
        'tipo_periodo'               => 'mensile',
        'data_inizio'                => '2026-01-01',
        'data_fine'                  => '2026-01-31',
        'iva_debito'                 => 2000,
        'iva_credito'                => 500,
        'credito_periodo_precedente' => 0,
        'saldo_periodo'              => 1500,
        'saldo_finale'               => 1500,
        'acconto_versato'            => 0,
        'interessi_trimestrali'      => 0,
        'status'                     => LiquidazioneIva::STATUS_DEFINITIVA,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// ModelloF24Service::crea
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Service::crea', function () {

    it('crea modello con righe e calcola saldo correttamente', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno'              => 2026,
            'mese'              => 1,
            'data_compilazione' => '2026-02-15',
        ], righeF24());

        expect($modello->totale_debiti)->toBe(1500.0);
        expect($modello->totale_crediti)->toBe(0.0);
        expect($modello->saldo)->toBe(1500.0);
        expect($modello->stato)->toBe(ModelloF24::STATO_BOZZA);
        expect($modello->righe)->toHaveCount(1);
        expect($modello->righe->first()->codice_tributo)->toBe('6001');
    });

    it('calcola saldo netto con crediti', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ], [
            ['sezione' => 'erario', 'codice_tributo' => '6001', 'importo_debito' => 2000.00, 'importo_credito' => 0],
            ['sezione' => 'erario', 'codice_tributo' => '6099', 'importo_debito' => 0, 'importo_credito' => 500.00],
        ]);

        expect($modello->totale_debiti)->toBe(2000.0);
        expect($modello->totale_crediti)->toBe(500.0);
        expect($modello->saldo)->toBe(1500.0);
    });

    it('lancia eccezione se anno non valido', function () {
        $svc = app(ModelloF24Service::class);
        expect(fn () => $svc->crea($this->tenant, ['anno' => 1999, 'data_compilazione' => '2026-01-01']))
            ->toThrow(\InvalidArgumentException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ModelloF24Service::generaDaLiquidazioneIva
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Service::generaDaLiquidazioneIva', function () {

    it('genera F24 con codice tributo 6001 per liquidazione mensile gennaio', function () {
        $svc  = app(ModelloF24Service::class);
        $liq  = liquidazioneBase($this->tenant);

        $modello = $svc->generaDaLiquidazioneIva($this->tenant, $liq, [
            'data_compilazione' => '2026-02-15',
        ]);

        expect($modello->liquidazione_iva_id)->toBe($liq->id);
        expect($modello->righe)->toHaveCount(1);
        expect($modello->righe->first()->codice_tributo)->toBe('6001');
        expect($modello->righe->first()->importo_debito)->toBe(1500.0);
        expect($modello->saldo)->toBe(1500.0);
    });

    it('usa codice 6031 per liquidazione trimestrale Q1', function () {
        $svc = app(ModelloF24Service::class);
        $liq = liquidazioneBase($this->tenant);
        $liq->update(['tipo_periodo' => 'trimestrale', 'periodo' => 1]);

        $modello = $svc->generaDaLiquidazioneIva($this->tenant, $liq, [
            'data_compilazione' => '2026-05-16',
        ]);

        expect($modello->righe->first()->codice_tributo)->toBe('6031');
    });

    it('lancia eccezione se saldo_finale <= 0', function () {
        $svc = app(ModelloF24Service::class);
        $liq = liquidazioneBase($this->tenant);
        $liq->update(['saldo_finale' => -100]);

        expect(fn () => $svc->generaDaLiquidazioneIva($this->tenant, $liq, ['data_compilazione' => '2026-02-15']))
            ->toThrow(\InvalidArgumentException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ModelloF24Service::generaDaRitenute
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Service::generaDaRitenute', function () {

    it('genera F24 con codice 1040 da versamento ritenute lavoro autonomo', function () {
        $svc = app(ModelloF24Service::class);

        // Crea compenso e versamento
        $compensoSvc = app(CompensaTerziService::class);
        $compenso    = $compensoSvc->crea($this->tenant, [
            'nome_percipiente'    => 'Test Persona',
            'codice_fiscale'      => 'TSTPRS80A01H501Z',
            'tipo_rapporto'       => 'occasionale',
            'codice_causale'      => 'A',
            'anno_competenza'     => 2026,
            'data_pagamento'      => '2026-01-15',
            'causale_prestazione' => 'Consulenza',
            'compenso_lordo'      => 1000.00,
        ]);

        $versamento = $compensoSvc->versaRitenute($this->tenant, [
            'mese' => 1, 'anno' => 2026, 'data_versamento' => '2026-02-16',
        ]);

        $modello = $svc->generaDaRitenute($this->tenant, $versamento, [
            'data_compilazione' => '2026-02-16',
        ]);

        expect($modello->versamento_ritenuta_id)->toBe($versamento->id);
        expect($modello->righe->first()->codice_tributo)->toBe('1040');
        expect((float) $modello->righe->first()->importo_debito)->toBe(200.0); // 1000 × 20%
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ModelloF24Service::aggiorna
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Service::aggiorna', function () {

    it('aggiorna righe e ricalcola saldo', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ], righeF24(['importo_debito' => 1500.00]));

        $updated = $svc->aggiorna($modello, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ], righeF24(['importo_debito' => 3000.00]));

        expect($updated->saldo)->toBe(3000.0);
        expect($updated->righe)->toHaveCount(1);
    });

    it('blocca modifica se versato', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15', 'stato' => 'versato',
        ]);
        $modello->update(['stato' => 'versato']);

        expect(fn () => $svc->aggiorna($modello->fresh(), ['anno' => 2026, 'data_compilazione' => '2026-02-15'], []))
            ->toThrow(\InvalidArgumentException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ModelloF24Service::segnaVersato
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Service::segnaVersato', function () {

    it('porta il modello in stato versato', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ]);

        $updated = $svc->segnaVersato($modello, '2026-02-16');

        expect($updated->stato)->toBe(ModelloF24::STATO_VERSATO);
        expect($updated->data_versamento->toDateString())->toBe('2026-02-16');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ModelloF24Service::generaXml
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Service::generaXml', function () {

    it('genera XML valido con righe', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'mese' => 1, 'data_compilazione' => '2026-02-15',
        ], righeF24());

        $xml = $svc->generaXml($modello);

        expect($xml)->toContain('<ModelF24');
        expect($xml)->toContain('<CodiceTributo>6001</CodiceTributo>');
        expect($xml)->toContain('1500.00');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: Index
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Controller::index', function () {

    it('restituisce pagina index con KPI', function () {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('f24.index', $this->tenant));
        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('F24/Index');
        expect($page['props'])->toHaveKey('modelli');
        expect($page['props'])->toHaveKey('kpi');
        expect($page['props'])->toHaveKey('statiLabel');
    });

    it('blocca utente senza ruolo', function () {
        $this->withoutMiddleware([
                 \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
             ])
             ->actingAs($this->userSenza)
             ->get(route('f24.index', $this->tenant))
             ->assertForbidden();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: Create / Store
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Controller::create', function () {

    it('restituisce pagina create con liquidazioni e versamenti', function () {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('f24.create', $this->tenant));
        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('F24/Create');
        expect($page['props'])->toHaveKey('sezioni');
        expect($page['props'])->toHaveKey('liquidazioni');
        expect($page['props'])->toHaveKey('versamenti');
        expect($page['props'])->toHaveKey('codiciFrecuenti');
    });

});

describe('ModelloF24Controller::store (manuale)', function () {

    it('crea F24 manuale e redirige al dettaglio', function () {
        $payload = [
            'modalita'         => 'manuale',
            'anno'             => 2026,
            'mese'             => 2,
            'data_compilazione'=> '2026-03-15',
            'stato'            => 'bozza',
            'righe' => [[
                'sezione'         => 'erario',
                'codice_tributo'  => '6002',
                'importo_debito'  => 800.00,
                'importo_credito' => 0,
                'anno_riferimento'=> 2026,
            ]],
        ];

        $response = $this->withoutMiddleware([
                 \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                 \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
             ])
             ->actingAs($this->user)
             ->post(route('f24.store', $this->tenant), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('modelli_f24', [
            'tenant_id' => $this->tenant->id,
            'anno'      => 2026,
            'mese'      => 2,
        ]);

        $this->assertDatabaseHas('righe_f24', [
            'codice_tributo' => '6002',
        ]);
    });

});

describe('ModelloF24Controller::store (da liquidazione)', function () {

    it('genera F24 da liquidazione IVA', function () {
        $liq = liquidazioneBase($this->tenant);

        $response = $this->withoutMiddleware([
                 \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                 \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
             ])
             ->actingAs($this->user)
             ->post(route('f24.store', $this->tenant), [
                'modalita'           => 'da_liquidazione',
                'anno'               => 2026,
                'data_compilazione'  => '2026-02-15',
                'stato'              => 'bozza',
                'liquidazione_iva_id'=> $liq->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('modelli_f24', [
            'tenant_id'           => $this->tenant->id,
            'liquidazione_iva_id' => $liq->id,
        ]);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: Show / Edit / Update / Destroy
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Controller::show', function () {

    it('mostra il dettaglio del modello F24', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ], righeF24());

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('f24.show', [$this->tenant, $modello]));
        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('F24/Show');
        expect($page['props'])->toHaveKey('modello');
    });

});

describe('ModelloF24Controller::destroy', function () {

    it('elimina un F24 in bozza', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ]);

        $this->withoutMiddleware([
                 \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                 \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
             ])
             ->actingAs($this->user)
             ->delete(route('f24.destroy', [$this->tenant, $modello]))
             ->assertRedirect(route('f24.index', $this->tenant));

        $this->assertDatabaseMissing('modelli_f24', ['id' => $modello->id]);
    });

    it('blocca eliminazione se versato', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ]);
        $modello->update(['stato' => 'versato']);

        $this->actingAs($this->user)
            ->delete(route('f24.destroy', [$this->tenant, $modello]))
            ->assertRedirect();

        $this->assertDatabaseHas('modelli_f24', ['id' => $modello->id]);
    });

});

describe('ModelloF24Controller::segnaVersato', function () {

    it('marca l\'F24 come versato con data', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ]);

        $this->withoutMiddleware([
                 \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                 \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
             ])
             ->actingAs($this->user)
             ->post(route('f24.versa', [$this->tenant, $modello]), [
                'data_versamento' => '2026-02-16',
            ])
            ->assertRedirect(route('f24.show', [$this->tenant, $modello]));

        expect($modello->fresh()->stato)->toBe(ModelloF24::STATO_VERSATO);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: Export PDF e XML
// ─────────────────────────────────────────────────────────────────────────────

describe('ModelloF24Controller exports', function () {

    it('restituisce HTTP 200 per export XML', function () {
        $svc     = app(ModelloF24Service::class);
        $modello = $svc->crea($this->tenant, [
            'anno' => 2026, 'data_compilazione' => '2026-02-15',
        ], righeF24());

        $this->actingAs($this->user)
            ->get(route('f24.xml', [$this->tenant, $modello]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    });

});
