<?php

use App\Models\CodiceIva;
use App\Models\FatturaPassiva;
use App\Models\RigaFatturaPassiva;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Test',
        'slug'              => 'coop-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);
    app()->instance('current_tenant', $this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

test('il calcolo dei totali di una riga è coerente con l\'aliquota del codice IVA', function () {
    $iva22 = CodiceIva::where('codice', '22')->first();

    $riga = new RigaFatturaPassiva([
        'codice_iva_id'   => $iva22->id,
        'descrizione'     => 'Cancelleria',
        'quantita'        => 10,
        'prezzo_unitario' => 5,
    ]);
    $riga->setRelation('codiceIva', $iva22);
    $riga->calcolaTotali();

    expect((float) $riga->imponibile)->toBe(50.0);
    expect((float) $riga->iva)->toBe(11.0);
    expect((float) $riga->totale)->toBe(61.0);
});

test('ricalcolaTotali su fattura passiva somma le righe', function () {
    $iva22 = CodiceIva::where('codice', '22')->first();
    $iva10 = CodiceIva::where('codice', '10')->first();

    $fattura = FatturaPassiva::create([
        'numero_fattura'     => 'FP-2026-001',
        'data_fattura'       => '2026-04-01',
        'data_registrazione' => '2026-04-01',
    ]);

    $fattura->righe()->create([
        'tenant_id'       => $this->tenant->id,
        'codice_iva_id'   => $iva22->id,
        'descrizione'     => 'Riga 1',
        'quantita'        => 1,
        'prezzo_unitario' => 100,
        'imponibile'      => 100,
        'iva'             => 22,
        'totale'          => 122,
    ]);
    $fattura->righe()->create([
        'tenant_id'       => $this->tenant->id,
        'codice_iva_id'   => $iva10->id,
        'descrizione'     => 'Riga 2',
        'quantita'        => 1,
        'prezzo_unitario' => 50,
        'imponibile'      => 50,
        'iva'             => 5,
        'totale'          => 55,
    ]);

    $fattura->ricalcolaTotali();

    expect((float) $fattura->imponibile_totale)->toBe(150.0);
    expect((float) $fattura->iva_totale)->toBe(27.0);
    expect((float) $fattura->totale_documento)->toBe(177.0);
});
