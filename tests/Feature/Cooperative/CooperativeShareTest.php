<?php

/**
 * Test suite per CapitaleSocialeService e il modello CooperativeShare.
 *
 * Copre:
 *  - Sottoscrizione quote (crea share, incasso, prima nota, aggiorna membro)
 *  - Versamento quote (parziale → versata, eccesso → eccezione)
 *  - Riscatto quote (status, spesa rimborso, azzeramento quote socio)
 *  - getSituazioneCapitale (aggregati, esclusione riscattate)
 *  - Accessor ancora_da_versare e percentuale_versamento
 */

use App\Models\Conto;
use App\Models\CooperativeShare;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\PrimaNotaEntry;
use App\Models\Spesa;
use App\Models\Tenant;
use App\Services\CapitaleSocialeService;
use Carbon\Carbon;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Share Test',
        'slug'              => 'coop-share-test-' . uniqid(),
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    // Conto di tesoreria necessario per CapitaleSocialeService
    $this->conto = Conto::create([
        'name'   => 'Cassa',
        'code'   => 'CASSA',
        'type'   => 'bank',
        'ordine' => 1,
        'attivo' => true,
    ]);

    $this->service = app(CapitaleSocialeService::class);

    // Tipo socio obbligatorio
    $this->memberType = MemberType::firstOrCreate(
        ['name' => 'socio'],
        ['display_name' => 'Socio'],
    );

    // Socio di test
    $this->member = Member::create([
        'member_type_id'      => $this->memberType->id,
        'nome'                => 'Mario',
        'cognome'             => 'Rossi',
        'email'               => 'mario.rossi@test.it',
        'numero_tessera'      => 1,
        'data_iscrizione'     => '2024-01-01',
        'stato'               => 'attivo',
        'tipo_persona'        => 'fisica',
        'numero_quote_capitale' => 0,
    ]);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// sottoscriviQuote
// ─────────────────────────────────────────────────────────────────────────────

describe('CapitaleSocialeService → sottoscriviQuote', function () {

    it('crea un CooperativeShare con status sottoscritta', function () {
        $share = $this->service->sottoscriviQuote(
            member:         $this->member,
            numeroQuote:    10,
            valoreUnitario: 50.0,
            data:           Carbon::parse('2025-01-15'),
        );

        expect($share)->toBeInstanceOf(CooperativeShare::class)
            ->and($share->status)->toBe('sottoscritta')
            ->and($share->member_id)->toBe($this->member->id)
            ->and((int) $share->numero_quote)->toBe(10);
    });

    it('calcola totale_sottoscritto = numero_quote × valore_unitario', function () {
        $share = $this->service->sottoscriviQuote(
            member:         $this->member,
            numeroQuote:    5,
            valoreUnitario: 100.0,
            data:           Carbon::parse('2025-01-15'),
        );

        expect((float) $share->totale_sottoscritto)->toBe(500.0)
            ->and((float) $share->totale_versato)->toBe(0.0);
    });

    it('crea un Incasso di tipo capitale', function () {
        $this->service->sottoscriviQuote(
            member:         $this->member,
            numeroQuote:    3,
            valoreUnitario: 50.0,
            data:           Carbon::parse('2025-01-15'),
        );

        $incasso = Incasso::where('member_id', $this->member->id)
            ->where('type', Incasso::TYPE_CAPITALE)
            ->first();

        expect($incasso)->not->toBeNull()
            ->and((float) $incasso->amount)->toBe(150.0);
    });

    it('crea una PrimaNotaEntry con rendiconto_code A01', function () {
        $this->service->sottoscriviQuote(
            member:         $this->member,
            numeroQuote:    4,
            valoreUnitario: 25.0,
            data:           Carbon::parse('2025-01-15'),
        );

        $entry = PrimaNotaEntry::where('conto_id', $this->conto->id)
            ->where('rendiconto_code', 'A01')
            ->first();

        expect($entry)->not->toBeNull()
            ->and((float) $entry->amount)->toBe(100.0);
    });

    it('aggiorna numero_quote_capitale sul Member', function () {
        $this->service->sottoscriviQuote(
            member:         $this->member,
            numeroQuote:    7,
            valoreUnitario: 50.0,
            data:           Carbon::parse('2025-01-15'),
        );

        $this->member->refresh();

        expect($this->member->numero_quote_capitale)->toBe(7);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// versaQuote
// ─────────────────────────────────────────────────────────────────────────────

describe('CapitaleSocialeService → versaQuote', function () {

    beforeEach(function () {
        $this->share = $this->service->sottoscriviQuote(
            member:         $this->member,
            numeroQuote:    10,
            valoreUnitario: 50.0,
            data:           Carbon::parse('2025-01-15'),
        );
    });

    it('versamento parziale imposta status parzialmente_versata', function () {
        $updated = $this->service->versaQuote(
            share:          $this->share,
            importoVersato: 200.0,
            data:           Carbon::parse('2025-02-01'),
        );

        expect($updated->status)->toBe('parzialmente_versata')
            ->and((float) $updated->totale_versato)->toBe(200.0);
    });

    it('versamento completo imposta status versata', function () {
        $updated = $this->service->versaQuote(
            share:          $this->share,
            importoVersato: 500.0,
            data:           Carbon::parse('2025-02-01'),
        );

        expect($updated->status)->toBe('versata')
            ->and((float) $updated->totale_versato)->toBe(500.0);
    });

    it('versamento in due rate porta a status versata', function () {
        $this->service->versaQuote($this->share, 300.0, Carbon::parse('2025-02-01'));
        $this->share->refresh();
        $updated = $this->service->versaQuote($this->share, 200.0, Carbon::parse('2025-03-01'));

        expect($updated->status)->toBe('versata')
            ->and((float) $updated->totale_versato)->toBe(500.0);
    });

    it('lancia RuntimeException se importo eccede il totale sottoscritto', function () {
        expect(fn () => $this->service->versaQuote(
            share:          $this->share,
            importoVersato: 600.0, // 500 sottoscritto → eccede
            data:           Carbon::parse('2025-02-01'),
        ))->toThrow(RuntimeException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// riscattaQuote
// ─────────────────────────────────────────────────────────────────────────────

describe('CapitaleSocialeService → riscattaQuote', function () {

    beforeEach(function () {
        $this->share = $this->service->sottoscriviQuote(
            member:         $this->member,
            numeroQuote:    4,
            valoreUnitario: 50.0,
            data:           Carbon::parse('2025-01-15'),
        );
        // Versa il totale prima di riscattare
        $this->service->versaQuote($this->share, 200.0, Carbon::parse('2025-02-01'));
        $this->share->refresh();
    });

    it('imposta status riscattata con data e motivo', function () {
        $riscattata = $this->service->riscattaQuote(
            share:  $this->share,
            motivo: 'Recesso volontario',
            data:   Carbon::parse('2025-06-01'),
        );

        expect($riscattata->status)->toBe('riscattata')
            ->and($riscattata->motivo_riscatto)->toBe('Recesso volontario')
            ->and($riscattata->data_riscatto->format('Y-m-d'))->toBe('2025-06-01');
    });

    it('crea una Spesa per il rimborso del capitale versato', function () {
        $this->service->riscattaQuote(
            share:  $this->share,
            motivo: 'Recesso',
            data:   Carbon::parse('2025-06-01'),
        );

        $spesa = Spesa::where('conto_id', $this->conto->id)
            ->where('amount', 200.0)
            ->first();

        expect($spesa)->not->toBeNull();
    });

    it('azzera numero_quote_capitale sul Member', function () {
        $this->service->riscattaQuote(
            share:  $this->share,
            motivo: 'Recesso',
            data:   Carbon::parse('2025-06-01'),
        );

        $this->member->refresh();

        expect($this->member->numero_quote_capitale)->toBe(0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// getSituazioneCapitale
// ─────────────────────────────────────────────────────────────────────────────

describe('CapitaleSocialeService → getSituazioneCapitale', function () {

    it('aggrega totale_sottoscritto, totale_versato e capitale_da_versare', function () {
        $membro2 = Member::create([
            'member_type_id'  => $this->memberType->id,
            'nome'            => 'Lucia',
            'cognome'         => 'Bianchi',
            'email'           => 'lucia@test.it',
            'numero_tessera'  => 2,
            'data_iscrizione' => '2024-01-01',
            'stato'           => 'attivo',
            'tipo_persona'    => 'fisica',
            'numero_quote_capitale' => 0,
        ]);

        $this->service->sottoscriviQuote($this->member, 5, 100.0, Carbon::parse('2025-01-10'));
        $share1 = CooperativeShare::where('member_id', $this->member->id)->first();
        $this->service->versaQuote($share1, 300.0, Carbon::parse('2025-01-20'));

        $this->service->sottoscriviQuote($membro2, 3, 100.0, Carbon::parse('2025-01-10'));

        $situazione = $this->service->getSituazioneCapitale();

        expect($situazione['totale_sottoscritto'])->toBe(800.0)
            ->and($situazione['totale_versato'])->toBe(300.0)
            ->and($situazione['capitale_da_versare'])->toBe(500.0)
            ->and($situazione['numero_soci_con_quote'])->toBe(2);
    });

    it('esclude le quote riscattate dal totale attive', function () {
        $share = $this->service->sottoscriviQuote($this->member, 4, 50.0, Carbon::parse('2025-01-10'));
        $share->refresh();
        $this->service->versaQuote($share, 200.0, Carbon::parse('2025-01-20'));
        $share->refresh();
        $this->service->riscattaQuote($share, 'Recesso', Carbon::parse('2025-06-01'));

        $situazione = $this->service->getSituazioneCapitale();

        expect($situazione['numero_soci_con_quote'])->toBe(0)
            ->and($situazione['totale_sottoscritto'])->toBe(0.0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Accessor del modello
// ─────────────────────────────────────────────────────────────────────────────

describe('CooperativeShare → accessor', function () {

    it('ancora_da_versare calcola la differenza tra sottoscritto e versato', function () {
        $share = CooperativeShare::create([
            'member_id'           => $this->member->id,
            'numero_quote'        => 10,
            'valore_unitario'     => 50.0,
            'totale_sottoscritto' => 500.0,
            'totale_versato'      => 150.0,
            'data_sottoscrizione' => '2025-01-01',
            'status'              => 'parzialmente_versata',
        ]);

        expect($share->ancora_da_versare)->toBe(350.0);
    });

});
