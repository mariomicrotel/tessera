<?php

/**
 * Unit-style tests per AmortizzamentoService.
 *
 * Verificano il calcolo delle quote (primo anno ridotto, metodi,
 * stop a VNC zero) e il flusso di generazione/registrazione in prima nota.
 */

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciationSchedule;
use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\MovimentoContabile;
use App\Models\Tenant;
use App\Services\AmortizzamentoService;
use App\Services\CespitiService;
use App\Services\MovimentoContabileService;
use Database\Seeders\AssetCategoriesSeeder;
use Database\Seeders\CausaliContabiliDiSistemaSeeder;
use Database\Seeders\PianoContiCooperativaSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Cespiti Test',
        'slug'              => 'coop-cespiti-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    // Seeder dipendenze
    (new PianoContiCooperativaSeeder)->perTenant($this->tenant);
    (new CausaliContabiliDiSistemaSeeder)->perTenant($this->tenant);
    (new AssetCategoriesSeeder)->run($this->tenant);

    $this->service = app(AmortizzamentoService::class);

    // Categoria attrezzature (15%, primo_anno_ridotto = true)
    $this->catAttrezz = AssetCategory::where('codice', 'ATTREZZ')->first();

    // Conti di ammortamento (per la scrittura contabile)
    $this->contoFondo = ContoContabile::withoutGlobalScope('tenant')
        ->where('tenant_id', $this->tenant->id)
        ->where('codice', 'like', '1.25.15%')
        ->where('movimentabile', true)
        ->first();

    $this->contoAmm = ContoContabile::withoutGlobalScope('tenant')
        ->where('tenant_id', $this->tenant->id)
        ->where('codice', 'like', '7.25.15%')
        ->where('movimentabile', true)
        ->first();

    $this->contoBene = ContoContabile::withoutGlobalScope('tenant')
        ->where('tenant_id', $this->tenant->id)
        ->where('codice', 'like', '1.20.15%')
        ->where('movimentabile', true)
        ->first();
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// Calcolo aliquota per anno
// ─────────────────────────────────────────────────────────────────────────────

describe('AmortizzamentoService → aliquotaAnno', function () {

    it('applica il 50% al primo anno (primo_anno_ridotto = true)', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // Anno 2024 = primo anno → 15% × 50% = 7.5%
        $aliquota = $this->service->aliquotaAnno($asset, 2024, 2024, 0.0, 10_000);
        expect($aliquota)->toBe(7.5);
    });

    it('usa il coefficiente pieno dal secondo anno', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // Anno 2025 = secondo anno → 15%
        $fondoDopoAnno1 = 10_000 * 0.075; // 750
        $aliquota = $this->service->aliquotaAnno($asset, 2025, 2024, $fondoDopoAnno1, 10_000);
        expect($aliquota)->toBe(15.0);
    });

    it('restituisce 0 se il cespite è già completamente ammortizzato', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // Fondo pari al costo storico → VNC = 0
        $aliquota = $this->service->aliquotaAnno($asset, 2030, 2024, 10_000.0, 10_000);
        expect($aliquota)->toBe(0.0);
    });

    it('metodo accelerato raddoppia il coefficiente', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ACCELERATO,
            'primo_anno_ridotto'       => false,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // 15% × 2 = 30%
        $aliquota = $this->service->aliquotaAnno($asset, 2024, 2024, 0.0, 10_000);
        expect($aliquota)->toBe(30.0);
    });

    it('metodo anticipato: coefficiente doppio per i primi 3 anni, poi ordinario', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ANTICIPATO,
            'primo_anno_ridotto'       => false,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // Anni 1-3 → 15% × 2 = 30%
        expect($this->service->aliquotaAnno($asset, 2024, 2024, 0.0, 10_000))->toBe(30.0);
        expect($this->service->aliquotaAnno($asset, 2025, 2024, 3000.0, 10_000))->toBe(30.0);
        expect($this->service->aliquotaAnno($asset, 2026, 2024, 6000.0, 10_000))->toBe(30.0);

        // Anno 4 → 15% ordinario
        expect($this->service->aliquotaAnno($asset, 2027, 2024, 9000.0, 10_000))->toBe(15.0);
    });

    it('metodo ridotto dimezza il coefficiente', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_RIDOTTO,
            'primo_anno_ridotto'       => false,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // 15% / 2 = 7.5%
        $aliquota = $this->service->aliquotaAnno($asset, 2024, 2024, 0.0, 10_000);
        expect($aliquota)->toBe(7.5);
    });

    it('aliquota_custom ha priorità sul coefficiente di categoria', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => false,
            'aliquota_custom'          => 20.0,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $aliquota = $this->service->aliquotaAnno($asset, 2024, 2024, 0.0, 10_000);
        expect($aliquota)->toBe(20.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Calcolo quota esercizio
// ─────────────────────────────────────────────────────────────────────────────

describe('AmortizzamentoService → calcolaQuotaEsercizio', function () {

    it('calcola quota, fondo e VNC per il primo anno', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $result = $this->service->calcolaQuotaEsercizio($asset, 2024);

        // Quota = 10000 × 7.5% = 750
        expect($result['quota_calcolata'])->toBe(750.0);
        expect($result['aliquota_applicata'])->toBe(7.5);
        expect($result['fondo_inizio_anno'])->toBe(0.0);
        expect($result['fondo_fine_anno'])->toBe(750.0);
        expect($result['valore_residuo_fine_anno'])->toBe(9_250.0);
    });

    it('la quota non può superare il VNC residuo (ultimo anno)', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 1_000,
            'data_inizio_ammortamento' => '2024-01-01',
            'metodo_ammortamento'      => Asset::METODO_ACCELERATO,
            'primo_anno_ridotto'       => false,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        // Crea schedules definitive per anni precedenti che portano il fondo a 700
        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $asset->id,
            'esercizio'               => 2024,
            'quota_calcolata'         => 300.00,
            'quota_registrata'        => 300.00,
            'aliquota_applicata'      => 30.0,
            'deducibilita_applicata'  => 100.0,
            'fondo_inizio_anno'       => 0.00,
            'fondo_fine_anno'         => 300.00,
            'valore_residuo_fine_anno'=> 700.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);
        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $asset->id,
            'esercizio'               => 2025,
            'quota_calcolata'         => 300.00,
            'quota_registrata'        => 300.00,
            'aliquota_applicata'      => 30.0,
            'deducibilita_applicata'   => 100.0,
            'fondo_inizio_anno'       => 300.00,
            'fondo_fine_anno'         => 600.00,
            'valore_residuo_fine_anno'=> 400.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);
        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $asset->id,
            'esercizio'               => 2026,
            'quota_calcolata'         => 300.00,
            'quota_registrata'        => 300.00,
            'aliquota_applicata'      => 30.0,
            'deducibilita_applicata'   => 100.0,
            'fondo_inizio_anno'       => 600.00,
            'fondo_fine_anno'         => 900.00,
            'valore_residuo_fine_anno'=> 100.00,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);

        // Anno 2027: VNC residuo = 100, quota teorica sarebbe 300 → capped a 100
        $result = $this->service->calcolaQuotaEsercizio($asset, 2027);

        expect($result['quota_calcolata'])->toBe(100.0);
        expect($result['valore_residuo_fine_anno'])->toBe(0.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Generazione righe per esercizio
// ─────────────────────────────────────────────────────────────────────────────

describe('AmortizzamentoService → generaRigheAnno', function () {

    it('genera righe bozza per tutti i cespiti in_uso', function () {
        // Crea 3 cespiti attivi
        Asset::factory()->count(3)->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 5_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $schedules = $this->service->generaRigheAnno(2025, $this->tenant);
        expect($schedules)->toHaveCount(3);
        expect($schedules->every(fn ($s) => $s->stato === AssetDepreciationSchedule::STATO_BOZZA))->toBeTrue();
    });

    it('non genera righe per cespiti dismessi o già ammortizzati', function () {
        Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 5_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'stato'                    => Asset::STATO_DISMESSO,
        ]);

        $schedules = $this->service->generaRigheAnno(2025, $this->tenant);
        expect($schedules)->toBeEmpty();
    });

    it('aggiorna la bozza esistente invece di creare un duplicato', function () {
        $asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 5_000,
            'data_inizio_ammortamento' => '2025-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $this->service->generaRigheAnno(2025, $this->tenant);
        $this->service->generaRigheAnno(2025, $this->tenant); // seconda chiamata

        $count = AssetDepreciationSchedule::where('asset_id', $asset->id)->where('esercizio', 2025)->count();
        expect($count)->toBe(1); // UNIQUE: (tenant_id, asset_id, esercizio)
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Registrazione in prima nota
// ─────────────────────────────────────────────────────────────────────────────

describe('AmortizzamentoService → registraQuotaInPrimaNota', function () {

    beforeEach(function () {
        // Cespite con conti espliciti per test scrittura
        $this->asset = Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 10_000,
            'data_inizio_ammortamento' => '2023-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
            'conto_bene_id'            => $this->contoBene?->id,
            'conto_fondo_id'           => $this->contoFondo?->id,
        ]);

        $this->schedules = $this->service->generaRigheAnno(2023, $this->tenant);
    });

    it('porta la schedule in stato definitivo', function () {
        if ($this->schedules->isEmpty() || ! $this->contoFondo || ! $this->contoAmm) {
            $this->markTestSkipped('Conti contabili non configurati nel piano conti del tenant di test.');
        }

        $schedule = $this->schedules->first();
        $this->service->registraQuotaInPrimaNota($schedule, $this->tenant, '2023-12-31');

        expect($schedule->fresh()->stato)->toBe(AssetDepreciationSchedule::STATO_DEFINITIVO);
    });

    it('crea un movimento contabile bilanciato', function () {
        if ($this->schedules->isEmpty() || ! $this->contoFondo || ! $this->contoAmm) {
            $this->markTestSkipped('Conti contabili non configurati nel piano conti del tenant di test.');
        }

        $schedule = $this->schedules->first();
        $movimento = $this->service->registraQuotaInPrimaNota($schedule, $this->tenant, '2023-12-31');

        expect($movimento->stato)->toBe(MovimentoContabile::STATO_DEFINITIVO);
        expect($movimento->isBilanciato())->toBeTrue();
        expect($movimento->righe)->toHaveCount(2);
    });

    it('il movimento ha dare=Ammortamento e avere=Fondo', function () {
        if ($this->schedules->isEmpty() || ! $this->contoFondo || ! $this->contoAmm) {
            $this->markTestSkipped('Conti contabili non configurati nel piano conti del tenant di test.');
        }

        $schedule  = $this->schedules->first();
        $quota     = (float) $schedule->quota_calcolata;
        $movimento = $this->service->registraQuotaInPrimaNota($schedule, $this->tenant, '2023-12-31');

        $movimento->load('righe');
        $totaleDare  = $movimento->righe->sum('importo_dare');
        $totaleAvere = $movimento->righe->sum('importo_avere');

        expect((float) $totaleDare)->toBe($quota);
        expect((float) $totaleAvere)->toBe($quota);
    });

    it('lancia eccezione se la schedule è già definitiva', function () {
        if ($this->schedules->isEmpty()) {
            $this->markTestSkipped('Nessuna schedule generata.');
        }

        $schedule = $this->schedules->first();
        $schedule->update(['stato' => AssetDepreciationSchedule::STATO_DEFINITIVO]);

        expect(fn () => $this->service->registraQuotaInPrimaNota($schedule->fresh(), $this->tenant))
            ->toThrow(\RuntimeException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Conferma esercizio batch
// ─────────────────────────────────────────────────────────────────────────────

describe('AmortizzamentoService → confermaEsercizio', function () {

    it('restituisce conteggio ok e array errori', function () {
        Asset::factory()->count(2)->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 5_000,
            'data_inizio_ammortamento' => '2022-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
            'conto_bene_id'            => $this->contoBene?->id,
            'conto_fondo_id'           => $this->contoFondo?->id,
        ]);

        $this->service->generaRigheAnno(2022, $this->tenant);

        $risultato = $this->service->confermaEsercizio(2022, $this->tenant, '2022-12-31');

        expect($risultato)->toHaveKeys(['ok', 'errori']);
        expect($risultato['ok'])->toBeGreaterThanOrEqual(0);
        expect($risultato['errori'])->toBeArray();
    });

    it('riepilogoEsercizio riporta bozze e definitive', function () {
        Asset::factory()->create([
            'tenant_id'                => $this->tenant->id,
            'asset_category_id'        => $this->catAttrezz->id,
            'costo_storico'            => 8_000,
            'data_inizio_ammortamento' => '2021-01-01',
            'metodo_ammortamento'      => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'       => true,
            'stato'                    => Asset::STATO_IN_USO,
        ]);

        $this->service->generaRigheAnno(2021, $this->tenant);

        $riepilogo = $this->service->riepilogoEsercizio(2021, $this->tenant);

        expect($riepilogo)->toHaveKeys(['bozze', 'definitive', 'totale_quota', 'cespiti_ammortizzati']);
        expect($riepilogo['bozze'])->toBe(1);
        expect($riepilogo['definitive'])->toBe(0);
        expect($riepilogo['totale_quota'])->toBeGreaterThan(0.0);
    });
});
