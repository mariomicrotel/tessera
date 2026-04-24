<?php

/**
 * Test suite per PrestitoSocialeService e il modello PrestitoSocialeLibretto.
 *
 * Copre:
 *  - Apertura libretto (numero formato PS-ANNO-XXXXX, progressivo)
 *  - Deposito (aggiorna saldo, crea movimento avere + Incasso)
 *  - Prelievo (aggiorna saldo, crea movimento dare + Spesa, validazioni)
 *  - Calcolo interessi mensili (formula, ritenuta 26%, idempotenza)
 *  - Chiusura libretto
 */

use App\Models\Conto;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\PrestitoSocialeLibretto;
use App\Models\PrestitoSocialeMovimento;
use App\Models\Spesa;
use App\Models\Tenant;
use App\Services\PrestitoSocialeService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop PS Test',
        'slug'              => 'coop-ps-test-' . uniqid(),
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    // Conto di tesoreria
    $this->conto = Conto::create([
        'name'   => 'Cassa',
        'code'   => 'CASSA',
        'type'   => 'bank',
        'ordine' => 1,
        'attivo' => true,
    ]);

    $this->service = app(PrestitoSocialeService::class);

    $memberType = MemberType::firstOrCreate(
        ['name' => 'socio'],
        ['display_name' => 'Socio'],
    );

    $this->member = Member::create([
        'member_type_id'  => $memberType->id,
        'nome'            => 'Anna',
        'cognome'         => 'Verdi',
        'email'           => 'anna.verdi@test.it',
        'numero_tessera'  => 1,
        'data_iscrizione' => '2024-01-01',
        'stato'           => 'attivo',
        'tipo_persona'    => 'fisica',
        'numero_quote_capitale' => 0,
    ]);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// apriLibretto
// ─────────────────────────────────────────────────────────────────────────────

describe('PrestitoSocialeService → apriLibretto', function () {

    it('crea un libretto con numero nel formato PS-{ANNO}-{5 cifre}', function () {
        $anno    = now()->year;
        $libretto = $this->service->apriLibretto(
            member:    $this->member,
            tassoAnnuo: 0.02,
            data:      Carbon::now(),
        );

        expect($libretto)->toBeInstanceOf(PrestitoSocialeLibretto::class)
            ->and($libretto->numero_libretto)->toStartWith("PS-{$anno}-")
            ->and(strlen($libretto->numero_libretto))->toBe(strlen("PS-{$anno}-") + 5);
    });

    it('il saldo iniziale è zero', function () {
        $libretto = $this->service->apriLibretto($this->member, 0.02, Carbon::now());

        expect((float) $libretto->saldo_attuale)->toBe(0.0)
            ->and($libretto->status)->toBe('attivo');
    });

    it('il progressivo aumenta per più libretti aperti nello stesso anno', function () {
        $memberType = MemberType::firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $member2 = Member::create([
            'member_type_id'  => $memberType->id,
            'nome'            => 'Carlo',
            'cognome'         => 'Neri',
            'email'           => 'carlo.neri@test.it',
            'numero_tessera'  => 2,
            'data_iscrizione' => '2024-01-01',
            'stato'           => 'attivo',
            'tipo_persona'    => 'fisica',
            'numero_quote_capitale' => 0,
        ]);

        $anno  = now()->year;
        $lib1  = $this->service->apriLibretto($this->member, 0.02, Carbon::now());
        $lib2  = $this->service->apriLibretto($member2, 0.03, Carbon::now());

        $num1 = (int) substr($lib1->numero_libretto, strlen("PS-{$anno}-"));
        $num2 = (int) substr($lib2->numero_libretto, strlen("PS-{$anno}-"));

        expect($num2)->toBe($num1 + 1);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// deposita
// ─────────────────────────────────────────────────────────────────────────────

describe('PrestitoSocialeService → deposita', function () {

    beforeEach(function () {
        $this->libretto = $this->service->apriLibretto($this->member, 0.02, Carbon::parse('2025-01-01'));
    });

    it('aumenta il saldo del libretto dell\'importo depositato', function () {
        $this->service->deposita($this->libretto, 1000.0, Carbon::parse('2025-01-10'));
        $this->libretto->refresh();

        expect((float) $this->libretto->saldo_attuale)->toBe(1000.0);
    });

    it('crea un movimento di tipo deposito con segno avere', function () {
        $mov = $this->service->deposita($this->libretto, 500.0, Carbon::parse('2025-01-10'));

        expect($mov->tipo)->toBe(PrestitoSocialeMovimento::TIPO_DEPOSITO)
            ->and($mov->segno)->toBe(PrestitoSocialeMovimento::SEGNO_AVERE)
            ->and((float) $mov->saldo_dopo)->toBe(500.0);
    });

    it('crea un Incasso di tipo prestito_sociale', function () {
        $this->service->deposita($this->libretto, 750.0, Carbon::parse('2025-01-10'));

        $incasso = Incasso::where('member_id', $this->member->id)
            ->where('type', Incasso::TYPE_PRESTITO_SOCIALE)
            ->first();

        expect($incasso)->not->toBeNull()
            ->and((float) $incasso->amount)->toBe(750.0);
    });

    it('lancia RuntimeException se il libretto non è attivo', function () {
        $this->libretto->update(['status' => 'chiuso']);

        expect(fn () => $this->service->deposita($this->libretto, 100.0, Carbon::now()))
            ->toThrow(RuntimeException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// preleva
// ─────────────────────────────────────────────────────────────────────────────

describe('PrestitoSocialeService → preleva', function () {

    beforeEach(function () {
        $this->libretto = $this->service->apriLibretto($this->member, 0.02, Carbon::parse('2025-01-01'));
        $this->service->deposita($this->libretto, 3000.0, Carbon::parse('2025-01-05'));
        $this->libretto->refresh();
    });

    it('riduce il saldo del libretto dell\'importo prelevato', function () {
        $this->service->preleva($this->libretto, 1000.0, Carbon::parse('2025-02-01'));
        $this->libretto->refresh();

        expect((float) $this->libretto->saldo_attuale)->toBe(2000.0);
    });

    it('crea un movimento di tipo prelievo con segno dare', function () {
        $mov = $this->service->preleva($this->libretto, 500.0, Carbon::parse('2025-02-01'));

        expect($mov->tipo)->toBe(PrestitoSocialeMovimento::TIPO_PRELIEVO)
            ->and($mov->segno)->toBe(PrestitoSocialeMovimento::SEGNO_DARE)
            ->and((float) $mov->saldo_dopo)->toBe(2500.0);
    });

    it('lancia ValidationException se il saldo è insufficiente', function () {
        expect(fn () => $this->service->preleva($this->libretto, 5000.0, Carbon::parse('2025-02-01')))
            ->toThrow(ValidationException::class);
    });

    it('lancia ValidationException se prelievo > 5000 senza prenotazione', function () {
        // Prima porta il saldo a 10.000
        $this->service->deposita($this->libretto, 7000.0, Carbon::parse('2025-01-06'));
        $this->libretto->refresh();

        expect(fn () => $this->service->preleva(
            lib:       $this->libretto,
            importo:   6000.0,
            data:      Carbon::parse('2025-02-01'),
            prenotato: false,
        ))->toThrow(ValidationException::class);
    });

    it('prelievo > 5000 con prenotazione va a buon fine', function () {
        $this->service->deposita($this->libretto, 7000.0, Carbon::parse('2025-01-06'));
        $this->libretto->refresh();

        $mov = $this->service->preleva(
            lib:       $this->libretto,
            importo:   6000.0,
            data:      Carbon::parse('2025-02-01'),
            prenotato: true,
        );

        expect($mov->tipo)->toBe(PrestitoSocialeMovimento::TIPO_PRELIEVO);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// calcolaInteressi
// ─────────────────────────────────────────────────────────────────────────────

describe('PrestitoSocialeService → calcolaInteressi', function () {

    beforeEach(function () {
        $this->libretto = $this->service->apriLibretto($this->member, 0.12, Carbon::parse('2025-01-01'));
        // Deposita 12.000: interessi mensili lordi = 12000 × 12% / 12 = 120
        $this->service->deposita($this->libretto, 12000.0, Carbon::parse('2025-01-01'));
        $this->libretto->refresh();
    });

    it('calcola interessi lordi e ritenuta 26%', function () {
        $risultati = $this->service->calcolaInteressi(2025, 1);

        expect($risultati)->toHaveCount(1);

        $r = $risultati->first();
        expect($r['interessi_lordi'])->toBe(120.0)
            ->and($r['ritenuta'])->toBe(round(120.0 * 0.26, 2))
            ->and($r['interessi_netti'])->toBe(round(120.0 * 0.74, 2));
    });

    it('crea movimenti interessi e ritenuta_fiscale sul libretto', function () {
        $this->service->calcolaInteressi(2025, 1);

        $movInteressi = PrestitoSocialeMovimento::where('libretto_id', $this->libretto->id)
            ->where('tipo', PrestitoSocialeMovimento::TIPO_INTERESSI)
            ->first();

        $movRitenuta = PrestitoSocialeMovimento::where('libretto_id', $this->libretto->id)
            ->where('tipo', PrestitoSocialeMovimento::TIPO_RITENUTA_FISCALE)
            ->first();

        expect($movInteressi)->not->toBeNull()
            ->and($movRitenuta)->not->toBeNull();
    });

    it('è idempotente: non ricalcola interessi già esistenti per lo stesso mese', function () {
        $this->service->calcolaInteressi(2025, 1);
        $risultati = $this->service->calcolaInteressi(2025, 1);

        // Seconda chiamata non produce risultati
        expect($risultati)->toHaveCount(0);

        // Un solo movimento interessi in DB
        $count = PrestitoSocialeMovimento::where('libretto_id', $this->libretto->id)
            ->where('tipo', PrestitoSocialeMovimento::TIPO_INTERESSI)
            ->where('anno_competenza', 2025)
            ->where('mese_competenza', 1)
            ->count();

        expect($count)->toBe(1);
    });

});
