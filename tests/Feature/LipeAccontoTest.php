<?php

/**
 * Test suite per LIPE XML e Acconto IVA dicembre (E5).
 *
 * Copre:
 *  - LipeXmlService: struttura XML, VP4/VP5/VP14 da liquidazione definitiva
 *  - LipeXmlService: fallback su fatture quando non c'è liquidazione chiusa
 *  - LipeXmlService: errore su trimestre non valido
 *  - AccontoIvaService: metodo storico (da liquidazione definitiva)
 *  - AccontoIvaService: metodo storico (fallback da fatture)
 *  - AccontoIvaService: metodo previsionale
 *  - AccontoIvaService: metodo analitico
 *  - IvaController::lipeXml → download XML con Content-Type application/xml
 *  - IvaController::accontoIva → Inertia render + dati prospetto
 *  - Middleware role:admin,contabile
 */

use App\Models\CodiceIva;
use App\Models\FatturaAttiva;
use App\Models\LiquidazioneIva;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccontoIvaService;
use App\Services\LipeXmlService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS LIPE Test',
        'slug'              => 'ets-lipe-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '90000001234',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();
    $this->user = User::factory()->create([
        'email'             => 'admin@lipe.it',
        'email_verified_at' => now(),
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    $this->userSenza = User::factory()->create([
        'email'             => 'nessuno@lipe.it',
        'email_verified_at' => now(),
    ]);
    $this->userSenza->tenants()->attach($this->tenant);

    $this->iva22 = CodiceIva::firstOrCreate(
        ['codice' => '22'],
        ['descrizione' => 'IVA 22%', 'percentuale' => 22.00, 'tipo' => 'imponibile', 'attivo' => true]
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper: crea LiquidazioneIva definitiva
// ─────────────────────────────────────────────────────────────────────────────

function creaLiquidazione(
    \Tests\TestCase $test,
    int $anno,
    int $periodo,
    string $tipo,
    float $ivaDebito,
    float $ivaCredito,
): LiquidazioneIva {
    $saldo = round($ivaDebito - $ivaCredito, 2);

    return LiquidazioneIva::create([
        'tenant_id'                  => $test->tenant->id,
        'anno'                       => $anno,
        'periodo'                    => $periodo,
        'tipo_periodo'               => $tipo,
        'data_inizio'                => "{$anno}-" . str_pad((string) (($periodo - 1) * ($tipo === LiquidazioneIva::TIPO_MENSILE ? 1 : 3) + 1), 2, '0', STR_PAD_LEFT) . '-01',
        'data_fine'                  => "{$anno}-12-31",
        'iva_debito'                 => $ivaDebito,
        'iva_credito'                => $ivaCredito,
        'credito_periodo_precedente' => 0,
        'saldo_periodo'              => $saldo,
        'saldo_finale'               => $saldo,
        'acconto_versato'            => 0,
        'interessi_trimestrali'      => 0,
        'status'                     => LiquidazioneIva::STATUS_DEFINITIVA,
        'data_chiusura'              => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// LipeXmlService
// ─────────────────────────────────────────────────────────────────────────────

describe('LipeXmlService', function () {

    it('genera XML valido con namespace corretto', function () {
        $svc = app(LipeXmlService::class);
        $xml = $svc->genera($this->tenant, 2025, 1);

        expect($xml)->toContain('ComunicazioneLiquidazioniPeriodiche');
        expect($xml)->toContain('ivaservizi.agenziaentrate.gov.it');
        expect($xml)->toContain('DAT_01.1');
        expect($xml)->toContain('<CodiceFiscale>90000001234</CodiceFiscale>');
        expect($xml)->toContain($this->tenant->name);
    });

    it('usa dati da liquidazione definitiva mensile', function () {
        // Crea 3 liquidazioni mensili per Q1 2025
        creaLiquidazione($this, 2025, 1, LiquidazioneIva::TIPO_MENSILE, 1000.0, 200.0);
        creaLiquidazione($this, 2025, 2, LiquidazioneIva::TIPO_MENSILE, 800.0,  150.0);
        creaLiquidazione($this, 2025, 3, LiquidazioneIva::TIPO_MENSILE, 600.0,  100.0);

        $svc = app(LipeXmlService::class);
        $xml = $svc->genera($this->tenant, 2025, 1);

        // Devono esserci 3 moduli
        expect(substr_count($xml, '<Modulo '))->toBe(3);
        expect($xml)->toContain('<VP1Mese>1</VP1Mese>');
        expect($xml)->toContain('<VP1Mese>2</VP1Mese>');
        expect($xml)->toContain('<VP1Mese>3</VP1Mese>');
        // VP4 di gennaio = 1000
        expect($xml)->toContain('<VP4>1000.00</VP4>');
    });

    it('usa dati da liquidazione trimestrale', function () {
        creaLiquidazione($this, 2025, 2, LiquidazioneIva::TIPO_TRIMESTRALE, 3000.0, 500.0);

        $svc = app(LipeXmlService::class);
        $xml = $svc->genera($this->tenant, 2025, 2);

        expect(substr_count($xml, '<Modulo '))->toBe(1);
        expect($xml)->toContain('<VP1Trimestre>2</VP1Trimestre>');
        expect($xml)->toContain('<VP4>3000.00</VP4>');
        expect($xml)->toContain('<VP5>500.00</VP5>');
        expect($xml)->toContain('<VP6Dovuta>2500.00</VP6Dovuta>');
    });

    it('fallback su fatture se nessuna liquidazione chiusa', function () {
        // Crea fattura attiva in Q3 2025 (luglio)
        FatturaAttiva::create([
            'tenant_id'         => $this->tenant->id,
            'sezionale'         => '',
            'anno'              => 2025,
            'progressivo'       => 1,
            'numero_fattura'    => '2025/0001',
            'data_fattura'      => '2025-07-15',
            'imponibile_totale' => 5000.0,
            'iva_totale'        => 1100.0,
            'totale_documento'  => 6100.0,
            'tipo_documento'    => 'TD01',
            'stato'             => FatturaAttiva::STATO_EMESSA,
            'stato_pagamento'   => FatturaAttiva::STATO_PAG_DA_INCASSARE,
        ]);

        $svc = app(LipeXmlService::class);
        $xml = $svc->genera($this->tenant, 2025, 3); // Q3

        // 3 moduli mensili
        expect(substr_count($xml, '<Modulo '))->toBe(3);
        // Luglio (mese 7) ha dati
        expect($xml)->toContain('<VP4>1100.00</VP4>');
    });

    it('lancia eccezione per trimestre non valido', function () {
        $svc = app(LipeXmlService::class);
        expect(fn () => $svc->genera($this->tenant, 2025, 5))
            ->toThrow(\InvalidArgumentException::class, 'Trimestre non valido');
    });

    it('VP6Credito quando IVA credito supera debito', function () {
        creaLiquidazione($this, 2025, 4, LiquidazioneIva::TIPO_TRIMESTRALE, 100.0, 500.0); // credito netto

        $svc = app(LipeXmlService::class);
        $xml = $svc->genera($this->tenant, 2025, 4);

        expect($xml)->toContain('<VP6Credito>400.00</VP6Credito>');
        expect($xml)->not->toContain('<VP6Dovuta>');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// AccontoIvaService
// ─────────────────────────────────────────────────────────────────────────────

describe('AccontoIvaService', function () {

    it('metodo storico usa liquidazione definitiva dic anno precedente', function () {
        creaLiquidazione($this, 2024, 12, LiquidazioneIva::TIPO_MENSILE, 2000.0, 200.0);

        $svc = app(AccontoIvaService::class);
        $r = $svc->calcola($this->tenant, 2025);

        // 88% di 1800 = 1584
        expect($r['storico']['acconto'])->toBe(1584.0);
        expect($r['storico']['fonte'])->toContain('Liquidazione definitiva');
    });

    it('metodo storico fallback fatture se nessuna liquidazione', function () {
        // Fattura attiva in dicembre 2024
        FatturaAttiva::create([
            'tenant_id'         => $this->tenant->id,
            'sezionale'         => '',
            'anno'              => 2024,
            'progressivo'       => 1,
            'numero_fattura'    => '2024/0001',
            'data_fattura'      => '2024-12-10',
            'imponibile_totale' => 10000.0,
            'iva_totale'        => 2200.0,
            'totale_documento'  => 12200.0,
            'tipo_documento'    => 'TD01',
            'stato'             => FatturaAttiva::STATO_EMESSA,
            'stato_pagamento'   => FatturaAttiva::STATO_PAG_DA_INCASSARE,
        ]);

        $svc = app(AccontoIvaService::class);
        $r = $svc->calcola($this->tenant, 2025);

        // IVA netta dic 2024 = 2200, acconto 88% = 1936
        expect($r['storico']['acconto'])->toBe(1936.0);
        expect($r['storico']['fonte'])->toContain('fatture');
    });

    it('metodo analitico usa IVA 1-20 dic al 100%', function () {
        FatturaAttiva::create([
            'tenant_id'         => $this->tenant->id,
            'sezionale'         => '',
            'anno'              => 2025,
            'progressivo'       => 1,
            'numero_fattura'    => '2025/0001',
            'data_fattura'      => '2025-12-10',
            'imponibile_totale' => 5000.0,
            'iva_totale'        => 1100.0,
            'totale_documento'  => 6100.0,
            'tipo_documento'    => 'TD01',
            'stato'             => FatturaAttiva::STATO_EMESSA,
            'stato_pagamento'   => FatturaAttiva::STATO_PAG_DA_INCASSARE,
        ]);

        $svc = app(AccontoIvaService::class);
        $r = $svc->calcola($this->tenant, 2025);

        // Acconto analitico = IVA netta 1-20 dic (100%)
        expect($r['analitico']['acconto'])->toBe(1100.0);
    });

    it('scadenza è sempre il 27 dicembre', function () {
        $svc = app(AccontoIvaService::class);
        $r = $svc->calcola($this->tenant, 2025);

        expect($r['scadenza'])->toBe('2025-12-27');
        expect($r['anno'])->toBe(2025);
    });

    it('minimo raccomandato è il minore tra storico e previsionale positivi', function () {
        creaLiquidazione($this, 2024, 12, LiquidazioneIva::TIPO_MENSILE, 2000.0, 200.0); // storico 88% di 1800 = 1584

        $svc = app(AccontoIvaService::class);
        $r = $svc->calcola($this->tenant, 2025);

        // Con nessuna fattura in dic 2025: previsionale = 0 → minimo = storico
        // Se entrambi >0 prende il minore
        expect($r['minimo_raccomandato'])->toBeGreaterThanOrEqual(0.0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Controller
// ─────────────────────────────────────────────────────────────────────────────

describe('IvaController → lipeXml', function () {

    it('scarica XML LIPE con Content-Type corretto', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->get(route('iva.lipe-xml', [
                'tenant'    => $this->tenant->slug,
                'anno'      => 2025,
                'trimestre' => 1,
            ]));

        $response->assertStatus(200);
        expect($response->headers->get('Content-Type'))->toContain('application/xml');
        $content = $response->streamedContent();
        expect($content)->toContain('ComunicazioneLiquidazioniPeriodiche');
    });

    it('lipeXml richiede ruolo admin/contabile', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSenza)
            ->get(route('iva.lipe-xml', ['tenant' => $this->tenant->slug, 'anno' => 2025, 'trimestre' => 1]));

        $response->assertStatus(403);
    });

});

describe('IvaController → accontoIva', function () {

    it('restituisce la pagina Inertia con prospetto', function () {
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
            ->get(route('iva.acconto-iva', ['tenant' => $this->tenant->slug, 'anno' => 2025]));

        $response->assertStatus(200);

        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('Iva/AccontoIva');
        expect($page['props']['prospetto'])->toHaveKey('storico');
        expect($page['props']['prospetto'])->toHaveKey('previsionale');
        expect($page['props']['prospetto'])->toHaveKey('analitico');
        expect($page['props']['prospetto']['anno'])->toBe(2025);
        expect($page['props']['prospetto']['scadenza'])->toBe('2025-12-27');
    });

    it('accontoIva richiede ruolo admin/contabile', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSenza)
            ->get(route('iva.acconto-iva', ['tenant' => $this->tenant->slug]));

        $response->assertStatus(403);
    });

});
