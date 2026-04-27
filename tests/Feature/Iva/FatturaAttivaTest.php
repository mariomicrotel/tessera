<?php

/**
 * Test suite per Fatture Attive (F-ATT).
 *
 * Copre:
 *  - FatturaAttivaService::crea (bozza, emessa con e senza conti)
 *  - FatturaAttivaService::aggiorna
 *  - FatturaAttivaService::registraPagamento (incasso pieno e parziale)
 *  - FatturaAttivaService::storna (nota di credito TD04)
 *  - FatturaAttivaService::calcolaNumeroProgressivo
 *  - Controller: index, create, store, show, edit, update, paga, storna, destroy, pdf
 *  - Middleware role:admin,contabile
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CodiceIva;
use App\Models\FatturaAttiva;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FatturaAttivaService;
use App\Services\FatturaXmlService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS FattAtt Test',
        'slug'              => 'ets-fatt-att-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000001234',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    $this->userSenza = User::factory()->create(['email_verified_at' => now()]);
    $this->userSenza->tenants()->attach($this->tenant);

    $this->iva22 = CodiceIva::firstOrCreate(
        ['codice' => '22'],
        ['descrizione' => 'IVA 22%', 'percentuale' => 22.00, 'tipo' => 'imponibile', 'attivo' => true]
    );

    $this->iva0 = CodiceIva::firstOrCreate(
        ['codice' => '0ES'],
        ['descrizione' => 'Esente', 'percentuale' => 0.00, 'tipo' => 'esente', 'attivo' => true]
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper
// ─────────────────────────────────────────────────────────────────────────────

function righeBase(CodiceIva $iva): array
{
    return [
        [
            'codice_iva_id'      => $iva->id,
            'descrizione'        => 'Servizio di consulenza',
            'quantita'           => 2,
            'prezzo_unitario'    => 500.00,
            'sconto_percentuale' => 0,
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// FatturaAttivaService
// ─────────────────────────────────────────────────────────────────────────────

describe('FatturaAttivaService::crea', function () {

    it('crea fattura in bozza senza movimenti', function () {
        $svc  = app(FatturaAttivaService::class);
        $data = [
            'anno'           => 2026,
            'data_fattura'   => '2026-01-15',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'bozza',
        ];

        $fattura = $svc->crea($this->tenant, $data, righeBase($this->iva22));

        expect($fattura->stato)->toBe('bozza');
        expect($fattura->righe)->toHaveCount(1);
        expect((float)$fattura->imponibile_totale)->toBe(1000.0);
        expect((float)$fattura->iva_totale)->toBe(220.0);
        expect((float)$fattura->totale_documento)->toBe(1220.0);
        expect($fattura->numero_fattura)->toStartWith('FT-2026-');
    });

    it('crea fattura emessa con numero progressivo corretto', function () {
        $svc = app(FatturaAttivaService::class);

        $f1 = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-01-10',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $f2 = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-01-20',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        expect($f1->numero_fattura)->toBe('FT-2026-0001');
        expect($f2->numero_fattura)->toBe('FT-2026-0002');
    });

    it('calcola imponibile con sconto percentuale', function () {
        $svc = app(FatturaAttivaService::class);
        $righe = [[
            'codice_iva_id'      => $this->iva22->id,
            'descrizione'        => 'Prodotto con sconto',
            'quantita'           => 1,
            'prezzo_unitario'    => 100.00,
            'sconto_percentuale' => 10.0, // 10% → imponibile 90
        ]];

        $f = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-02-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'bozza',
        ], $righe);

        expect((float)$f->imponibile_totale)->toBe(90.0);
        expect((float)$f->iva_totale)->toBe(19.8);
        expect((float)$f->totale_documento)->toBe(109.8);
    });

});

describe('FatturaAttivaService::aggiorna', function () {

    it('aggiorna righe e ricalcola totali', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-03-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'bozza',
        ], righeBase($this->iva22));

        $nuoveRighe = [[
            'codice_iva_id'      => $this->iva0->id,
            'descrizione'        => 'Quota associativa',
            'quantita'           => 1,
            'prezzo_unitario'    => 200.00,
            'sconto_percentuale' => 0,
        ]];

        $svc->aggiorna($fattura, ['data_fattura' => '2026-03-15', 'esigibilita' => 'immediata'], $nuoveRighe);
        $fattura->refresh();

        expect($fattura->righe)->toHaveCount(1);
        expect((float)$fattura->imponibile_totale)->toBe(200.0);
        expect((float)$fattura->iva_totale)->toBe(0.0);
        expect((float)$fattura->totale_documento)->toBe(200.0);
    });

    it('lancia eccezione se fattura è già incassata', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-03-20',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        // Simula pagamento completo → stato_pagamento incassata
        $svc->registraPagamento($fattura, [
            'importo'        => 1220.00,
            'data_pagamento' => '2026-03-25',
        ]);
        $fattura->refresh();

        // aggiorna deve lanciare eccezione perché stato_pagamento != da_incassare
        expect(fn () => $svc->aggiorna($fattura, ['data_fattura' => '2026-04-01', 'esigibilita' => 'immediata'], righeBase($this->iva22)))
            ->toThrow(InvalidArgumentException::class);
    });

});

describe('FatturaAttivaService::registraPagamento', function () {

    it('marca la fattura come incassata', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-04-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $svc->registraPagamento($fattura, [
            'importo'        => 1220.00,
            'data_pagamento' => '2026-04-10',
        ]);

        $fattura->refresh();
        expect($fattura->stato_pagamento)->toBe('incassata');
    });

    it('marca la fattura come parzialmente incassata', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-04-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $svc->registraPagamento($fattura, [
            'importo'        => 500.00,
            'data_pagamento' => '2026-04-05',
        ]);

        $fattura->refresh();
        expect($fattura->stato_pagamento)->toBe('parzialmente_incassata');
    });

});

describe('FatturaAttivaService::storna', function () {

    it('genera nota di credito TD04 e annulla originale', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-05-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $nc = $svc->storna($fattura);

        $fattura->refresh();
        expect($fattura->stato)->toBe('annullata');
        expect($nc->tipo_documento)->toBe('TD04');
        expect($nc->sezionale)->toBe('NC');
        expect($nc->righe)->toHaveCount(1);
        expect((float)$nc->imponibile_totale)->toBe(1000.0);
    });

    it('lancia eccezione se la fattura non è emessa e da incassare', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-06-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'bozza',
        ], righeBase($this->iva22));

        expect(fn () => $svc->storna($fattura))
            ->toThrow(InvalidArgumentException::class);
    });

});

describe('FatturaAttivaService::calcolaNumeroProgressivo', function () {

    it('restituisce FT-ANNO-0001 per il primo documento', function () {
        $svc = app(FatturaAttivaService::class);
        $n   = $svc->calcolaNumeroProgressivo($this->tenant, 2026);
        expect($n)->toBe('FT-2026-0001');
    });

    it('restituisce il numero corretto dopo inserimenti', function () {
        FatturaAttiva::create([
            'tenant_id'         => $this->tenant->id,
            'anno'              => 2026,
            'progressivo'       => 1,
            'numero_fattura'    => 'FT-2026-0001',
            'data_fattura'      => '2026-01-01',
            'imponibile_totale' => 100,
            'iva_totale'        => 22,
            'totale_documento'  => 122,
            'tipo_documento'    => 'TD01',
            'stato'             => 'emessa',
            'stato_pagamento'   => 'da_incassare',
        ]);

        $svc = app(FatturaAttivaService::class);
        $n   = $svc->calcolaNumeroProgressivo($this->tenant, 2026);
        expect($n)->toBe('FT-2026-0002');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Controller
// ─────────────────────────────────────────────────────────────────────────────

function withoutAuthMiddleware($test)
{
    return $test->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
    ]);
}

function inertiaVersion(): string
{
    return app(HandleInertiaRequests::class)->version(request()) ?? '';
}

describe('FatturaAttivaController::index', function () {

    it('restituisce lista fatture (Inertia JSON)', function () {
        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('iva.fatture-attive.index', $this->tenant->slug));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('Iva/FattureAttive/Index');
        expect($page['props'])->toHaveKey('fatture');
        expect($page['props'])->toHaveKey('kpi');
    });

});

describe('FatturaAttivaController::create', function () {

    it('restituisce pagina Create (Inertia JSON)', function () {
        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('iva.fatture-attive.create', $this->tenant->slug));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('Iva/FattureAttive/Create');
        expect($page['props'])->toHaveKey('codiciIva');
        expect($page['props'])->toHaveKey('clienti');
        expect($page['props'])->toHaveKey('numeroSuggerito');
    });

    it('crea richiede ruolo admin/contabile', function () {
        $response = withoutAuthMiddleware($this)
            ->actingAs($this->userSenza)
            ->get(route('iva.fatture-attive.create', $this->tenant->slug));

        $response->assertStatus(403);
    });

});

describe('FatturaAttivaController::store', function () {

    it('crea fattura bozza con redirect a show', function () {
        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.fatture-attive.store', $this->tenant->slug), [
                'anno'           => 2026,
                'data_fattura'   => '2026-07-01',
                'tipo_documento' => 'TD01',
                'esigibilita'    => 'immediata',
                'stato'          => 'bozza',
                'righe'          => [[
                    'codice_iva_id'      => $this->iva22->id,
                    'descrizione'        => 'Servizio test',
                    'quantita'           => 1,
                    'prezzo_unitario'    => 100.00,
                    'sconto_percentuale' => 0,
                ]],
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('fatture_attive', [
            'tenant_id'      => $this->tenant->id,
            'tipo_documento' => 'TD01',
            'stato'          => 'bozza',
        ]);
    });

    it('store richiede ruolo admin/contabile', function () {
        $response = withoutAuthMiddleware($this)
            ->actingAs($this->userSenza)
            ->post(route('iva.fatture-attive.store', $this->tenant->slug), []);

        $response->assertStatus(403);
    });

});

describe('FatturaAttivaController::show', function () {

    it('restituisce pagina Show (Inertia JSON)', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-07-10',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'bozza',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('iva.fatture-attive.show', [$this->tenant->slug, $fattura->id]));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('Iva/FattureAttive/Show');
        expect($page['props']['fattura']['id'])->toBe($fattura->id);
    });

});

describe('FatturaAttivaController::destroy', function () {

    it('elimina fattura bozza', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-08-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'bozza',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->delete(route('iva.fatture-attive.destroy', [$this->tenant->slug, $fattura->id]));

        $response->assertStatus(302);
        $this->assertSoftDeleted('fatture_attive', ['id' => $fattura->id]);
    });

    it('non elimina fatture emesse', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-08-10',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->delete(route('iva.fatture-attive.destroy', [$this->tenant->slug, $fattura->id]));

        $response->assertStatus(302);
        $this->assertDatabaseHas('fatture_attive', ['id' => $fattura->id, 'deleted_at' => null]);
    });

});

describe('FatturaAttivaController::paga', function () {

    it('registra pagamento e reindirizza a show', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-09-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.fatture-attive.paga', [$this->tenant->slug, $fattura->id]), [
                'importo'        => 1220.00,
                'data_pagamento' => '2026-09-15',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('fatture_attive', [
            'id'              => $fattura->id,
            'stato_pagamento' => 'incassata',
        ]);
    });

});

describe('FatturaAttivaController::storna', function () {

    it('genera nota di credito e reindirizza', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-10-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.fatture-attive.storna', [$this->tenant->slug, $fattura->id]));

        $response->assertStatus(302);
        $fattura->refresh();
        expect($fattura->stato)->toBe('annullata');
        $this->assertDatabaseHas('fatture_attive', [
            'tipo_documento' => 'TD04',
            'sezionale'      => 'NC',
        ]);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// F3: Nota di Credito Attiva (TD04)
// ─────────────────────────────────────────────────────────────────────────────

describe('FatturaAttivaService::creaNdiCredito', function () {

    it('crea NC per storno totale con righe inverse', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-11-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $nc = $svc->creaNdiCredito($fattura, [
            'tipo_storno'         => 'totale',
            'motivo_nota_credito' => 'Reso cliente',
        ]);

        expect($nc->tipo_documento)->toBe('TD04');
        expect($nc->stato)->toBe('emessa');
        expect($nc->fattura_collegata_id)->toBe($fattura->id);
        expect($nc->motivo_nota_credito)->toBe('Reso cliente');

        // Verifica righe invertite
        expect($nc->righe)->toHaveCount(1);
        $riga = $nc->righe->first();
        expect($riga->quantita)->toBe(-2.0);
        expect($riga->imponibile)->toBeLessThan(0);
        expect($riga->iva)->toBeLessThan(0);
        expect($riga->totale)->toBeLessThan(0);

        // Totali sono negativi
        expect($nc->imponibile_totale)->toBeLessThan(0);
        expect($nc->iva_totale)->toBeLessThan(0);
        expect($nc->totale_documento)->toBeLessThan(0);
    });

    it('crea NC per storno parziale con importo ridotto', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-11-05',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $importoTotale = (float) $fattura->totale_documento;
        $importoMeta = $importoTotale / 2;

        $nc = $svc->creaNdiCredito($fattura, [
            'tipo_storno'         => 'parziale',
            'importo_storno'      => $importoMeta,
            'motivo_nota_credito' => 'Reso parziale',
        ]);

        expect($nc->tipo_documento)->toBe('TD04');
        expect(abs($nc->totale_documento))->toBeCloseTo($importoMeta, 2);
    });

    it('blocca creazione NC se fattura non è emessa', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-11-10',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'bozza',
        ], righeBase($this->iva22));

        expect(function () use ($svc, $fattura) {
            $svc->creaNdiCredito($fattura, [
                'tipo_storno'         => 'totale',
                'motivo_nota_credito' => 'Reso',
            ]);
        })->toThrow(InvalidArgumentException::class);
    });

});

describe('FatturaAttivaController::creaNotaCredito', function () {

    it('mostra form con dati prefillati (GET)', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-11-15',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('iva.fatture-attive.crea-nota-credito', [$this->tenant->slug, $fattura->id]));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('Iva/FattureAttive/CreateNotaCredito');
        expect($page['props']['fattura']['id'])->toBe($fattura->id);
    });

});

describe('FatturaAttivaController::storeNotaCredito', function () {

    it('crea NC e reindirizza a show (POST)', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-11-20',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(
                route('iva.fatture-attive.store-nota-credito', [$this->tenant->slug, $fattura->id]),
                [
                    'tipo_storno'         => 'totale',
                    'motivo_nota_credito' => 'Reso completo',
                ]
            );

        $response->assertStatus(302);
        $this->assertDatabaseHas('fatture_attive', [
            'tipo_documento'      => 'TD04',
            'fattura_collegata_id' => $fattura->id,
            'motivo_nota_credito' => 'Reso completo',
        ]);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// F2: Fattura Elettronica XML — FatturaXmlService
// ─────────────────────────────────────────────────────────────────────────────

describe('FatturaXmlService::generaStringa', function () {

    it('genera XML valido con tag FatturaElettronica', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-12-01',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $xml = app(FatturaXmlService::class)->generaStringa($fattura);

        expect($xml)->toContain('FatturaElettronica');
        expect($xml)->toContain('FPR12');
        expect($xml)->toContain('TD01');
        expect($xml)->toContain($fattura->numero_fattura);
        expect($xml)->toContain('EUR');
    });

    it('include DatiRiepilogo con aliquota IVA 22%', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-12-02',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $xml = app(FatturaXmlService::class)->generaStringa($fattura);

        expect($xml)->toContain('<AliquotaIVA>22.00</AliquotaIVA>');
        expect($xml)->toContain('<Imposta>220.00</Imposta>');
        expect($xml)->toContain('<ImponibileImporto>1000.00</ImponibileImporto>');
    });

    it('genera XML per nota di credito TD04 con riferimento fattura originale', function () {
        $svc = app(FatturaAttivaService::class);

        $fatturaOriginale = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-12-05',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $nc = $svc->creaNdiCredito($fatturaOriginale, [
            'tipo_storno'         => 'totale',
            'motivo_nota_credito' => 'Reso merce',
        ]);

        $xml = app(FatturaXmlService::class)->generaStringa($nc);

        expect($xml)->toContain('<TipoDocumento>TD04</TipoDocumento>');
        expect($xml)->toContain($fatturaOriginale->numero_fattura);
        expect($xml)->toContain('Reso merce');
    });

    it('include EsigibilitaIVA immediata (I)', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-12-10',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $xml = app(FatturaXmlService::class)->generaStringa($fattura);
        expect($xml)->toContain('<EsigibilitaIVA>I</EsigibilitaIVA>');
    });

    it('genera XML per fattura con IVA esente (natura N4)', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-12-15',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva0));

        $xml = app(FatturaXmlService::class)->generaStringa($fattura);

        expect($xml)->toContain('<AliquotaIVA>0.00</AliquotaIVA>');
        expect($xml)->toContain('<Natura>N4</Natura>');
    });

});

describe('FatturaAttivaController::downloadXml', function () {

    it('scarica XML per fattura emessa', function () {
        \Illuminate\Support\Facades\Storage::fake('private');

        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-12-20',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->get(route('iva.fatture-attive.xml', [$this->tenant->slug, $fattura->id]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
    });

    it('blocca download XML per fattura in bozza', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-12-22',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'bozza',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->get(route('iva.fatture-attive.xml', [$this->tenant->slug, $fattura->id]));

        $response->assertStatus(302);
    });

});

describe('FatturaAttivaController::aggiornaStatoSdi', function () {

    it('aggiorna stato a inviata_sdi con identificativo', function () {
        $svc     = app(FatturaAttivaService::class);
        $fattura = $svc->crea($this->tenant, [
            'anno'           => 2026,
            'data_fattura'   => '2026-12-25',
            'tipo_documento' => 'TD01',
            'esigibilita'    => 'immediata',
            'stato'          => 'emessa',
        ], righeBase($this->iva22));

        $response = withoutAuthMiddleware($this)
            ->actingAs($this->user)
            ->post(route('iva.fatture-attive.sdi', [$this->tenant->slug, $fattura->id]), [
                'stato'              => 'inviata_sdi',
                'sdi_identificativo' => 'SDI-TEST-001',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('fatture_attive', [
            'id'                 => $fattura->id,
            'stato'              => 'inviata_sdi',
            'sdi_identificativo' => 'SDI-TEST-001',
        ]);
    });

});
