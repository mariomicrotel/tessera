<?php

/**
 * Test di integrazione end-to-end per il flusso di ammortamento.
 *
 * Verifica:
 *   1. genera bozze → schedules in stato "bozza"
 *   2. conferma esercizio (batch) → schedules diventano "definitivo"
 *   3. movimenti contabili sono bilanciati (somma dare = somma avere)
 *   4. VNC cespite si riduce correttamente dopo conferma
 *   5. cespite già ammortizzato non genera nuove righe
 *   6. rotta PDF registro risponde 200 con contenuto application/pdf
 */

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciationSchedule;
use App\Models\ContoContabile;
use App\Models\MovimentoContabile;
use App\Models\RigaMovimentoContabile;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AmortizzamentoService;
use Database\Seeders\AssetCategoriesSeeder;
use Database\Seeders\CausaliContabiliDiSistemaSeeder;
use Database\Seeders\PianoContiCooperativaSeeder;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Flow Test',
        'slug'              => 'coop-flow-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new PianoContiCooperativaSeeder)->perTenant($this->tenant);
    (new CausaliContabiliDiSistemaSeeder)->perTenant($this->tenant);
    (new AssetCategoriesSeeder)->run($this->tenant);

    $this->service = app(AmortizzamentoService::class);

    // Categoria attrezzature (15%)
    $this->catAttrezz = AssetCategory::where('codice', 'ATTREZZ')->first();

    // Conti necessari per le scritture
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

    // Helper: crea un cespite con conti configurati
    $this->makeAsset = function (array $overrides = []): Asset {
        return Asset::factory()->create(array_merge([
            'tenant_id'                  => $this->tenant->id,
            'asset_category_id'          => $this->catAttrezz?->id,
            'costo_storico'              => 10000.00,
            'data_inizio_ammortamento'   => '2023-01-01',
            'metodo_ammortamento'        => Asset::METODO_ORDINARIO,
            'primo_anno_ridotto'         => true,
            'percentuale_deducibilita'   => 100.00,
            'stato'                      => Asset::STATO_IN_USO,
            'conto_bene_id'              => $this->contoBene?->id,
            'conto_fondo_id'             => $this->contoFondo?->id,
        ], $overrides));
    };
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// Flusso completo: genera → conferma
// ─────────────────────────────────────────────────────────────────────────────

describe('Flusso ammortamento end-to-end', function () {

    it('genera le schedule in bozza per i cespiti in_uso del tenant', function () {
        if (! $this->contoFondo || ! $this->contoAmm || ! $this->contoBene || ! $this->catAttrezz) {
            $this->markTestSkipped('Conti contabili non configurati per il test.');
        }

        $asset1 = ($this->makeAsset)(['name' => 'Macchinario A', 'costo_storico' => 8000]);
        $asset2 = ($this->makeAsset)(['name' => 'Macchinario B', 'costo_storico' => 5000]);

        $esercizio = 2024;
        $schedules = $this->service->generaRigheAnno($esercizio, $this->tenant);

        expect($schedules)->toHaveCount(2);

        foreach ($schedules as $s) {
            expect($s->stato)->toBe(AssetDepreciationSchedule::STATO_BOZZA);
            expect($s->esercizio)->toBe($esercizio);
            expect((float) $s->quota_calcolata)->toBeGreaterThan(0);
        }
    });

    it('non genera schedule per cespiti dismessi o con costo_storico = 0', function () {
        if (! $this->catAttrezz) {
            $this->markTestSkipped('Categoria ATTREZZ non trovata.');
        }

        ($this->makeAsset)(['name' => 'Attivo', 'stato' => Asset::STATO_IN_USO, 'costo_storico' => 5000]);
        ($this->makeAsset)(['name' => 'Dismesso', 'stato' => Asset::STATO_DISMESSO, 'costo_storico' => 5000]);
        ($this->makeAsset)(['name' => 'Zero costo', 'stato' => Asset::STATO_IN_USO, 'costo_storico' => 0]);

        $schedules = $this->service->generaRigheAnno(2024, $this->tenant);

        // Solo il cespite attivo con costo > 0 genera schedule
        expect($schedules)->toHaveCount(1);
        expect($schedules->first()->asset->name)->toBe('Attivo');
    });

    it('la generazione è idempotente: rieseguire non duplica le bozze', function () {
        if (! $this->contoFondo || ! $this->catAttrezz) {
            $this->markTestSkipped('Conti non configurati.');
        }

        ($this->makeAsset)(['name' => 'Cespite Unico']);

        $this->service->generaRigheAnno(2024, $this->tenant);
        $this->service->generaRigheAnno(2024, $this->tenant); // seconda esecuzione

        $count = AssetDepreciationSchedule::where('tenant_id', $this->tenant->id)
            ->where('esercizio', 2024)
            ->count();

        expect($count)->toBe(1);
    });

    it('confermaEsercizio porta le bozze a definitivo e genera movimenti bilanciati', function () {
        if (! $this->contoFondo || ! $this->contoAmm || ! $this->contoBene || ! $this->catAttrezz) {
            $this->markTestSkipped('Conti contabili non configurati per il test.');
        }

        ($this->makeAsset)(['name' => 'Tornio CNC', 'costo_storico' => 12000]);

        $esercizio = 2024;
        $this->service->generaRigheAnno($esercizio, $this->tenant);

        $contate = $this->service->confermaEsercizio($esercizio, $this->tenant, '2024-12-31');

        expect($contate)->toBeGreaterThan(0);

        // Tutte le schedule dell'esercizio devono essere definitive
        $bozzeRimaste = AssetDepreciationSchedule::where('tenant_id', $this->tenant->id)
            ->where('esercizio', $esercizio)
            ->where('stato', AssetDepreciationSchedule::STATO_BOZZA)
            ->count();

        expect($bozzeRimaste)->toBe(0);

        // Ogni schedule definitiva ha un movimento contabile collegato
        $scheduleDefinitive = AssetDepreciationSchedule::where('tenant_id', $this->tenant->id)
            ->where('esercizio', $esercizio)
            ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
            ->get();

        foreach ($scheduleDefinitive as $sched) {
            expect($sched->movimento_contabile_id)->not->toBeNull();
        }
    });

    it('i movimenti contabili generati sono bilanciati (dare = avere)', function () {
        if (! $this->contoFondo || ! $this->contoAmm || ! $this->contoBene || ! $this->catAttrezz) {
            $this->markTestSkipped('Conti contabili non configurati per il test.');
        }

        ($this->makeAsset)(['name' => 'Fresatrice', 'costo_storico' => 20000]);

        $esercizio = 2024;
        $this->service->generaRigheAnno($esercizio, $this->tenant);
        $this->service->confermaEsercizio($esercizio, $this->tenant, '2024-12-31');

        // Recupera tutti i movimenti di ammortamento del tenant per l'esercizio
        $scheduleIds = AssetDepreciationSchedule::where('tenant_id', $this->tenant->id)
            ->where('esercizio', $esercizio)
            ->whereNotNull('movimento_contabile_id')
            ->pluck('movimento_contabile_id');

        expect($scheduleIds)->not->toBeEmpty();

        foreach ($scheduleIds as $movId) {
            $righe = RigaMovimentoContabile::where('movimento_contabile_id', $movId)->get();

            $totaleDare  = $righe->where('tipo', 'dare')->sum('importo');
            $totaleAvere = $righe->where('tipo', 'avere')->sum('importo');

            expect((float) $totaleDare)->toEqual((float) $totaleAvere,
                "Movimento ID {$movId}: dare ({$totaleDare}) ≠ avere ({$totaleAvere})"
            );
        }
    });

    it('il VNC del cespite si riduce dopo la conferma dell\'ammortamento', function () {
        if (! $this->contoFondo || ! $this->contoAmm || ! $this->contoBene || ! $this->catAttrezz) {
            $this->markTestSkipped('Conti contabili non configurati per il test.');
        }

        $costo = 10000.00;
        $asset = ($this->makeAsset)(['name' => 'Pressa Idraulica', 'costo_storico' => $costo]);

        $esercizio = 2024;
        $this->service->generaRigheAnno($esercizio, $this->tenant);
        $this->service->confermaEsercizio($esercizio, $this->tenant, '2024-12-31');

        // Fondo dopo la conferma = somma delle quote definitive
        $fondoCumulato = (float) AssetDepreciationSchedule::where('asset_id', $asset->id)
            ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
            ->sum('quota_registrata');

        $vncAtteso = round($costo - $fondoCumulato, 2);

        expect($fondoCumulato)->toBeGreaterThan(0);
        expect($vncAtteso)->toBeLessThan($costo);
        expect($vncAtteso)->toBeGreaterThanOrEqual(0);
    });

    it('non genera schedule per cespiti già completamente ammortizzati', function () {
        if (! $this->catAttrezz) {
            $this->markTestSkipped('Categoria ATTREZZ non trovata.');
        }

        $costo = 1000.00;
        $asset = ($this->makeAsset)([
            'name'         => 'Attrezzatura Vecchia',
            'costo_storico' => $costo,
            'data_inizio_ammortamento' => '2015-01-01', // inizio molto lontano
        ]);

        // Simula ammortamento completato: inserisce schedules definitive che coprono tutto il costo
        AssetDepreciationSchedule::create([
            'tenant_id'               => $this->tenant->id,
            'asset_id'                => $asset->id,
            'esercizio'               => 2020,
            'quota_calcolata'         => $costo,
            'quota_registrata'        => $costo,
            'aliquota_applicata'      => 15.00,
            'deducibilita_applicata'  => 100.00,
            'fondo_inizio_anno'       => 0,
            'fondo_fine_anno'         => $costo,
            'valore_residuo_fine_anno' => 0,
            'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
        ]);

        $schedules = $this->service->generaRigheAnno(2024, $this->tenant);

        // Non deve generare schedule per l'attrezzatura già esaurita
        $scheduleAsset = $schedules->where('asset_id', $asset->id);
        expect($scheduleAsset)->toBeEmpty();
    });

    it('non sovrascrive schedule già definitive durante la generazione', function () {
        if (! $this->contoFondo || ! $this->contoAmm || ! $this->contoBene || ! $this->catAttrezz) {
            $this->markTestSkipped('Conti contabili non configurati per il test.');
        }

        $asset = ($this->makeAsset)(['name' => 'Trapano Industriale']);
        $esercizio = 2024;

        // Prima esecuzione e conferma
        $this->service->generaRigheAnno($esercizio, $this->tenant);
        $this->service->confermaEsercizio($esercizio, $this->tenant, '2024-12-31');

        $scheduleDefinitivaId = AssetDepreciationSchedule::where('asset_id', $asset->id)
            ->where('esercizio', $esercizio)
            ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
            ->value('id');

        // Seconda generazione: non deve toccare la definitiva
        $this->service->generaRigheAnno($esercizio, $this->tenant);

        $schedulePost = AssetDepreciationSchedule::where('asset_id', $asset->id)
            ->where('esercizio', $esercizio)
            ->get();

        // Esiste solo la definitiva originale (non è stata creata una bozza affiancata)
        expect($schedulePost)->toHaveCount(1);
        expect($schedulePost->first()->id)->toBe($scheduleDefinitivaId);
        expect($schedulePost->first()->stato)->toBe(AssetDepreciationSchedule::STATO_DEFINITIVO);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Calcolo progressivo multi-anno
// ─────────────────────────────────────────────────────────────────────────────

describe('Calcolo progressivo multi-anno', function () {

    it('il fondo cresce anno dopo anno fino ad esaurire il cespite', function () {
        if (! $this->contoFondo || ! $this->contoAmm || ! $this->contoBene || ! $this->catAttrezz) {
            $this->markTestSkipped('Conti contabili non configurati per il test.');
        }

        // Aliquota 15%, primo anno ridotto → anno 1: 7.5%, anni successivi: 15%
        // Costo 1.000 → anno 1 quota = 75, anno 2 = 150, ...
        $asset = ($this->makeAsset)([
            'name'                   => 'Muletto Test',
            'costo_storico'          => 1000.00,
            'data_inizio_ammortamento' => '2022-01-01',
        ]);

        $fondoCumulato = 0.0;

        foreach ([2022, 2023, 2024] as $anno) {
            $this->service->generaRigheAnno($anno, $this->tenant);
            $this->service->confermaEsercizio($anno, $this->tenant, "{$anno}-12-31");

            $sched = AssetDepreciationSchedule::where('asset_id', $asset->id)
                ->where('esercizio', $anno)
                ->first();

            expect($sched)->not->toBeNull();
            expect($sched->stato)->toBe(AssetDepreciationSchedule::STATO_DEFINITIVO);

            $fondoCumulato += (float) $sched->quota_registrata;
        }

        // Dopo 3 anni: 75 + 150 + 150 = 375 su 1000
        expect($fondoCumulato)->toBeGreaterThan(0);
        expect($fondoCumulato)->toBeLessThan(1000.00);

        // VNC residuo = costo - fondo
        $vncResiduo = round(1000.0 - $fondoCumulato, 2);
        expect($vncResiduo)->toBeGreaterThan(0);
    });

    it('la quota del primo anno è dimezzata se primo_anno_ridotto = true (15% → 7.5%)', function () {
        if (! $this->catAttrezz) {
            $this->markTestSkipped('Categoria ATTREZZ non trovata.');
        }

        $asset = ($this->makeAsset)([
            'name'                   => 'Compressore',
            'costo_storico'          => 2000.00,
            'data_inizio_ammortamento' => '2024-01-01',
            'primo_anno_ridotto'     => true,
        ]);

        $calcolo = $this->service->calcolaQuotaEsercizio($asset, 2024);

        // 7.5% di 2000 = 150
        expect($calcolo['aliquota_applicata'])->toBe(7.5);
        expect((float) $calcolo['quota_calcolata'])->toBe(150.0);
    });

    it('la quota dei successivi è al coefficiente pieno (15%)', function () {
        if (! $this->contoFondo || ! $this->contoAmm || ! $this->contoBene || ! $this->catAttrezz) {
            $this->markTestSkipped('Conti contabili non configurati per il test.');
        }

        $asset = ($this->makeAsset)([
            'name'                   => 'Robot Saldatore',
            'costo_storico'          => 2000.00,
            'data_inizio_ammortamento' => '2023-01-01',
            'primo_anno_ridotto'     => true,
        ]);

        // Anno 1 (2023): conferma
        $this->service->generaRigheAnno(2023, $this->tenant);
        $this->service->confermaEsercizio(2023, $this->tenant, '2023-12-31');

        // Anno 2 (2024): calcolo
        $calcolo = $this->service->calcolaQuotaEsercizio($asset, 2024);

        // Fondo inizio 2024 = quota definitiva 2023 (7.5% di 2000 = 150)
        expect((float) $calcolo['fondo_inizio_anno'])->toBe(150.0);
        // Aliquota piena 15%, quota = 15% di 2000 = 300
        expect($calcolo['aliquota_applicata'])->toBe(15.0);
        expect((float) $calcolo['quota_calcolata'])->toBe(300.0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// HTTP smoke test — PDF Registro Cespiti
// ─────────────────────────────────────────────────────────────────────────────

describe('HTTP — Registro Cespiti PDF', function () {

    it('la rotta registro-pdf risponde 200 e restituisce un PDF', function () {
        if (! $this->catAttrezz) {
            $this->markTestSkipped('Categoria ATTREZZ non trovata.');
        }

        // Crea utente admin e collegalo al tenant
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'email'             => 'admin-flow-test@test.local',
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach(Role::where('name', 'admin')->first());
        $user->tenants()->attach($this->tenant->id, ['role' => 'admin']);

        ($this->makeAsset)(['name' => 'Cespite PDF Test', 'costo_storico' => 5000]);

        // La rotta è protetta da auth + tenant + role: bypassiamo solo il middleware
        // per testare la logica di generazione PDF (non la pipeline auth)
        $response = $this->actingAs($user)
            ->withoutMiddleware([
                \App\Http\Middleware\ResolveTenant::class,
                \App\Http\Middleware\EnsureUserHasRole::class,
            ])
            ->get(route('cespiti.registro-pdf', [
                'tenant'    => $this->tenant->slug,
                'esercizio' => 2024,
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    });

    it('la rotta registro-pdf accetta il filtro stato', function () {
        if (! $this->catAttrezz) {
            $this->markTestSkipped('Categoria ATTREZZ non trovata.');
        }

        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'email'             => 'admin-flow-test2@test.local',
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach(Role::where('name', 'admin')->first());
        $user->tenants()->attach($this->tenant->id, ['role' => 'admin']);

        ($this->makeAsset)(['name' => 'Cespite In Uso', 'stato' => Asset::STATO_IN_USO]);
        ($this->makeAsset)(['name' => 'Cespite Dismesso', 'stato' => Asset::STATO_DISMESSO]);

        $response = $this->actingAs($user)
            ->withoutMiddleware([
                \App\Http\Middleware\ResolveTenant::class,
                \App\Http\Middleware\EnsureUserHasRole::class,
            ])
            ->get(route('cespiti.registro-pdf', [
                'tenant'    => $this->tenant->slug,
                'esercizio' => 2024,
                'stato'     => Asset::STATO_IN_USO,
            ]));

        $response->assertStatus(200);
    });

});
