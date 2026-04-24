<?php

/**
 * Test suite per EsercizioContabile e AperturaChiusuraService.
 *
 * Copre:
 *  - Model: scopes (aperti, chiusi, perAnno), helper (isAperto, isChiuso, isConfiguratoPerChiusura)
 *  - Service: apriEsercizio (idempotente), chiudiEsercizio (lock movimenti, scritture CE/SP)
 *  - Service: riaperiEsercizio (unlock, cancella scritture)
 *  - Service: eccezioni (già chiuso, non configurato, anno successivo chiuso)
 *  - Controller: store, close, reopen, middleware role:admin,contabile
 */

use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\EsercizioContabile;
use App\Models\MovimentoContabile;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AperturaChiusuraService;
use App\Services\MovimentoContabileService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup globale
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Esercizio Test',
        'slug'              => 'ets-ese-test-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();
    $this->user = User::factory()->create([
        'email'             => 'admin@esercizio.it',
        'email_verified_at' => now(),
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    $this->userSenza = User::factory()->create([
        'email'             => 'nessuno@esercizio.it',
        'email_verified_at' => now(),
    ]);
    $this->userSenza->tenants()->attach($this->tenant);

    // Conti contabili necessari per chiusura
    $this->contoRicavo = ContoContabile::create([
        'codice'       => '4.10.01.001',
        'descrizione'  => 'Ricavi vendite',
        'livello'      => 4,
        'natura'       => ContoContabile::NATURA_RICAVO,
        'segno_naturale' => 'avere',
        'movimentabile' => true,
        'di_sistema'   => false,
        'attivo'       => true,
    ]);

    $this->contoCosto = ContoContabile::create([
        'codice'       => '3.10.01.001',
        'descrizione'  => 'Acquisti merci',
        'livello'      => 4,
        'natura'       => ContoContabile::NATURA_COSTO,
        'segno_naturale' => 'dare',
        'movimentabile' => true,
        'di_sistema'   => false,
        'attivo'       => true,
    ]);

    $this->contoBanca = ContoContabile::create([
        'codice'       => '1.30.10.001',
        'descrizione'  => 'Banca c/c',
        'livello'      => 4,
        'natura'       => ContoContabile::NATURA_ATTIVO,
        'segno_naturale' => 'dare',
        'movimentabile' => true,
        'di_sistema'   => false,
        'attivo'       => true,
    ]);

    $this->contoFornitori = ContoContabile::create([
        'codice'       => '2.20.01.001',
        'descrizione'  => 'Debiti v/fornitori',
        'livello'      => 4,
        'natura'       => ContoContabile::NATURA_PASSIVO,
        'segno_naturale' => 'avere',
        'movimentabile' => true,
        'di_sistema'   => false,
        'attivo'       => true,
    ]);

    // Conto transitorio per chiusura CE (riepilogo utile/perdita)
    $this->contoChiusuraCe = ContoContabile::create([
        'codice'       => '9.99.01.001',
        'descrizione'  => 'Riepilogo Conto Economico',
        'livello'      => 4,
        'natura'       => ContoContabile::NATURA_TRANSITORIO,
        'segno_naturale' => 'avere',
        'movimentabile' => true,
        'di_sistema'   => true,
        'attivo'       => true,
    ]);

    // Conto transitorio per apertura/chiusura SP
    $this->contoApertura = ContoContabile::create([
        'codice'       => '9.99.02.001',
        'descrizione'  => 'Conto Patrimoniale di Apertura',
        'livello'      => 4,
        'natura'       => ContoContabile::NATURA_TRANSITORIO,
        'segno_naturale' => 'avere',
        'movimentabile' => true,
        'di_sistema'   => true,
        'attivo'       => true,
    ]);

    $this->service = app(AperturaChiusuraService::class);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// Model — scopes e helper
// ─────────────────────────────────────────────────────────────────────────────

describe('EsercizioContabile model', function () {

    it('isAperto e isChiuso funzionano', function () {
        $e = EsercizioContabile::create([
            'anno'  => 2025,
            'stato' => EsercizioContabile::STATO_APERTO,
        ]);

        expect($e->isAperto())->toBeTrue()
            ->and($e->isChiuso())->toBeFalse();

        $e->update(['stato' => EsercizioContabile::STATO_CHIUSO]);
        $e->refresh();

        expect($e->isChiuso())->toBeTrue()
            ->and($e->isAperto())->toBeFalse();
    });

    it('isConfiguratoPerChiusura richiede entrambi i conti', function () {
        $e = EsercizioContabile::create([
            'anno'  => 2025,
            'stato' => EsercizioContabile::STATO_APERTO,
        ]);

        expect($e->isConfiguratoPerChiusura())->toBeFalse();

        $e->update(['conto_chiusura_ce_id' => $this->contoChiusuraCe->id]);
        $e->refresh();
        expect($e->isConfiguratoPerChiusura())->toBeFalse(); // solo uno

        $e->update(['conto_apertura_id' => $this->contoApertura->id]);
        $e->refresh();
        expect($e->isConfiguratoPerChiusura())->toBeTrue(); // entrambi
    });

    it('scope aperti e chiusi filtrano correttamente', function () {
        EsercizioContabile::create(['anno' => 2024, 'stato' => EsercizioContabile::STATO_CHIUSO]);
        EsercizioContabile::create(['anno' => 2025, 'stato' => EsercizioContabile::STATO_APERTO]);

        expect(EsercizioContabile::aperti()->count())->toBe(1)
            ->and(EsercizioContabile::chiusi()->count())->toBe(1)
            ->and(EsercizioContabile::perAnno(2024)->first()->anno)->toBe(2024);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Service — apriEsercizio
// ─────────────────────────────────────────────────────────────────────────────

describe('AperturaChiusuraService → apriEsercizio', function () {

    it('crea un nuovo esercizio contabile', function () {
        $e = $this->service->apriEsercizio($this->tenant, 2025);

        expect($e)->not->toBeNull()
            ->and($e->anno)->toBe(2025)
            ->and($e->stato)->toBe(EsercizioContabile::STATO_APERTO)
            ->and($e->data_apertura->format('Y'))->toBe('2025');

        expect(EsercizioContabile::perAnno(2025)->count())->toBe(1);
    });

    it('apriEsercizio è idempotente: restituisce quello esistente', function () {
        $e1 = $this->service->apriEsercizio($this->tenant, 2025);
        $e2 = $this->service->apriEsercizio($this->tenant, 2025);

        expect($e1->id)->toBe($e2->id);
        expect(EsercizioContabile::perAnno(2025)->count())->toBe(1);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Service — chiudiEsercizio
// ─────────────────────────────────────────────────────────────────────────────

describe('AperturaChiusuraService → chiudiEsercizio', function () {

    beforeEach(function () {
        $this->esercizio = EsercizioContabile::create([
            'anno'                 => 2025,
            'stato'                => EsercizioContabile::STATO_APERTO,
            'conto_chiusura_ce_id' => $this->contoChiusuraCe->id,
            'conto_apertura_id'    => $this->contoApertura->id,
        ]);
    });

    it('lancia eccezione se esercizio già chiuso', function () {
        $this->esercizio->update(['stato' => EsercizioContabile::STATO_CHIUSO]);

        expect(fn () => $this->service->chiudiEsercizio($this->esercizio))
            ->toThrow(\InvalidArgumentException::class, '2025');
    });

    it('lancia eccezione se conti di chiusura non configurati', function () {
        $eNonConfig = EsercizioContabile::create([
            'anno'  => 2026,
            'stato' => EsercizioContabile::STATO_APERTO,
        ]);

        expect(fn () => $this->service->chiudiEsercizio($eNonConfig))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('blocca tutti i movimenti definitivi dell\'anno', function () {
        // Crea movimento definitivo per il 2025
        $movSvc = app(MovimentoContabileService::class);
        $mov = $movSvc->crea($this->tenant, [
            'data_registrazione' => '2025-06-15',
            'causale_id'         => CausaleContabile::firstOrCreate(
                ['tipo' => CausaleContabile::TIPO_GENERICO, 'di_sistema' => true],
                ['codice' => 'GEN', 'descrizione' => 'Generico', 'attivo' => true]
            )->id,
            'descrizione' => 'Test movimento',
            'anno_esercizio' => 2025,
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,     'importo_dare'  => 1000, 'importo_avere' => 0],
            ['conto_contabile_id' => $this->contoRicavo->id,    'importo_dare'  => 0,    'importo_avere' => 1000],
        ]);
        $movSvc->conferma($mov);

        // Verifica che non sia locked prima della chiusura
        expect($mov->fresh()->locked)->toBeFalse();

        // Chiudi esercizio
        $this->service->chiudiEsercizio($this->esercizio);

        // Verifica che sia ora locked
        expect($mov->fresh()->locked)->toBeTrue();
    });

    it('genera il movimento di apertura per l\'anno successivo', function () {
        // Crea un movimento per il 2025 (acquisto merce)
        $movSvc = app(MovimentoContabileService::class);
        $causale = CausaleContabile::firstOrCreate(
            ['tipo' => CausaleContabile::TIPO_GENERICO, 'di_sistema' => true],
            ['codice' => 'GEN', 'descrizione' => 'Generico', 'attivo' => true]
        );

        $movSvc->conferma($movSvc->crea($this->tenant, [
            'data_registrazione' => '2025-03-10',
            'causale_id'         => $causale->id,
            'descrizione'        => 'Acquisto merci',
            'anno_esercizio'     => 2025,
        ], [
            ['conto_contabile_id' => $this->contoCosto->id,     'importo_dare' => 500, 'importo_avere' => 0],
            ['conto_contabile_id' => $this->contoFornitori->id, 'importo_dare' => 0,   'importo_avere' => 500],
        ]));

        $this->service->chiudiEsercizio($this->esercizio);

        $this->esercizio->refresh();

        // Deve essere stato generato il movimento di apertura per il 2026
        expect($this->esercizio->movimento_apertura_id)->not->toBeNull();

        $movApertura = MovimentoContabile::withoutGlobalScope('tenant')
            ->find($this->esercizio->movimento_apertura_id);

        expect($movApertura)->not->toBeNull()
            ->and($movApertura->anno_esercizio)->toBe(2026)
            ->and($movApertura->stato)->toBe(MovimentoContabile::STATO_DEFINITIVO);
    });

    it('porta l\'esercizio in stato chiuso', function () {
        $this->service->chiudiEsercizio($this->esercizio);

        $this->esercizio->refresh();

        expect($this->esercizio->stato)->toBe(EsercizioContabile::STATO_CHIUSO)
            ->and($this->esercizio->data_chiusura)->not->toBeNull()
            ->and($this->esercizio->locked_at)->not->toBeNull();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Service — riaperiEsercizio
// ─────────────────────────────────────────────────────────────────────────────

describe('AperturaChiusuraService → riaperiEsercizio', function () {

    beforeEach(function () {
        $this->esercizio = EsercizioContabile::create([
            'anno'                 => 2025,
            'stato'                => EsercizioContabile::STATO_APERTO,
            'conto_chiusura_ce_id' => $this->contoChiusuraCe->id,
            'conto_apertura_id'    => $this->contoApertura->id,
        ]);
    });

    it('lancia eccezione se esercizio già aperto', function () {
        expect(fn () => $this->service->riaperiEsercizio($this->esercizio))
            ->toThrow(\InvalidArgumentException::class, 'già aperto');
    });

    it('sblocca i movimenti e riporta lo stato ad aperto', function () {
        // Crea e chiudi l'esercizio con un movimento
        $movSvc = app(MovimentoContabileService::class);
        $causale = CausaleContabile::firstOrCreate(
            ['tipo' => CausaleContabile::TIPO_GENERICO, 'di_sistema' => true],
            ['codice' => 'GEN', 'descrizione' => 'Generico', 'attivo' => true]
        );

        $mov = $movSvc->conferma($movSvc->crea($this->tenant, [
            'data_registrazione' => '2025-01-15',
            'causale_id'         => $causale->id,
            'descrizione'        => 'Movimento test',
            'anno_esercizio'     => 2025,
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,  'importo_dare' => 100, 'importo_avere' => 0],
            ['conto_contabile_id' => $this->contoRicavo->id, 'importo_dare' => 0,   'importo_avere' => 100],
        ]));

        $this->service->chiudiEsercizio($this->esercizio);

        // Verifiche pre-riapertura
        expect($mov->fresh()->locked)->toBeTrue()
            ->and($this->esercizio->fresh()->stato)->toBe(EsercizioContabile::STATO_CHIUSO);

        // Riapri
        $this->service->riaperiEsercizio($this->esercizio->fresh());

        $this->esercizio->refresh();
        expect($this->esercizio->stato)->toBe(EsercizioContabile::STATO_APERTO)
            ->and($this->esercizio->data_chiusura)->toBeNull()
            ->and($this->esercizio->locked_at)->toBeNull()
            ->and($mov->fresh()->locked)->toBeFalse();
    });

    it('impedisce riapertura se anno successivo è già chiuso', function () {
        $this->service->chiudiEsercizio($this->esercizio);

        // Chiudi anche il 2026
        $e2026 = EsercizioContabile::create([
            'anno'                 => 2026,
            'stato'                => EsercizioContabile::STATO_CHIUSO,
            'conto_chiusura_ce_id' => $this->contoChiusuraCe->id,
            'conto_apertura_id'    => $this->contoApertura->id,
        ]);

        expect(fn () => $this->service->riaperiEsercizio($this->esercizio->fresh()))
            ->toThrow(\InvalidArgumentException::class, '2026');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Controller HTTP
// ─────────────────────────────────────────────────────────────────────────────

describe('EsercizioContabileController', function () {

    it('store apre un nuovo esercizio e redirige', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('esercizi.store', $this->tenant), ['anno' => 2025])
            ->assertRedirect(route('esercizi.index', $this->tenant));

        expect(EsercizioContabile::perAnno(2025)->count())->toBe(1);
    });

    it('close chiude l\'esercizio configurato', function () {
        $esercizio = EsercizioContabile::create([
            'anno'                 => 2025,
            'stato'                => EsercizioContabile::STATO_APERTO,
            'conto_chiusura_ce_id' => $this->contoChiusuraCe->id,
            'conto_apertura_id'    => $this->contoApertura->id,
        ]);

        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('esercizi.close', [$this->tenant, $esercizio]))
            ->assertRedirect(route('esercizi.index', $this->tenant));

        expect($esercizio->fresh()->stato)->toBe(EsercizioContabile::STATO_CHIUSO);
    });

    it('reopen riapre un esercizio chiuso', function () {
        $esercizio = EsercizioContabile::create([
            'anno'                 => 2025,
            'stato'                => EsercizioContabile::STATO_CHIUSO,
            'conto_chiusura_ce_id' => $this->contoChiusuraCe->id,
            'conto_apertura_id'    => $this->contoApertura->id,
            'data_chiusura'        => '2025-12-31',
            'locked_at'            => now(),
        ]);

        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('esercizi.reopen', [$this->tenant, $esercizio]))
            ->assertRedirect(route('esercizi.index', $this->tenant));

        expect($esercizio->fresh()->stato)->toBe(EsercizioContabile::STATO_APERTO);
    });

    it('store fallisce senza ruolo admin/contabile', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSenza)
            ->post(route('esercizi.store', $this->tenant), ['anno' => 2025])
            ->assertForbidden();

        expect(EsercizioContabile::count())->toBe(0);
    });

});
