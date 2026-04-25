<?php

/**
 * Test suite per RateoRisconto e RateiRiscontiService.
 *
 * Copre:
 *  - Model: constants, helpers (isRateo, isRisconto, isAttivo, isDaRegistrare, …)
 *  - Model: calcolaQuota (proporzione giorni)
 *  - Service: crea (con quota auto e manuale), validazione date
 *  - Service: registra (genera scrittura contabile, cambia stato)
 *  - Service: storna (inverte righe, genera storno anno successivo)
 *  - Service: aggiorna, elimina
 *  - Service: registraBatch
 *  - Controller: index, store, registra, storna, destroy — middleware role
 */

use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\MovimentoContabile;
use App\Models\RateoRisconto;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RateiRiscontiService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup globale
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Ratei Test',
        'slug'              => 'ets-ratei-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin  = Role::where('name', 'admin')->first();
    $this->user = User::factory()->create([
        'email'             => 'admin@ratei.it',
        'email_verified_at' => now(),
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    $this->userSenza = User::factory()->create([
        'email'             => 'nobody@ratei.it',
        'email_verified_at' => now(),
    ]);
    $this->userSenza->tenants()->attach($this->tenant);

    // Conti contabili
    $this->contoCosto = ContoContabile::create([
        'codice'          => '6.10.01.001',
        'descrizione'     => 'Affitti passivi',
        'livello'         => 4,
        'natura'          => ContoContabile::NATURA_COSTO,
        'segno_naturale'  => ContoContabile::SEGNO_DARE,
        'movimentabile'   => true,
        'attivo'          => true,
        'di_sistema'      => false,
        'tenant_id'       => $this->tenant->id,
    ]);

    $this->contoRicavo = ContoContabile::create([
        'codice'          => '4.10.01.001',
        'descrizione'     => 'Affitti attivi',
        'livello'         => 4,
        'natura'          => ContoContabile::NATURA_RICAVO,
        'segno_naturale'  => ContoContabile::SEGNO_AVERE,
        'movimentabile'   => true,
        'attivo'          => true,
        'di_sistema'      => false,
        'tenant_id'       => $this->tenant->id,
    ]);

    $this->contoRateiAttivi = ContoContabile::create([
        'codice'          => '1.40.01.001',
        'descrizione'     => 'Ratei attivi',
        'livello'         => 4,
        'natura'          => ContoContabile::NATURA_ATTIVO,
        'segno_naturale'  => ContoContabile::SEGNO_DARE,
        'movimentabile'   => true,
        'attivo'          => true,
        'di_sistema'      => false,
        'tenant_id'       => $this->tenant->id,
    ]);

    $this->contoRateiPassivi = ContoContabile::create([
        'codice'          => '2.40.01.001',
        'descrizione'     => 'Ratei passivi',
        'livello'         => 4,
        'natura'          => ContoContabile::NATURA_PASSIVO,
        'segno_naturale'  => ContoContabile::SEGNO_AVERE,
        'movimentabile'   => true,
        'attivo'          => true,
        'di_sistema'      => false,
        'tenant_id'       => $this->tenant->id,
    ]);

    $this->service = app(RateiRiscontiService::class);

    // Causale RAT (usata dal service)
    CausaleContabile::firstOrCreate(
        ['codice' => 'RAT'],
        [
            'descrizione' => 'Rateo / Risconto',
            'tipo'        => 'generico',
            'di_sistema'  => true,
            'attivo'      => true,
        ]
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// Model helpers
// ─────────────────────────────────────────────────────────────────────────────

describe('RateoRisconto model', function () {

    it('identifica rateo e risconto correttamente', function () {
        $rateo = RateoRisconto::make(['tipo' => RateoRisconto::TIPO_RATEO_ATTIVO]);
        expect($rateo->isRateo())->toBeTrue();
        expect($rateo->isRisconto())->toBeFalse();
        expect($rateo->isAttivo())->toBeTrue();

        $risconto = RateoRisconto::make(['tipo' => RateoRisconto::TIPO_RISCONTO_PASSIVO]);
        expect($risconto->isRateo())->toBeFalse();
        expect($risconto->isRisconto())->toBeTrue();
        expect($risconto->isAttivo())->toBeFalse();
    });

    it('calcolaQuota proporziona correttamente', function () {
        // Affitto annuale 1200 → quota mensile 100
        // Periodo: 01/10/2025 → 30/09/2026 (12 mesi = 365 giorni)
        // Giorni di competenza 2025: 92 (ott-dic)
        $rateo = RateoRisconto::make([
            'tipo'            => RateoRisconto::TIPO_RISCONTO_ATTIVO,
            'importo_totale'  => 1200.00,
            'data_inizio'     => '2025-10-01',
            'data_fine'       => '2026-09-30',
            'anno_esercizio'  => 2025,
        ]);

        $quota = $rateo->calcolaQuota(2025);

        // giorni totali: 365, giorni 2025: 92 (ott=31, nov=30, dic=31)
        $atteso = round(1200 * 92 / 365, 2);
        expect($quota)->toBe($atteso);
    });

    it('calcolaQuota restituisce 0 se il periodo è fuori dall\'anno', function () {
        $rateo = RateoRisconto::make([
            'tipo'            => RateoRisconto::TIPO_RATEO_PASSIVO,
            'importo_totale'  => 1000.00,
            'data_inizio'     => '2024-01-01',
            'data_fine'       => '2024-12-31',
            'anno_esercizio'  => 2025,
        ]);

        expect($rateo->calcolaQuota(2025))->toBe(0.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: crea
// ─────────────────────────────────────────────────────────────────────────────

describe('RateiRiscontiService → crea', function () {

    it('crea un rateo passivo con quota calcolata automaticamente', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Interessi su mutuo maturati',
            'importo_totale'     => 1200.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        expect($rateo->stato)->toBe(RateoRisconto::STATO_DA_REGISTRARE);
        expect($rateo->quota_esercizio)->toBe(1200.0); // intero anno
        expect($rateo->tipo)->toBe(RateoRisconto::TIPO_RATEO_PASSIVO);
    });

    it('accetta quota_esercizio manuale sovrascrivendo il calcolo', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RISCONTO_ATTIVO,
            'descrizione'        => 'Affitto pagato in anticipo',
            'importo_totale'     => 1200.00,
            'quota_esercizio'    => 300.00,
            'data_inizio'        => '2025-10-01',
            'data_fine'          => '2026-09-30',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiAttivi->id,
        ]);

        expect($rateo->quota_esercizio)->toBe(300.0);
    });

    it('lancia eccezione se data_fine è precedente a data_inizio', function () {
        expect(fn () => $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Errore date',
            'importo_totale'     => 100.00,
            'data_inizio'        => '2025-12-31',
            'data_fine'          => '2025-01-01',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]))->toThrow(\InvalidArgumentException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: registra
// ─────────────────────────────────────────────────────────────────────────────

describe('RateiRiscontiService → registra', function () {

    it('genera scrittura contabile per rateo_passivo', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Interessi passivi maturati',
            'quota_esercizio'    => 500.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        $rateo = $this->service->registra($rateo);

        expect($rateo->stato)->toBe(RateoRisconto::STATO_REGISTRATO);
        expect($rateo->movimento_id)->not->toBeNull();

        $mov = MovimentoContabile::with('righe')->find($rateo->movimento_id);
        expect($mov->stato)->toBe('definitivo');
        expect($mov->isBilanciato())->toBeTrue();

        // rateo_passivo: DARE costo, AVERE ratei passivi
        $righe = $mov->righe->keyBy('conto_contabile_id');
        expect((float) $righe[$this->contoCosto->id]->importo_dare)->toBe(500.0);
        expect((float) $righe[$this->contoRateiPassivi->id]->importo_avere)->toBe(500.0);
    });

    it('genera scrittura contabile per risconto_attivo', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RISCONTO_ATTIVO,
            'descrizione'        => 'Affitto pagato in anticipo',
            'quota_esercizio'    => 200.00,
            'data_inizio'        => '2025-10-01',
            'data_fine'          => '2026-03-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiAttivi->id,
        ]);

        $rateo = $this->service->registra($rateo);
        $mov   = MovimentoContabile::with('righe')->find($rateo->movimento_id);

        expect($mov->isBilanciato())->toBeTrue();

        // risconto_attivo: DARE risconti attivi (SP), AVERE costo (CE)
        $righe = $mov->righe->keyBy('conto_contabile_id');
        expect((float) $righe[$this->contoRateiAttivi->id]->importo_dare)->toBe(200.0);
        expect((float) $righe[$this->contoCosto->id]->importo_avere)->toBe(200.0);
    });

    it('genera scrittura per rateo_attivo', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_ATTIVO,
            'descrizione'        => 'Affitti attivi maturati',
            'quota_esercizio'    => 800.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoRicavo->id,
            'conto_rettifica_id' => $this->contoRateiAttivi->id,
        ]);

        $rateo = $this->service->registra($rateo);
        $mov   = MovimentoContabile::with('righe')->find($rateo->movimento_id);

        // rateo_attivo: DARE ratei attivi (SP), AVERE ricavo (CE)
        $righe = $mov->righe->keyBy('conto_contabile_id');
        expect((float) $righe[$this->contoRateiAttivi->id]->importo_dare)->toBe(800.0);
        expect((float) $righe[$this->contoRicavo->id]->importo_avere)->toBe(800.0);
    });

    it('genera scrittura per risconto_passivo', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RISCONTO_PASSIVO,
            'descrizione'        => 'Ricavo incassato in anticipo',
            'quota_esercizio'    => 400.00,
            'data_inizio'        => '2025-10-01',
            'data_fine'          => '2026-03-31',
            'conto_economico_id' => $this->contoRicavo->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        $rateo = $this->service->registra($rateo);
        $mov   = MovimentoContabile::with('righe')->find($rateo->movimento_id);

        // risconto_passivo: DARE ricavo (CE), AVERE risconti passivi (SP)
        $righe = $mov->righe->keyBy('conto_contabile_id');
        expect((float) $righe[$this->contoRicavo->id]->importo_dare)->toBe(400.0);
        expect((float) $righe[$this->contoRateiPassivi->id]->importo_avere)->toBe(400.0);
    });

    it('lancia eccezione se già registrato', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Duplicato',
            'quota_esercizio'    => 100.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        $rateo = $this->service->registra($rateo);

        expect(fn () => $this->service->registra($rateo))
            ->toThrow(\InvalidArgumentException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: storna
// ─────────────────────────────────────────────────────────────────────────────

describe('RateiRiscontiService → storna', function () {

    it('genera movimento di storno con righe invertite nell\'anno successivo', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Interesse da stornare',
            'quota_esercizio'    => 600.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        $rateo = $this->service->registra($rateo);
        $rateo = $this->service->storna($rateo);

        expect($rateo->stato)->toBe(RateoRisconto::STATO_STORNATO);
        expect($rateo->storno_id)->not->toBeNull();

        $storno = MovimentoContabile::with('righe')->find($rateo->storno_id);
        expect($storno->anno_esercizio)->toBe(2026);
        expect($storno->data_registrazione->format('Y-m-d'))->toBe('2026-01-01');
        expect($storno->isBilanciato())->toBeTrue();

        // Storno inverte: DARE ratei passivi, AVERE costo
        $righe = $storno->righe->keyBy('conto_contabile_id');
        expect((float) $righe[$this->contoRateiPassivi->id]->importo_dare)->toBe(600.0);
        expect((float) $righe[$this->contoCosto->id]->importo_avere)->toBe(600.0);
    });

    it('lancia eccezione se non è registrato', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Non registrato',
            'quota_esercizio'    => 100.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        expect(fn () => $this->service->storna($rateo))
            ->toThrow(\InvalidArgumentException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: aggiorna e elimina
// ─────────────────────────────────────────────────────────────────────────────

describe('RateiRiscontiService → aggiorna e elimina', function () {

    it('aggiorna descrizione e quota di un rateo da_registrare', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Originale',
            'quota_esercizio'    => 100.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        $rateo = $this->service->aggiorna($rateo, [
            'descrizione'     => 'Aggiornata',
            'quota_esercizio' => 250.00,
            'data_inizio'     => '2025-01-01',
            'data_fine'       => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        expect($rateo->descrizione)->toBe('Aggiornata');
        expect($rateo->quota_esercizio)->toBe(250.0);
    });

    it('impedisce aggiornamento di un rateo già registrato', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Già registrato',
            'quota_esercizio'    => 100.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);
        $rateo = $this->service->registra($rateo);

        expect(fn () => $this->service->aggiorna($rateo, ['descrizione' => 'Tentativo']))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('elimina un rateo da_registrare', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Da eliminare',
            'quota_esercizio'    => 50.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);
        $id = $rateo->id;

        $this->service->elimina($rateo);

        expect(RateoRisconto::find($id))->toBeNull();
    });

    it('impedisce eliminazione di un rateo registrato', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Già registrato',
            'quota_esercizio'    => 50.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);
        $rateo = $this->service->registra($rateo);

        expect(fn () => $this->service->elimina($rateo))
            ->toThrow(\InvalidArgumentException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: registraBatch
// ─────────────────────────────────────────────────────────────────────────────

describe('RateiRiscontiService → registraBatch', function () {

    it('registra tutti i ratei da_registrare di un anno', function () {
        foreach (range(1, 3) as $i) {
            $this->service->crea($this->tenant, [
                'anno_esercizio'     => 2025,
                'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
                'descrizione'        => "Rateo batch #{$i}",
                'quota_esercizio'    => 100.00 * $i,
                'data_inizio'        => '2025-01-01',
                'data_fine'          => '2025-12-31',
                'conto_economico_id' => $this->contoCosto->id,
                'conto_rettifica_id' => $this->contoRateiPassivi->id,
            ]);
        }

        $risultato = $this->service->registraBatch($this->tenant, 2025);

        expect($risultato['registrati'])->toBe(3);
        expect($risultato['errori'])->toBeEmpty();

        $count = RateoRisconto::where('tenant_id', $this->tenant->id)
            ->where('anno_esercizio', 2025)
            ->where('stato', RateoRisconto::STATO_REGISTRATO)
            ->count();

        expect($count)->toBe(3);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller HTTP
// ─────────────────────────────────────────────────────────────────────────────

describe('RateiRiscontiController', function () {

    it('store crea un nuovo rateo/risconto', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ratei-risconti.store', $this->tenant), [
                'anno_esercizio'     => 2025,
                'tipo'               => RateoRisconto::TIPO_RISCONTO_ATTIVO,
                'descrizione'        => 'Affitto anticipato test',
                'importo_totale'     => 1200,
                'quota_esercizio'    => 300,
                'data_inizio'        => '2025-10-01',
                'data_fine'          => '2026-03-31',
                'conto_economico_id' => $this->contoCosto->id,
                'conto_rettifica_id' => $this->contoRateiAttivi->id,
            ])
            ->assertRedirect();

        expect(
            RateoRisconto::where('tenant_id', $this->tenant->id)
                ->where('descrizione', 'Affitto anticipato test')
                ->exists()
        )->toBeTrue();
    });

    it('registra genera la scrittura contabile', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Via controller',
            'quota_esercizio'    => 150.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ratei-risconti.registra', [$this->tenant, $rateo->id]))
            ->assertRedirect();

        expect($rateo->fresh()->stato)->toBe(RateoRisconto::STATO_REGISTRATO);
    });

    it('destroy elimina un rateo da_registrare', function () {
        $rateo = $this->service->crea($this->tenant, [
            'anno_esercizio'     => 2025,
            'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
            'descrizione'        => 'Da eliminare controller',
            'quota_esercizio'    => 75.00,
            'data_inizio'        => '2025-01-01',
            'data_fine'          => '2025-12-31',
            'conto_economico_id' => $this->contoCosto->id,
            'conto_rettifica_id' => $this->contoRateiPassivi->id,
        ]);

        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->delete(route('ratei-risconti.destroy', [$this->tenant, $rateo->id]))
            ->assertRedirect();

        expect(RateoRisconto::find($rateo->id))->toBeNull();
    });

    it('store fallisce senza ruolo admin/contabile', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSenza)
            ->post(route('ratei-risconti.store', $this->tenant), [
                'anno_esercizio'     => 2025,
                'tipo'               => RateoRisconto::TIPO_RATEO_PASSIVO,
                'descrizione'        => 'Non autorizzato',
                'quota_esercizio'    => 100,
                'data_inizio'        => '2025-01-01',
                'data_fine'          => '2025-12-31',
                'conto_economico_id' => $this->contoCosto->id,
                'conto_rettifica_id' => $this->contoRateiPassivi->id,
            ])
            ->assertForbidden();

        expect(RateoRisconto::count())->toBe(0);
    });
});
