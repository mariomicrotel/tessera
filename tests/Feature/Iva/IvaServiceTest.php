<?php

use App\Exceptions\IvaAlreadyClosedException;
use App\Exceptions\IvaPeriodoNonValidoException;
use App\Models\CodiceIva;
use App\Models\Conto;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\LiquidazioneIva;
use App\Models\PrimaNotaEntry;
use App\Models\RigaFatturaAttiva;
use App\Models\RigaFatturaPassiva;
use App\Models\Tenant;
use App\Services\IvaService;
use Database\Seeders\CodiciIvaDiSistemaSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup condiviso
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    // Tenant cooperativa con codici IVA di sistema
    $this->tenant = Tenant::create([
        'name'              => 'Coop IVA Test',
        'slug'              => 'coop-iva-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    // Il TenantObserver precarica i codici — se non fosse registrato, lo facciamo a mano
    if (CodiceIva::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->doesntExist()) {
        (new CodiciIvaDiSistemaSeeder)->perTenant($this->tenant);
    }

    $this->iva22 = CodiceIva::where('codice', '22')->first();
    $this->iva10 = CodiceIva::where('codice', '10')->first();

    $this->service = new IvaService();
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// Helper: crea una fattura passiva con una riga
function creaFatturaPassiva(
    Tenant $tenant,
    CodiceIva $codice,
    float $imponibile,
    string $dataRegistrazione,
    float $indetraibile = 0,
): FatturaPassiva {
    static $seq = 0;
    $seq++;

    $aliquota = (float) $codice->percentuale;
    $iva = round($imponibile * $aliquota / 100, 2);
    $ivaIndetraibile = round($iva * $indetraibile / 100, 2);

    $fp = FatturaPassiva::create([
        'numero_fattura'     => "FP-TEST-{$seq}",
        'data_fattura'       => $dataRegistrazione,
        'data_registrazione' => $dataRegistrazione,
        'stato_pagamento'    => FatturaPassiva::STATO_DA_PAGARE,
        'imponibile_totale'  => $imponibile,
        'iva_totale'         => $iva,
        'totale_documento'   => $imponibile + $iva,
    ]);

    RigaFatturaPassiva::create([
        'fattura_passiva_id'       => $fp->id,
        'codice_iva_id'            => $codice->id,
        'descrizione'              => 'Riga test',
        'quantita'                 => 1,
        'prezzo_unitario'          => $imponibile,
        'imponibile'               => $imponibile,
        'iva'                      => $iva,
        'totale'                   => $imponibile + $iva,
        'indetraibile_percentuale' => $indetraibile,
        'iva_indetraibile'         => $ivaIndetraibile,
    ]);

    return $fp;
}

// Helper: crea una fattura attiva con una riga
function creaFatturaAttiva(
    Tenant $tenant,
    CodiceIva $codice,
    float $imponibile,
    string $dataFattura,
    string $stato = FatturaAttiva::STATO_EMESSA,
): FatturaAttiva {
    static $seq = 0;
    $seq++;

    $aliquota = (float) $codice->percentuale;
    $iva = round($imponibile * $aliquota / 100, 2);

    $fa = FatturaAttiva::create([
        'sezionale'         => '',
        'anno'              => (int) substr($dataFattura, 0, 4),
        'progressivo'       => $seq,
        'numero_fattura'    => "FA-TEST-{$seq}",
        'data_fattura'      => $dataFattura,
        'stato'             => $stato,
        'stato_pagamento'   => FatturaAttiva::STATO_PAG_DA_INCASSARE,
        'imponibile_totale' => $imponibile,
        'iva_totale'        => $iva,
        'totale_documento'  => $imponibile + $iva,
    ]);

    RigaFatturaAttiva::create([
        'fattura_attiva_id'  => $fa->id,
        'codice_iva_id'      => $codice->id,
        'descrizione'        => 'Riga test',
        'quantita'           => 1,
        'prezzo_unitario'    => $imponibile,
        'sconto_percentuale' => 0,
        'imponibile'         => $imponibile,
        'iva'                => $iva,
        'totale'             => $imponibile + $iva,
    ]);

    return $fa;
}

// ─────────────────────────────────────────────────────────────────────────────
// rangeDate
// ─────────────────────────────────────────────────────────────────────────────

describe('rangeDate', function () {

    test('mensile restituisce il primo e ultimo giorno del mese', function () {
        $service = new IvaService();
        [$inizio, $fine] = $service->rangeDate(2026, 4, 'mensile');

        expect($inizio)->toBe('2026-04-01');
        expect($fine)->toBe('2026-04-30');
    });

    test('trimestrale Q1 va da gennaio a marzo', function () {
        [$inizio, $fine] = $this->service->rangeDate(2026, 1, 'trimestrale');

        expect($inizio)->toBe('2026-01-01');
        expect($fine)->toBe('2026-03-31');
    });

    test('trimestrale Q4 va da ottobre a dicembre', function () {
        [$inizio, $fine] = $this->service->rangeDate(2026, 4, 'trimestrale');

        expect($inizio)->toBe('2026-10-01');
        expect($fine)->toBe('2026-12-31');
    });

    test('lancia eccezione per mese invalido', function () {
        expect(fn () => $this->service->rangeDate(2026, 13, 'mensile'))
            ->toThrow(IvaPeriodoNonValidoException::class);
    });

    test('lancia eccezione per trimestre invalido', function () {
        expect(fn () => $this->service->rangeDate(2026, 5, 'trimestrale'))
            ->toThrow(IvaPeriodoNonValidoException::class);
    });

    test('lancia eccezione per tipo periodo sconosciuto', function () {
        expect(fn () => $this->service->rangeDate(2026, 1, 'semestrale'))
            ->toThrow(IvaPeriodoNonValidoException::class);
    });

    test('lancia eccezione per anno fuori range', function () {
        expect(fn () => $this->service->rangeDate(1999, 1, 'mensile'))
            ->toThrow(IvaPeriodoNonValidoException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// calcolaLiquidazione
// ─────────────────────────────────────────────────────────────────────────────

describe('calcolaLiquidazione', function () {

    test('calcola correttamente iva_debito e iva_credito per un mese', function () {
        // Fattura attiva: imponibile 1000 + IVA 22% = 220 (debito)
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');

        // Fattura passiva: imponibile 500 + IVA 22% = 110 (credito)
        creaFatturaPassiva($this->tenant, $this->iva22, 500, '2026-04-05');

        $saldi = $this->service->calcolaLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($saldi['iva_debito'])->toBe(220.0);
        expect($saldi['iva_credito'])->toBe(110.0);
        expect($saldi['saldo_periodo'])->toBe(110.0);   // debito netto
        expect($saldi['saldo_finale'])->toBe(110.0);    // nessun credito precedente
        expect($saldi['credito_periodo_precedente'])->toBe(0.0);
    });

    test('esclude fatture fuori dal periodo', function () {
        // Nel periodo (aprile 2026)
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-15');

        // Fuori periodo (maggio 2026)
        creaFatturaAttiva($this->tenant, $this->iva22, 2000, '2026-05-01');

        $saldi = $this->service->calcolaLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($saldi['iva_debito'])->toBe(220.0);  // solo la fattura di aprile
    });

    test('esclude fatture attive in bozza e annullate', function () {
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10', FatturaAttiva::STATO_BOZZA);
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10', FatturaAttiva::STATO_ANNULLATA);

        $saldi = $this->service->calcolaLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($saldi['iva_debito'])->toBe(0.0);
    });

    test('esclude iva indetraibile dal credito', function () {
        // Fattura con IVA 22%, 60% indetraibile (tipico auto aziendale)
        creaFatturaPassiva($this->tenant, $this->iva22, 1000, '2026-04-10', 60);

        $saldi = $this->service->calcolaLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        // IVA totale = 220, ma 60% indetraibile = 132 → credito detraibile = 88
        expect($saldi['iva_credito'])->toBe(88.0);
    });

    test('saldo negativo indica credito d\'imposta', function () {
        // Solo acquisti: credito supera il debito
        creaFatturaPassiva($this->tenant, $this->iva22, 5000, '2026-04-10');  // credito 1100
        creaFatturaAttiva($this->tenant, $this->iva22, 100, '2026-04-10');    // debito 22

        $saldi = $this->service->calcolaLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($saldi['saldo_finale'])->toBeLessThan(0);
    });

    test('riporta il credito del periodo precedente', function () {
        // Crea liquidazione del mese precedente con credito (saldo_finale negativo)
        LiquidazioneIva::create([
            'anno'                       => 2026,
            'periodo'                    => 3,
            'tipo_periodo'               => 'mensile',
            'data_inizio'                => '2026-03-01',
            'data_fine'                  => '2026-03-31',
            'iva_debito'                 => 0,
            'iva_credito'                => 200,
            'saldo_periodo'              => -200,
            'credito_periodo_precedente' => 0,
            'saldo_finale'               => -200,   // credito di 200€
            'status'                     => LiquidazioneIva::STATUS_DEFINITIVA,
        ]);

        // Aprile: debito 220 - credito precedente 200 = saldo 20
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');

        $saldi = $this->service->calcolaLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($saldi['credito_periodo_precedente'])->toBe(200.0);
        expect($saldi['iva_debito'])->toBe(220.0);
        expect($saldi['saldo_finale'])->toBe(20.0);
    });

    test('non riporta credito se il periodo precedente era a debito', function () {
        LiquidazioneIva::create([
            'anno'          => 2026,
            'periodo'       => 3,
            'tipo_periodo'  => 'mensile',
            'data_inizio'   => '2026-03-01',
            'data_fine'     => '2026-03-31',
            'saldo_finale'  => 500,  // era a debito, non a credito
            'status'        => LiquidazioneIva::STATUS_DEFINITIVA,
        ]);

        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');

        $saldi = $this->service->calcolaLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($saldi['credito_periodo_precedente'])->toBe(0.0);
    });

    test('calcola correttamente su periodo trimestrale', function () {
        // Fattura di gennaio (Q1)
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-01-15');
        // Fattura di marzo (Q1)
        creaFatturaAttiva($this->tenant, $this->iva10, 2000, '2026-03-20');
        // Fattura di aprile (Q2, esclusa)
        creaFatturaAttiva($this->tenant, $this->iva22, 5000, '2026-04-01');

        $saldi = $this->service->calcolaLiquidazione(
            $this->tenant->id, 2026, 1, 'trimestrale'
        );

        // IVA 22% su 1000 = 220, IVA 10% su 2000 = 200 → totale 420
        expect($saldi['iva_debito'])->toBe(420.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// chiudiLiquidazione
// ─────────────────────────────────────────────────────────────────────────────

describe('chiudiLiquidazione', function () {

    test('crea una liquidazione definitiva con i saldi corretti', function () {
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');
        creaFatturaPassiva($this->tenant, $this->iva22, 200, '2026-04-05');

        $liq = $this->service->chiudiLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($liq->status)->toBe(LiquidazioneIva::STATUS_DEFINITIVA);
        expect((float) $liq->iva_debito)->toBe(220.0);
        expect((float) $liq->iva_credito)->toBe(44.0);
        expect((float) $liq->saldo_finale)->toBe(176.0);
        expect($liq->data_chiusura)->not->toBeNull();
    });

    test('aggancia le fatture del periodo alla liquidazione', function () {
        $fp = creaFatturaPassiva($this->tenant, $this->iva22, 500, '2026-04-10');
        $fa = creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-15');

        $liq = $this->service->chiudiLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($fp->fresh()->liquidazione_iva_id)->toBe($liq->id);
        expect($fa->fresh()->liquidazione_iva_id)->toBe($liq->id);
    });

    test('non aggancia fatture di altri periodi', function () {
        $fpFuoriPeriodo = creaFatturaPassiva($this->tenant, $this->iva22, 500, '2026-05-01');
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-15');

        $liq = $this->service->chiudiLiquidazione(
            $this->tenant->id, 2026, 4, 'mensile'
        );

        expect($fpFuoriPeriodo->fresh()->liquidazione_iva_id)->toBeNull();
    });

    test('lancia IvaAlreadyClosedException se già definitiva', function () {
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');

        $this->service->chiudiLiquidazione($this->tenant->id, 2026, 4, 'mensile');

        expect(fn () => $this->service->chiudiLiquidazione($this->tenant->id, 2026, 4, 'mensile'))
            ->toThrow(IvaAlreadyClosedException::class);
    });

    test('aggiorna una bozza esistente invece di crearne una nuova', function () {
        // Crea bozza manualmente
        $bozza = LiquidazioneIva::create([
            'anno'         => 2026,
            'periodo'      => 4,
            'tipo_periodo' => 'mensile',
            'data_inizio'  => '2026-04-01',
            'data_fine'    => '2026-04-30',
            'status'       => LiquidazioneIva::STATUS_BOZZA,
        ]);

        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');

        $liq = $this->service->chiudiLiquidazione($this->tenant->id, 2026, 4, 'mensile');

        // Deve essere lo stesso record aggiornato, non un duplicato
        expect($liq->id)->toBe($bozza->id);
        expect($liq->status)->toBe(LiquidazioneIva::STATUS_DEFINITIVA);
        expect(LiquidazioneIva::withoutGlobalScope('tenant')->count())->toBe(1);
    });

    test('registra in prima nota quando esiste un conto', function () {
        // Crea un conto attivo per la prima nota
        Conto::create([
            'name'   => 'Conto Corrente Test',
            'type'   => 'corrente',
            'attivo' => true,
            'ordine' => 1,
        ]);

        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');

        $liq = $this->service->chiudiLiquidazione($this->tenant->id, 2026, 4, 'mensile');

        $entry = PrimaNotaEntry::withoutGlobalScope('tenant')
            ->where('entryable_type', LiquidazioneIva::class)
            ->where('entryable_id', $liq->id)
            ->where('rendiconto_code', 'iva_liquidazione')
            ->first();

        expect($entry)->not->toBeNull();
        expect((float) $entry->amount)->toBe(220.0);
    });

    test('non registra prima nota se saldo è zero', function () {
        Conto::create(['name' => 'Conto Test', 'type' => 'corrente', 'attivo' => true, 'ordine' => 1]);

        // Stessa IVA su attive e passive → saldo zero
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');
        creaFatturaPassiva($this->tenant, $this->iva22, 1000, '2026-04-05');

        $liq = $this->service->chiudiLiquidazione($this->tenant->id, 2026, 4, 'mensile');

        $count = PrimaNotaEntry::withoutGlobalScope('tenant')
            ->where('entryable_type', LiquidazioneIva::class)
            ->where('entryable_id', $liq->id)
            ->count();

        expect($count)->toBe(0);
    });

    test('non registra prima nota se non esiste nessun conto', function () {
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');

        $liq = $this->service->chiudiLiquidazione($this->tenant->id, 2026, 4, 'mensile');

        $count = PrimaNotaEntry::withoutGlobalScope('tenant')
            ->where('entryable_type', LiquidazioneIva::class)
            ->where('entryable_id', $liq->id)
            ->count();

        expect($count)->toBe(0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// getRegistroAcquisti
// ─────────────────────────────────────────────────────────────────────────────

describe('getRegistroAcquisti', function () {

    test('restituisce le fatture passive del mese ordinate per data', function () {
        creaFatturaPassiva($this->tenant, $this->iva22, 200, '2026-04-20');
        creaFatturaPassiva($this->tenant, $this->iva10, 300, '2026-04-05');
        // Fuori mese
        creaFatturaPassiva($this->tenant, $this->iva22, 999, '2026-05-01');

        $registro = $this->service->getRegistroAcquisti($this->tenant->id, 2026, 4);

        expect($registro)->toHaveCount(2);
        expect($registro->first()->data_registrazione->format('Y-m-d'))->toBe('2026-04-05');
    });

    test('carica le righe con il codice IVA', function () {
        creaFatturaPassiva($this->tenant, $this->iva22, 500, '2026-04-10');

        $registro = $this->service->getRegistroAcquisti($this->tenant->id, 2026, 4);

        expect($registro->first()->righe)->not->toBeEmpty();
        expect($registro->first()->righe->first()->codiceIva->codice)->toBe('22');
    });

    test('non include fatture annullate', function () {
        $fp = creaFatturaPassiva($this->tenant, $this->iva22, 500, '2026-04-10');
        $fp->update(['stato_pagamento' => FatturaPassiva::STATO_ANNULLATA]);

        $registro = $this->service->getRegistroAcquisti($this->tenant->id, 2026, 4);

        expect($registro)->toHaveCount(0);
    });

    test('lancia eccezione per mese invalido', function () {
        expect(fn () => $this->service->getRegistroAcquisti($this->tenant->id, 2026, 0))
            ->toThrow(IvaPeriodoNonValidoException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// getRegistroVendite
// ─────────────────────────────────────────────────────────────────────────────

describe('getRegistroVendite', function () {

    test('restituisce le fatture attive del mese ordinate per data e progressivo', function () {
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-20');
        creaFatturaAttiva($this->tenant, $this->iva10, 2000, '2026-04-10');
        // Fuori mese
        creaFatturaAttiva($this->tenant, $this->iva22, 999, '2026-05-01');

        $registro = $this->service->getRegistroVendite($this->tenant->id, 2026, 4);

        expect($registro)->toHaveCount(2);
        expect($registro->first()->data_fattura->format('Y-m-d'))->toBe('2026-04-10');
    });

    test('esclude bozze e annullate', function () {
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10', FatturaAttiva::STATO_BOZZA);
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10', FatturaAttiva::STATO_ANNULLATA);
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-15', FatturaAttiva::STATO_EMESSA);

        $registro = $this->service->getRegistroVendite($this->tenant->id, 2026, 4);

        expect($registro)->toHaveCount(1);
    });

    test('carica le righe con il codice IVA', function () {
        creaFatturaAttiva($this->tenant, $this->iva22, 1000, '2026-04-10');

        $registro = $this->service->getRegistroVendite($this->tenant->id, 2026, 4);

        expect($registro->first()->righe->first()->codiceIva->codice)->toBe('22');
    });

    test('non mostra fatture di un altro tenant', function () {
        // Secondo tenant
        $altroTenant = Tenant::create([
            'name'              => 'Altro Tenant',
            'slug'              => 'altro-tenant',
            'organization_type' => 'cooperative',
            'plan'              => 'free',
            'is_active'         => true,
        ]);
        app()->instance('current_tenant', $altroTenant);

        if (CodiceIva::withoutGlobalScope('tenant')->where('tenant_id', $altroTenant->id)->doesntExist()) {
            (new CodiciIvaDiSistemaSeeder)->perTenant($altroTenant);
        }
        $iva22Altro = CodiceIva::withoutGlobalScope('tenant')
            ->where('tenant_id', $altroTenant->id)
            ->where('codice', '22')
            ->first();

        creaFatturaAttiva($altroTenant, $iva22Altro, 9999, '2026-04-10');

        app()->instance('current_tenant', $this->tenant);

        $registro = $this->service->getRegistroVendite($this->tenant->id, 2026, 4);

        // Il tenant principale non ha fatture → registro vuoto
        expect($registro)->toHaveCount(0);
    });
});
