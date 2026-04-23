<?php

/**
 * Feature tests per CespitiService e DismissioneCespitiService.
 *
 * Coprono: CRUD cespiti, calcolo VNC/fondo, lock campi,
 * preview piano ammortamento, dismissione con plus/minusvalenza.
 */

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciationSchedule;
use App\Models\AssetDisposal;
use App\Models\ContoContabile;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\CespitiService;
use App\Services\DismissioneCespitiService;
use Database\Seeders\AssetCategoriesSeeder;
use Database\Seeders\CausaliContabiliDiSistemaSeeder;
use Database\Seeders\PianoContiCooperativaSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop CRUD Test',
        'slug'              => 'coop-crud-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new PianoContiCooperativaSeeder)->perTenant($this->tenant);
    (new CausaliContabiliDiSistemaSeeder)->perTenant($this->tenant);
    (new AssetCategoriesSeeder)->run($this->tenant);

    $this->cespitiService     = app(CespitiService::class);
    $this->dismissioneService = app(DismissioneCespitiService::class);

    $this->catMobili = AssetCategory::where('codice', 'MOBILI')->first();
    $this->catAttrezz = AssetCategory::where('codice', 'ATTREZZ')->first();
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// CespitiService — creazione
// ─────────────────────────────────────────────────────────────────────────────

describe('CespitiService → crea', function () {

    it('crea un cespite con stato in_uso', function () {
        $asset = $this->cespitiService->crea($this->tenant, [
            'name'                     => 'Scrivania direzionale',
            'code'                     => 'MOB-001',
            'asset_category_id'        => $this->catMobili->id,
            'costo_storico'            => 1_200.00,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'percentuale_deducibilita' => 100,
        ]);

        expect($asset->stato)->toBe(Asset::STATO_IN_USO);
        expect($asset->name)->toBe('Scrivania direzionale');
        expect((float) $asset->costo_storico)->toBe(1_200.0);
        expect($asset->tenant_id)->toBe($this->tenant->id);
    });

    it('applica i default della categoria (conti, aliquota, primo anno)', function () {
        $asset = $this->cespitiService->crea($this->tenant, [
            'name'                     => 'Tornio industriale',
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 5_000.00,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'percentuale_deducibilita' => 100,
        ]);

        // La categoria ATTREZZ ha primo_anno_ridotto_default = true
        expect($asset->primo_anno_ridotto)->toBeTrue();

        // Se la categoria ha conto_bene_default_id, viene ereditato
        if ($this->catAttrezz->conto_bene_default_id) {
            expect($asset->conto_bene_id)->toBe($this->catAttrezz->conto_bene_default_id);
        }
    });

    it('sincronizza value con costo_storico', function () {
        $asset = $this->cespitiService->crea($this->tenant, [
            'name'                     => 'PC aziendale',
            'costo_storico'            => 2_500.00,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'percentuale_deducibilita' => 100,
        ]);

        expect((float) $asset->value)->toBe(2_500.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// CespitiService — aggiornamento
// ─────────────────────────────────────────────────────────────────────────────

describe('CespitiService → aggiorna', function () {

    it('aggiorna i campi anagrafici', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'name'                     => 'Nome vecchio',
            'costo_storico'            => 1_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $aggiornato = $this->cespitiService->aggiorna($asset, [
            'name'                     => 'Nome nuovo',
            'costo_storico'            => 1_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'matricola'                => 'SN-12345',
        ]);

        expect($aggiornato->name)->toBe('Nome nuovo');
        expect($aggiornato->matricola)->toBe('SN-12345');
    });

    it('blocca la modifica del costo_storico se esistono ammortamenti definitivi', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // Crea una schedule definitiva
        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $asset->id,
            'esercizio'               => 2025,
            'quota_calcolata'         => 750.00,
            'quota_registrata'        => 750.00,
            'aliquota_applicata'      => 7.5,
            'deducibilita_applicata'  => 100.0,
            'fondo_inizio_anno'       => 0.00,
            'fondo_fine_anno'         => 750.00,
            'valore_residuo_fine_anno'=> 9_250.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);

        expect(fn () => $this->cespitiService->aggiorna($asset, [
            'name'                     => 'Invariato',
            'costo_storico'            => 12_000, // ← modifica vietata
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
        ]))->toThrow(\RuntimeException::class, 'costo_storico');
    });

    it('permette la modifica di campi non fiscali anche con ammortamenti definitivi', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $asset->id,
            'esercizio'               => 2025,
            'quota_calcolata'         => 750.00,
            'quota_registrata'        => 750.00,
            'aliquota_applicata'      => 7.5,
            'deducibilita_applicata'  => 100.0,
            'fondo_inizio_anno'       => 0.00,
            'fondo_fine_anno'         => 750.00,
            'valore_residuo_fine_anno'=> 9_250.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);

        // Modifica solo matricola (non è campo locked)
        $aggiornato = $this->cespitiService->aggiorna($asset, [
            'name'                     => $asset->name,
            'costo_storico'            => 10_000, // invariato
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'matricola'                => 'NUOVO-SERIALE',
        ]);

        expect($aggiornato->matricola)->toBe('NUOVO-SERIALE');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// CespitiService — calcolo VNC e fondo
// ─────────────────────────────────────────────────────────────────────────────

describe('CespitiService → calcolo VNC e fondo', function () {

    beforeEach(function () {
        $this->asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2023-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'stato'                    => Asset::STATO_IN_USO,
        ]);
    });

    it('restituisce costo_storico come VNC se nessuna schedule definitiva', function () {
        $vnc = $this->cespitiService->calcolaValoreResiduo($this->asset);
        expect($vnc)->toBe(10_000.0);
    });

    it('sottrae le quote definitive dal costo storico', function () {
        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $this->asset->id,
            'esercizio'               => 2023,
            'quota_calcolata'         => 750.00,
            'quota_registrata'        => 750.00,
            'aliquota_applicata'      => 7.5,
            'deducibilita_applicata'  => 100.0,
            'fondo_inizio_anno'       => 0.00,
            'fondo_fine_anno'         => 750.00,
            'valore_residuo_fine_anno'=> 9_250.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);

        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $this->asset->id,
            'esercizio'               => 2024,
            'quota_calcolata'         => 1_500.00,
            'quota_registrata'        => 1_500.00,
            'aliquota_applicata'      => 15.0,
            'deducibilita_applicata'  => 100.0,
            'fondo_inizio_anno'       => 750.00,
            'fondo_fine_anno'         => 2_250.00,
            'valore_residuo_fine_anno'=> 7_750.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);

        $fondo = $this->cespitiService->calcolaFondoCumulato($this->asset);
        $vnc   = $this->cespitiService->calcolaValoreResiduo($this->asset);

        expect($fondo)->toBe(2_250.0);
        expect($vnc)->toBe(7_750.0);
    });

    it('ignora le schedule bozza nel calcolo', function () {
        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $this->asset->id,
            'esercizio'               => 2023,
            'quota_calcolata'         => 750.00,
            'quota_registrata'        => 750.00,
            'aliquota_applicata'      => 7.5,
            'deducibilita_applicata'  => 100.0,
            'fondo_inizio_anno'       => 0.00,
            'fondo_fine_anno'         => 750.00,
            'valore_residuo_fine_anno'=> 9_250.00,
            'stato'                   => AssetDepreciationSchedule::STATO_BOZZA, // ← bozza
        ]);

        $vnc = $this->cespitiService->calcolaValoreResiduo($this->asset);
        expect($vnc)->toBe(10_000.0); // invariato — bozza ignorata
    });

    it('VNC non scende sotto zero', function () {
        // Fondo superiore al costo (situazione anomala)
        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $this->asset->id,
            'esercizio'               => 2023,
            'quota_calcolata'         => 10_500.00,
            'quota_registrata'        => 10_500.00,
            'aliquota_applicata'      => 100.0,
            'deducibilita_applicata'  => 100.0,
            'fondo_inizio_anno'       => 0.00,
            'fondo_fine_anno'         => 10_500.00,
            'valore_residuo_fine_anno'=> 0.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);

        $vnc = $this->cespitiService->calcolaValoreResiduo($this->asset);
        expect($vnc)->toBe(0.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// CespitiService — preview piano
// ─────────────────────────────────────────────────────────────────────────────

describe('CespitiService → previewPianoAmmortamento', function () {

    it('calcola il piano completo fino a VNC = 0', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 1_500,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $piano = $this->cespitiService->previewPianoAmmortamento($asset);

        expect($piano)->not->toBeEmpty();

        // Ultimo anno deve avere residuo 0
        $ultimaRiga = last($piano);
        expect($ultimaRiga['residuo'])->toBe(0.0);

        // Il fondo cumulato finale deve eguagliare il costo storico
        expect($ultimaRiga['fondo'])->toBe(1_500.0);
    });

    it('primo anno ha aliquota dimezzata (7.5% per ATTREZZ)', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $piano = $this->cespitiService->previewPianoAmmortamento($asset);
        $primoAnno = $piano[0];

        expect($primoAnno['aliquota'])->toBe(7.5);
        expect($primoAnno['quota'])->toBe(750.0);
    });

    it('restituisce array vuoto se costo storico è zero', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'costo_storico'            => 0,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $piano = $this->cespitiService->previewPianoAmmortamento($asset);
        expect($piano)->toBeEmpty();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// DismissioneCespitiService
// ─────────────────────────────────────────────────────────────────────────────

describe('DismissioneCespitiService', function () {

    beforeEach(function () {
        $this->asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catMobili->id,
            'costo_storico'            => 5_000,
            'data_inizio_ammortamento' => '2023-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // Simula ammortamento anno 2023 (bozza → non considerata nel VNC)
        // Lascia VNC = 5000 (nessuna schedule definitiva)
    });

    it('preview calcola VNC e plus/minus correttamente', function () {
        $result = $this->dismissioneService->preview($this->asset, 4_000.0, '2026-12-31');

        expect($result)->toHaveKeys(['vnc', 'fondo_cumulato', 'plusvalenza_minusvalenza']);
        // VNC = 5000 (nessuna schedule definitiva), realizzo = 4000 → minus = -1000
        expect($result['vnc'])->toBe(5_000.0);
        expect($result['plusvalenza_minusvalenza'])->toBe(-1_000.0);
    });

    it('dismetti registra l\'AssetDisposal', function () {
        $disposal = $this->dismissioneService->dismetti($this->asset, $this->tenant, [
            'tipo'             => AssetDisposal::TIPO_ROTTAMAZIONE,
            'data_dismissione' => '2026-06-30',
            'valore_realizzo'  => 0,
            'note'             => 'Rotto',
        ]);

        expect($disposal)->toBeInstanceOf(AssetDisposal::class);
        expect($disposal->tipo)->toBe(AssetDisposal::TIPO_ROTTAMAZIONE);
        expect((float) $disposal->valore_netto_contabile)->toBe(5_000.0);
        expect((float) $disposal->plusvalenza_minusvalenza)->toBe(-5_000.0);
    });

    it('aggiorna lo stato del cespite a dismesso (rottamazione)', function () {
        $this->dismissioneService->dismetti($this->asset, $this->tenant, [
            'tipo'             => AssetDisposal::TIPO_ROTTAMAZIONE,
            'data_dismissione' => '2026-06-30',
            'valore_realizzo'  => 0,
        ]);

        expect($this->asset->fresh()->stato)->toBe(Asset::STATO_DISMESSO);
    });

    it('aggiorna lo stato del cespite a venduto (vendita)', function () {
        $this->dismissioneService->dismetti($this->asset, $this->tenant, [
            'tipo'             => AssetDisposal::TIPO_VENDITA,
            'data_dismissione' => '2026-06-30',
            'valore_realizzo'  => 6_000,
        ]);

        expect($this->asset->fresh()->stato)->toBe(Asset::STATO_VENDUTO);
    });

    it('lancia eccezione se il cespite è già dismesso', function () {
        $this->dismissioneService->dismetti($this->asset, $this->tenant, [
            'tipo'             => AssetDisposal::TIPO_DONAZIONE,
            'data_dismissione' => '2026-06-30',
            'valore_realizzo'  => 0,
        ]);

        expect(fn () => $this->dismissioneService->dismetti($this->asset->fresh(), $this->tenant, [
            'tipo'             => AssetDisposal::TIPO_ROTTAMAZIONE,
            'data_dismissione' => '2026-07-01',
            'valore_realizzo'  => 0,
        ]))->toThrow(\RuntimeException::class);
    });

    it('lancia eccezione se esiste già un disposal', function () {
        // Crea manualmente un disposal
        AssetDisposal::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $this->asset->id,
            'tipo'                    => AssetDisposal::TIPO_FURTO,
            'data_dismissione'        => '2026-01-01',
            'valore_realizzo'         => 0,
            'valore_netto_contabile'  => 5_000,
            'plusvalenza_minusvalenza'=> -5_000,
        ]);

        expect(fn () => $this->dismissioneService->dismetti($this->asset, $this->tenant, [
            'tipo'             => AssetDisposal::TIPO_ROTTAMAZIONE,
            'data_dismissione' => '2026-06-30',
            'valore_realizzo'  => 0,
        ]))->toThrow(\RuntimeException::class);
    });

    it('plusvalenza positiva quando realizzo > VNC', function () {
        $disposal = $this->dismissioneService->dismetti($this->asset, $this->tenant, [
            'tipo'             => AssetDisposal::TIPO_VENDITA,
            'data_dismissione' => '2026-12-31',
            'valore_realizzo'  => 7_500, // superiore al VNC di 5000
        ]);

        expect((float) $disposal->plusvalenza_minusvalenza)->toBe(2_500.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Model helper: valoreNetto() e fondoCumulato()
// ─────────────────────────────────────────────────────────────────────────────

describe('Asset model helpers', function () {

    it('aliquotaEffettiva usa aliquota_custom se presente', function () {
        $asset = Asset::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'asset_category_id' => $this->catAttrezz->id,
            'aliquota_custom'   => 25.0,
            'costo_storico'     => 1_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento' => Asset::METODO_ORDINARIO,
            'stato'             => Asset::STATO_IN_USO,
        ]);

        expect($asset->aliquotaEffettiva())->toBe(25.0);
    });

    it('aliquotaEffettiva usa coefficiente della categoria se custom è null', function () {
        $asset = Asset::factory()->create([
            'tenant_id'            => $this->tenant->id,
            'asset_category_id'    => $this->catAttrezz->id,
            'aliquota_custom'      => null,
            'costo_storico'        => 1_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'  => Asset::METODO_ORDINARIO,
            'stato'                => Asset::STATO_IN_USO,
        ]);

        $asset->load('category');
        expect($asset->aliquotaEffettiva())->toBe((float) $this->catAttrezz->coefficiente_ministeriale);
    });

    it('isCompletamenteAmmortizzato è false con VNC > 0', function () {
        $asset = Asset::factory()->create([
            'tenant_id'            => $this->tenant->id,
            'costo_storico'        => 10_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'  => Asset::METODO_ORDINARIO,
            'stato'                => Asset::STATO_IN_USO,
        ]);

        expect($asset->isCompletamenteAmmortizzato())->toBeFalse();
    });

    it('isCompletamenteAmmortizzato è true quando fondo = costo storico', function () {
        $asset = Asset::factory()->create([
            'tenant_id'            => $this->tenant->id,
            'costo_storico'        => 1_000,
            'data_inizio_ammortamento' => '2023-01-01',
            'metodo_ammortamento'  => Asset::METODO_ORDINARIO,
            'stato'                => Asset::STATO_IN_USO,
        ]);

        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $asset->id,
            'esercizio'               => 2023,
            'quota_calcolata'         => 1_000.00,
            'quota_registrata'        => 1_000.00,
            'aliquota_applicata'      => 100.0,
            'deducibilita_applicata'  => 100.0,
            'fondo_inizio_anno'       => 0.00,
            'fondo_fine_anno'         => 1_000.00,
            'valore_residuo_fine_anno'=> 0.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);

        expect($asset->isCompletamenteAmmortizzato())->toBeTrue();
    });
});
