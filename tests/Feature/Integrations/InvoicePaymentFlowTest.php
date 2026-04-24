<?php

/**
 * Integration test: Flusso Fattura Passiva → Pagamento.
 *
 * Copre:
 *  - FatturaPassivaService::registra (creazione con righe, calcolo totali)
 *  - Ciclo stati: da_pagare → parzialmente_pagata → pagata → annullata
 *  - FatturaPassivaService::aggiorna (ricalcolo righe)
 *  - FatturaPassivaService::annulla + guard pagamento su annullata
 *  - calcolaTotaliPreview (calcolo senza persistenza)
 *  - isReadOnly() su fattura non agganciata a liquidazione
 */

use App\Models\CodiceIva;
use App\Models\FatturaPassiva;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\FatturaPassivaService;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Invoice Flow',
        'slug'              => 'ets-invoice-flow-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    $this->service = app(FatturaPassivaService::class);

    // Fornitore
    $this->supplier = Supplier::create([
        'name'          => 'Fornitore Test SRL',
        'ragione_sociale' => 'Fornitore Test SRL',
        'partita_iva'   => '12345678901',
        'attivo'        => true,
    ]);

    // Codice IVA 22%
    $this->codiceIva22 = CodiceIva::create([
        'codice'                   => 'IVA22',
        'descrizione'              => 'IVA 22%',
        'percentuale'              => 22.00,
        'tipo'                     => 'vendita',
        'indetraibile_percentuale' => 0.00,
        'attivo'                   => true,
        'di_sistema'               => false,
    ]);

    // Codice IVA 0%
    $this->codiceIvaEs = CodiceIva::create([
        'codice'                   => 'ES',
        'descrizione'              => 'Esente Art.10',
        'percentuale'              => 0.00,
        'tipo'                     => 'vendita',
        'natura_sdi'               => 'N4',
        'indetraibile_percentuale' => 0.00,
        'attivo'                   => true,
        'di_sistema'               => false,
    ]);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// Creazione fattura
// ─────────────────────────────────────────────────────────────────────────────

describe('FatturaPassivaService → registra', function () {

    it('crea fattura con righe e calcola totali correttamente', function () {
        $fattura = $this->service->registra(
            testata: [
                'supplier_id'        => $this->supplier->id,
                'numero_fattura'     => 'FT-2025-001',
                'data_fattura'       => '2025-03-01',
                'data_registrazione' => '2025-03-05',
                'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
            ],
            righe: [
                [
                    'codice_iva_id'   => $this->codiceIva22->id,
                    'descrizione'     => 'Servizi consulenza',
                    'quantita'        => 2,
                    'prezzo_unitario' => 500.00,
                ],
            ]
        );

        // imponibile = 2 × 500 = 1000, IVA 22% = 220, totale = 1220
        expect($fattura)->toBeInstanceOf(FatturaPassiva::class)
            ->and((float) $fattura->imponibile_totale)->toBe(1000.0)
            ->and((float) $fattura->iva_totale)->toBe(220.0)
            ->and((float) $fattura->totale_documento)->toBe(1220.0)
            ->and($fattura->righe()->count())->toBe(1)
            ->and($fattura->stato_pagamento)->toBe(FatturaPassiva::STATO_DA_PAGARE);
    });

    it('crea fattura con più righe e IVA mista', function () {
        $fattura = $this->service->registra(
            testata: [
                'supplier_id'        => $this->supplier->id,
                'numero_fattura'     => 'FT-2025-002',
                'data_fattura'       => '2025-03-10',
                'data_registrazione' => '2025-03-10',
                'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
            ],
            righe: [
                [
                    'codice_iva_id'   => $this->codiceIva22->id,
                    'descrizione'     => 'Bene soggetto IVA',
                    'quantita'        => 1,
                    'prezzo_unitario' => 100.00,
                ],
                [
                    'codice_iva_id'   => $this->codiceIvaEs->id,
                    'descrizione'     => 'Servizio esente',
                    'quantita'        => 1,
                    'prezzo_unitario' => 200.00,
                ],
            ]
        );

        // 100+22 + 200+0 = 322 totale
        expect((float) $fattura->totale_documento)->toBe(322.0)
            ->and($fattura->righe()->count())->toBe(2);
    });

    it('lancia InvalidArgumentException se righe vuote', function () {
        expect(fn () => $this->service->registra(
            testata: [
                'supplier_id'        => $this->supplier->id,
                'numero_fattura'     => 'FT-ERR',
                'data_fattura'       => '2025-03-01',
                'data_registrazione' => '2025-03-01',
                'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
            ],
            righe: []
        ))->toThrow(InvalidArgumentException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Ciclo stati
// ─────────────────────────────────────────────────────────────────────────────

describe('FatturaPassivaService → ciclo stati', function () {

    beforeEach(function () {
        $this->fattura = $this->service->registra(
            testata: [
                'supplier_id'        => $this->supplier->id,
                'numero_fattura'     => 'FT-2025-CICLO',
                'data_fattura'       => '2025-04-01',
                'data_registrazione' => '2025-04-01',
                'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
            ],
            righe: [[
                'codice_iva_id'   => $this->codiceIva22->id,
                'descrizione'     => 'Servizio',
                'quantita'        => 1,
                'prezzo_unitario' => 800.00,
            ]]
        );
    });

    it('marcaPagata porta la fattura in stato pagata', function () {
        $this->service->marcaPagata($this->fattura);
        $this->fattura->refresh();

        expect($this->fattura->stato_pagamento)->toBe(FatturaPassiva::STATO_PAGATA);
    });

    it('marcaParzialmentePagata → reimpostaDaPagare ciclo corretto', function () {
        $this->service->marcaParzialmentePagata($this->fattura);
        $this->fattura->refresh();
        expect($this->fattura->stato_pagamento)->toBe(FatturaPassiva::STATO_PARZIALMENTE_PAGATA);

        $this->service->reimpostaDaPagare($this->fattura);
        $this->fattura->refresh();
        expect($this->fattura->stato_pagamento)->toBe(FatturaPassiva::STATO_DA_PAGARE);
    });

    it('annulla porta la fattura in stato annullata', function () {
        $this->service->annulla($this->fattura);
        $this->fattura->refresh();

        expect($this->fattura->stato_pagamento)->toBe(FatturaPassiva::STATO_ANNULLATA);
    });

    it('marcaPagata su fattura annullata lancia eccezione', function () {
        $this->service->annulla($this->fattura);
        $this->fattura->refresh();

        expect(fn () => $this->service->marcaPagata($this->fattura))
            ->toThrow(InvalidArgumentException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Aggiornamento e preview
// ─────────────────────────────────────────────────────────────────────────────

describe('FatturaPassivaService → aggiorna e preview', function () {

    it('aggiorna righe e ricalcola totali', function () {
        $fattura = $this->service->registra(
            testata: [
                'supplier_id'        => $this->supplier->id,
                'numero_fattura'     => 'FT-2025-AGG',
                'data_fattura'       => '2025-05-01',
                'data_registrazione' => '2025-05-01',
                'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
            ],
            righe: [[
                'codice_iva_id'   => $this->codiceIva22->id,
                'descrizione'     => 'Originale',
                'quantita'        => 1,
                'prezzo_unitario' => 100.00,
            ]]
        );

        $aggiornata = $this->service->aggiorna(
            fattura: $fattura,
            testata: ['data_fattura' => '2025-05-01', 'data_registrazione' => '2025-05-01'],
            righe: [[
                'codice_iva_id'   => $this->codiceIvaEs->id,
                'descrizione'     => 'Servizio esente aggiornato',
                'quantita'        => 3,
                'prezzo_unitario' => 200.00,
            ]]
        );

        // 3 × 200 = 600, IVA 0%, totale = 600
        expect((float) $aggiornata->imponibile_totale)->toBe(600.0)
            ->and((float) $aggiornata->iva_totale)->toBe(0.0)
            ->and((float) $aggiornata->totale_documento)->toBe(600.0)
            ->and($aggiornata->righe()->count())->toBe(1);
    });

    it('calcolaTotaliPreview restituisce importi senza persistere', function () {
        $preview = $this->service->calcolaTotaliPreview([
            [
                'codice_iva_id'   => $this->codiceIva22->id,
                'quantita'        => 2,
                'prezzo_unitario' => 250.00,
            ],
        ]);

        expect($preview['imponibile_totale'])->toBe(500.0)
            ->and($preview['iva_totale'])->toBe(110.0)
            ->and($preview['totale_documento'])->toBe(610.0);

        // Nessuna fattura creata
        expect(FatturaPassiva::count())->toBe(0);
    });

    it('fattura non agganciata a liquidazione non è isReadOnly', function () {
        $fattura = $this->service->registra(
            testata: [
                'supplier_id'        => $this->supplier->id,
                'numero_fattura'     => 'FT-2025-RO',
                'data_fattura'       => '2025-06-01',
                'data_registrazione' => '2025-06-01',
                'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
            ],
            righe: [[
                'codice_iva_id'   => $this->codiceIva22->id,
                'descrizione'     => 'Test',
                'quantita'        => 1,
                'prezzo_unitario' => 50.00,
            ]]
        );

        expect($fattura->isReadOnly())->toBeFalse();
    });

});
