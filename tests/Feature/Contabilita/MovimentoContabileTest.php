<?php

use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\MovimentoContabile;
use App\Models\RigaMovimentoContabile;
use App\Models\Tenant;
use App\Services\MovimentoContabileService;
use Database\Seeders\CausaliContabiliDiSistemaSeeder;
use Database\Seeders\PianoContiCooperativaSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup globale
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Contab Test',
        'slug'              => 'coop-contab-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    // L'observer ha già seedato PianoConti e Causali; il seeder è idempotente
    // quindi questa chiamata esplicita serve solo come documentazione della dipendenza
    (new PianoContiCooperativaSeeder)->perTenant($this->tenant);
    (new CausaliContabiliDiSistemaSeeder)->perTenant($this->tenant);

    $this->service = app(MovimentoContabileService::class);

    // Recupera conti movimentabili (livello 4) su cui fare test
    $this->contoClienti   = ContoContabile::where('codice', '1.50.05.001')->first(); // Clienti Italia
    $this->contoBanca     = ContoContabile::where('codice', '1.60.05.001')->first(); // Banca c/c
    $this->contoRicavi    = ContoContabile::where('codice', '4.05.05.001')->first(); // Ricavi istituzionali
    $this->contoAcquisti  = ContoContabile::where('codice', '5.10.05.001')->first(); // Acquisti merci
    $this->contoFornitori = ContoContabile::where('codice', '2.40.05.001')->first(); // Fornitori Italia

    // Causale generica di sistema
    $this->causaleGen = CausaleContabile::where('codice', 'GIR')->first();
    $this->causaleFac = CausaleContabile::where('codice', 'FAC')->first();
    $this->causaleFvc = CausaleContabile::where('codice', 'FVC')->first();
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// CausaleContabile
// ─────────────────────────────────────────────────────────────────────────────

describe('CausaleContabile', function () {

    it('il seeder crea le causali di sistema', function () {
        $count = CausaleContabile::count();
        expect($count)->toBeGreaterThanOrEqual(20);
    });

    it('tutte le causali di sistema hanno di_sistema=true e attivo=true', function () {
        CausaleContabile::each(function (CausaleContabile $c) {
            expect($c->di_sistema)->toBeTrue();
            expect($c->attivo)->toBeTrue();
        });
    });

    it('il seeder è idempotente', function () {
        $countPrima = CausaleContabile::count();
        (new CausaliContabiliDiSistemaSeeder)->perTenant($this->tenant);
        expect(CausaleContabile::count())->toBe($countPrima);
    });

    it('codici causale sono univoci per tenant', function () {
        $codici = CausaleContabile::pluck('codice')->toArray();
        expect(array_unique($codici))->toHaveCount(count($codici));
    });

    it('scope attive filtra correttamente', function () {
        // Disattiva una causale
        $causale = CausaleContabile::where('codice', 'RET')->first();
        $causale->update(['attivo' => false]);

        $attive = CausaleContabile::attive()->pluck('codice')->toArray();
        expect($attive)->not->toContain('RET');
    });

    it('scope byTipo filtra per tipo', function () {
        $acquisti = CausaleContabile::byTipo('fattura_acquisto')->get();
        expect($acquisti)->not->toBeEmpty();
        foreach ($acquisti as $c) {
            expect($c->tipo)->toBe('fattura_acquisto');
        }
    });

    it('scope search trova per codice o descrizione', function () {
        expect(CausaleContabile::search('FAC')->count())->toBeGreaterThan(0);
        expect(CausaleContabile::search('fattura')->count())->toBeGreaterThan(0);
        expect(CausaleContabile::search('zzz_impossibile')->count())->toBe(0);
    });

    it('isUsata ritorna false se nessun movimento usa la causale', function () {
        expect($this->causaleGen->isUsata())->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MovimentoContabileService — validazione righe
// ─────────────────────────────────────────────────────────────────────────────

describe('MovimentoContabileService → validazione', function () {

    it('lancia eccezione se le righe sono meno di 2', function () {
        expect(fn () => $this->service->validaRighe($this->tenant, [
            ['conto_contabile_id' => $this->contoBanca->id, 'importo_dare' => 100],
        ]))->toThrow(InvalidArgumentException::class, 'almeno 2 righe');
    });

    it('lancia eccezione se dare e avere sono entrambi zero', function () {
        expect(fn () => $this->service->validaRighe($this->tenant, [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare' => 0, 'importo_avere' => 0],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_dare' => 0, 'importo_avere' => 0],
        ]))->toThrow(InvalidArgumentException::class, 'entrambi zero');
    });

    it('lancia eccezione se dare e avere sono entrambi positivi', function () {
        expect(fn () => $this->service->validaRighe($this->tenant, [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare' => 100, 'importo_avere' => 50],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_dare' => 50,  'importo_avere' => 0],
        ]))->toThrow(InvalidArgumentException::class, 'entrambi positivi');
    });

    it('lancia eccezione se il conto non è movimentabile (livello < 4)', function () {
        $mastro = ContoContabile::where('livello', 2)->first();
        expect($mastro)->not->toBeNull();

        expect(fn () => $this->service->validaRighe($this->tenant, [
            ['conto_contabile_id' => $mastro->id,             'importo_dare'  => 100],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 100],
        ]))->toThrow(InvalidArgumentException::class, 'non è movimentabile');
    });

    it('lancia eccezione se il movimento non è bilanciato', function () {
        expect(fn () => $this->service->validaRighe($this->tenant, [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 100],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 99.99],
        ]))->toThrow(InvalidArgumentException::class, 'non bilanciato');
    });

    it('non lancia eccezione con righe valide e bilanciate', function () {
        expect(fn () => $this->service->validaRighe($this->tenant, [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 1000],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 1000],
        ]))->not->toThrow(InvalidArgumentException::class);
    });

    it('accetta bilanciamento con più righe (più dare e più avere)', function () {
        expect(fn () => $this->service->validaRighe($this->tenant, [
            ['conto_contabile_id' => $this->contoAcquisti->id,  'importo_dare'  => 1000],
            ['conto_contabile_id' => $this->contoBanca->id,     'importo_dare'  =>  220],
            ['conto_contabile_id' => $this->contoFornitori->id, 'importo_avere' => 1000],
            ['conto_contabile_id' => $this->contoFornitori->id, 'importo_avere' =>  220],
        ]))->not->toThrow(InvalidArgumentException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MovimentoContabileService — creazione
// ─────────────────────────────────────────────────────────────────────────────

describe('MovimentoContabileService → creazione', function () {

    it('crea un movimento in stato bozza', function () {
        $movimento = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-04-22',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Giroconto test',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 500],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 500],
        ]);

        expect($movimento->stato)->toBe(MovimentoContabile::STATO_BOZZA);
        expect($movimento->numero)->toBe(1);
        expect($movimento->anno_esercizio)->toBe(2026);
        expect($movimento->righe)->toHaveCount(2);
    });

    it('assegna numeri progressivi per anno', function () {
        $dati = fn (string $desc) => [
            ['data_registrazione' => '2026-04-01', 'causale_id' => $this->causaleGen->id, 'descrizione' => $desc],
            [
                ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 100],
                ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 100],
            ],
        ];

        $m1 = $this->service->crea($this->tenant, ...$dati('Prima scrittura'));
        $m2 = $this->service->crea($this->tenant, ...$dati('Seconda scrittura'));
        $m3 = $this->service->crea($this->tenant, ...$dati('Terza scrittura'));

        expect($m1->numero)->toBe(1);
        expect($m2->numero)->toBe(2);
        expect($m3->numero)->toBe(3);
    });

    it('numerazione riparte da 1 per anno diverso', function () {
        $righe = [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 100],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 100],
        ];

        $m2025 = $this->service->crea($this->tenant, [
            'data_registrazione' => '2025-01-10',
            'anno_esercizio'     => 2025,
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Scrittura 2025',
        ], $righe);

        $m2026 = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-01-10',
            'anno_esercizio'     => 2026,
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Scrittura 2026',
        ], $righe);

        expect($m2025->numero)->toBe(1);
        expect($m2026->numero)->toBe(1);
    });

    it('le righe hanno importi corretti e ordine', function () {
        $movimento = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-04-22',
            'causale_id'         => $this->causaleFac->id,
            'descrizione'        => 'Fattura fornitore 001',
            'numero_documento'   => 'FPA-001',
        ], [
            ['conto_contabile_id' => $this->contoAcquisti->id,  'importo_dare'  => 1000.00, 'descrizione' => 'Merci c/acquisti'],
            ['conto_contabile_id' => $this->contoFornitori->id, 'importo_avere' => 1000.00, 'descrizione' => 'Debiti v/fornitori'],
        ]);

        $righe = $movimento->righe;
        expect($righe->first()->importo_dare)->toEqual('1000.00');
        expect($righe->last()->importo_avere)->toEqual('1000.00');
        expect($righe->first()->ordine)->toBe(1);
        expect($righe->last()->ordine)->toBe(2);
    });

    it('etichetta restituisce numero/anno', function () {
        $m = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-04-22',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Test etichetta',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 10],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 10],
        ]);

        expect($m->etichetta)->toBe('1/2026');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MovimentoContabileService — conferma
// ─────────────────────────────────────────────────────────────────────────────

describe('MovimentoContabileService → conferma', function () {

    beforeEach(function () {
        $this->movimento = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-04-22',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Movimento da confermare',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 200],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 200],
        ]);
    });

    it('porta il movimento in stato definitivo', function () {
        $definitivo = $this->service->conferma($this->movimento);
        expect($definitivo->stato)->toBe(MovimentoContabile::STATO_DEFINITIVO);
        expect($definitivo->isDefinitivo())->toBeTrue();
    });

    it('lancia eccezione se il movimento non è in bozza', function () {
        $this->service->conferma($this->movimento);

        expect(fn () => $this->service->conferma($this->movimento->fresh()))
            ->toThrow(InvalidArgumentException::class, 'non è in bozza');
    });

    it('lancia eccezione se il movimento è locked', function () {
        $this->movimento->update(['locked' => true]);

        expect(fn () => $this->service->conferma($this->movimento->fresh()))
            ->toThrow(InvalidArgumentException::class, 'bloccato');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MovimentoContabileService — storno
// ─────────────────────────────────────────────────────────────────────────────

describe('MovimentoContabileService → storno', function () {

    beforeEach(function () {
        $this->movimento = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-04-22',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Movimento da stornare',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 300, 'descrizione' => 'Banca'],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 300, 'descrizione' => 'Clienti'],
        ]);

        $this->service->conferma($this->movimento);
        $this->movimento->refresh();
    });

    it('crea un movimento di storno con righe invertite', function () {
        $storno = $this->service->storna($this->movimento);

        expect($storno->stato)->toBe(MovimentoContabile::STATO_DEFINITIVO);
        expect($storno->movimento_origine_id)->toBe($this->movimento->id);

        $righeStorno = $storno->righe;
        // La riga che era "dare 300" diventa "avere 300" e viceversa
        $rBanca     = $righeStorno->firstWhere('conto_contabile_id', $this->contoBanca->id);
        $rClienti   = $righeStorno->firstWhere('conto_contabile_id', $this->contoClienti->id);

        expect((float) $rBanca->importo_dare)->toBe(0.0);
        expect((float) $rBanca->importo_avere)->toBe(300.0);
        expect((float) $rClienti->importo_dare)->toBe(300.0);
        expect((float) $rClienti->importo_avere)->toBe(0.0);
    });

    it('marca il movimento originale come stornato', function () {
        $this->service->storna($this->movimento);
        expect($this->movimento->fresh()->stato)->toBe(MovimentoContabile::STATO_STORNATO);
    });

    it('lo storno è bilanciato', function () {
        $storno = $this->service->storna($this->movimento);
        $storno->load('righe');
        expect($storno->isBilanciato())->toBeTrue();
    });

    it('lancia eccezione su storno di bozza', function () {
        $bozza = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-04-22',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Bozza',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 10],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 10],
        ]);

        expect(fn () => $this->service->storna($bozza))
            ->toThrow(InvalidArgumentException::class, 'non è definitivo');
    });

    it('non permette di stornare due volte lo stesso movimento', function () {
        $this->service->storna($this->movimento);

        expect(fn () => $this->service->storna($this->movimento->fresh()))
            ->toThrow(InvalidArgumentException::class);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// MovimentoContabileService — saldo conto
// ─────────────────────────────────────────────────────────────────────────────

describe('MovimentoContabileService → saldo conto', function () {

    it('saldo è zero se non ci sono movimenti definitivi', function () {
        // Crea solo bozza
        $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-01-10',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Bozza',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 500],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 500],
        ]);

        $saldo = $this->service->saldoConto($this->contoBanca, 2026);
        expect($saldo)->toBe(0.0);
    });

    it('saldo dare positivo su conto con eccedenza dare', function () {
        $m = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-03-01',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Scrittura saldo',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 1500],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 1500],
        ]);

        $this->service->conferma($m);

        $saldo = $this->service->saldoConto($this->contoBanca, 2026);
        expect($saldo)->toBe(1500.0);
    });

    it('saldo accumulato su più movimenti definitivi', function () {
        $righe1 = [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 1000],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 1000],
        ];
        $righe2 = [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 500],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 500],
        ];

        $base = [
            'causale_id'  => $this->causaleGen->id,
            'descrizione' => 'Saldo multiplo',
        ];

        $m1 = $this->service->crea($this->tenant, array_merge($base, ['data_registrazione' => '2026-01-15']), $righe1);
        $m2 = $this->service->crea($this->tenant, array_merge($base, ['data_registrazione' => '2026-02-20']), $righe2);

        $this->service->conferma($m1);
        $this->service->conferma($m2);

        $saldo = $this->service->saldoConto($this->contoBanca, 2026);
        expect($saldo)->toBe(1500.0);
    });

    it('stornare un movimento azzera il suo contributo al saldo', function () {
        $m = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-04-01',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Da stornare',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 800],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 800],
        ]);

        $this->service->conferma($m);
        expect($this->service->saldoConto($this->contoBanca, 2026))->toBe(800.0);

        $this->service->storna($m->fresh());
        expect($this->service->saldoConto($this->contoBanca, 2026))->toBe(0.0);
    });

    it('saldoConti calcola più saldi in una sola query', function () {
        $m = $this->service->crea($this->tenant, [
            'data_registrazione' => '2026-03-15',
            'causale_id'         => $this->causaleGen->id,
            'descrizione'        => 'Multi-saldo',
        ], [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 200],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 200],
        ]);

        $this->service->conferma($m);

        $saldi = $this->service->saldoConti(
            [$this->contoBanca->id, $this->contoClienti->id],
            $this->tenant->id,
            2026
        );

        // I saldi aggregati devono coincidere con quelli singoli
        expect($saldi)->toHaveKey($this->contoBanca->id);
        expect($saldi)->toHaveKey($this->contoClienti->id);
        expect($saldi[$this->contoBanca->id])->toBe(200.0);
        expect($saldi[$this->contoClienti->id])->toBe(-200.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Scopes MovimentoContabile
// ─────────────────────────────────────────────────────────────────────────────

describe('scopes MovimentoContabile', function () {

    beforeEach(function () {
        $righe = [
            ['conto_contabile_id' => $this->contoBanca->id,   'importo_dare'  => 100],
            ['conto_contabile_id' => $this->contoClienti->id, 'importo_avere' => 100],
        ];
        $base = ['causale_id' => $this->causaleGen->id, 'descrizione' => 'Scope test'];

        $bozza = $this->service->crea($this->tenant, array_merge($base, ['data_registrazione' => '2026-01-10']), $righe);

        $def = $this->service->crea($this->tenant, array_merge($base, ['data_registrazione' => '2026-02-15']), $righe);
        $this->service->conferma($def);
        $def->refresh();

        $daStornare = $this->service->crea($this->tenant, array_merge($base, ['data_registrazione' => '2026-03-01']), $righe);
        $this->service->conferma($daStornare);
        $this->service->storna($daStornare->fresh());

        $this->bozza = $bozza;
        $this->definitivo = $def;
    });

    it('scope definitivi esclude bozze e stornati', function () {
        $definitivi = MovimentoContabile::definitivi()->get();
        expect($definitivi->every(fn ($m) => $m->stato === MovimentoContabile::STATO_DEFINITIVO))->toBeTrue();
    });

    it('scope bozze esclude definitivi e stornati', function () {
        $bozze = MovimentoContabile::bozze()->get();
        expect($bozze->every(fn ($m) => $m->isBozza()))->toBeTrue();
    });

    it('scope perAnno filtra per anno_esercizio', function () {
        $count2026 = MovimentoContabile::perAnno(2026)->count();
        expect($count2026)->toBeGreaterThan(0);

        $count2020 = MovimentoContabile::perAnno(2020)->count();
        expect($count2020)->toBe(0);
    });

    it('scope perPeriodo filtra per data_registrazione', function () {
        // Solo Gen/Feb
        $count = MovimentoContabile::perPeriodo('2026-01-01', '2026-02-28')->count();
        expect($count)->toBe(2); // bozza (gen) + definitivo (feb)

        // Solo Mar
        $countMar = MovimentoContabile::perPeriodo('2026-03-01', '2026-03-31')->count();
        expect($countMar)->toBeGreaterThan(0); // da stornare + storno
    });

    it('scope nonLocked esclude i movimenti bloccati', function () {
        $this->definitivo->update(['locked' => true]);

        $nonLocked = MovimentoContabile::nonLocked()->get();
        expect($nonLocked->pluck('id'))->not->toContain($this->definitivo->id);
    });
});
