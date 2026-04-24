<?php

/**
 * Integration test: Flusso Fine Anno Cooperativa.
 *
 * Copre:
 *  - Sottoscrizione + versamento quote → getSituazioneCapitale
 *  - Prestito sociale: apri → deposita → calcola interessi → preleva
 *  - Ristorno: crea entries → markPagato → verifica PrimaNotaEntry
 *  - Riscatto quote: flow completo con Spesa di rimborso
 *  - Sequenza integrata multi-socio (quota + prestito + ristorno in un esercizio)
 */

use App\Models\Conto;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\PrimaNotaEntry;
use App\Models\Ristorno;
use App\Models\RistornoEntry;
use App\Models\Role;
use App\Models\Spesa;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CapitaleSocialeService;
use App\Services\PrestitoSocialeService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Year End Flow',
        'slug'              => 'coop-yearend-' . uniqid(),
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    // Conto di tesoreria
    $this->conto = Conto::create([
        'name'   => 'Banca',
        'code'   => 'BANCA',
        'type'   => 'bank',
        'ordine' => 1,
        'attivo' => true,
    ]);

    $this->capitaleSvc  = app(CapitaleSocialeService::class);
    $this->prestitoSvc  = app(PrestitoSocialeService::class);

    $memberType = MemberType::firstOrCreate(
        ['name' => 'socio'],
        ['display_name' => 'Socio'],
    );

    // Due soci
    $this->socio1 = Member::create([
        'member_type_id'        => $memberType->id,
        'nome'                  => 'Marco',
        'cognome'               => 'Verdi',
        'email'                 => 'marco@coop.it',
        'numero_tessera'        => 1,
        'data_iscrizione'       => '2024-01-01',
        'stato'                 => 'attivo',
        'tipo_persona'          => 'fisica',
        'numero_quote_capitale' => 0,
    ]);

    $this->socio2 = Member::create([
        'member_type_id'        => $memberType->id,
        'nome'                  => 'Sara',
        'cognome'               => 'Neri',
        'email'                 => 'sara@coop.it',
        'numero_tessera'        => 2,
        'data_iscrizione'       => '2024-01-01',
        'stato'                 => 'attivo',
        'tipo_persona'          => 'fisica',
        'numero_quote_capitale' => 0,
    ]);

    // Admin per i test HTTP
    $roleAdmin = Role::where('name', 'admin')->first();
    $this->user = User::factory()->create([
        'email'             => 'admin@coop.it',
        'email_verified_at' => now(),
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// Flusso Capitale Sociale
// ─────────────────────────────────────────────────────────────────────────────

describe('Flusso Capitale Sociale', function () {

    it('sottoscrivi → versa → getSituazioneCapitale riflette i valori corretti', function () {
        // Socio1: 10 quote da €50 = 500 sottoscritto, 300 versato
        $share1 = $this->capitaleSvc->sottoscriviQuote(
            member:         $this->socio1,
            numeroQuote:    10,
            valoreUnitario: 50.0,
            data:           Carbon::parse('2025-01-15'),
        );
        $this->capitaleSvc->versaQuote($share1, 300.0, Carbon::parse('2025-02-01'));

        // Socio2: 5 quote da €50 = 250 sottoscritto, 250 versato (completo)
        $share2 = $this->capitaleSvc->sottoscriviQuote(
            member:         $this->socio2,
            numeroQuote:    5,
            valoreUnitario: 50.0,
            data:           Carbon::parse('2025-01-20'),
        );
        $this->capitaleSvc->versaQuote($share2, 250.0, Carbon::parse('2025-02-05'));

        $situazione = $this->capitaleSvc->getSituazioneCapitale();

        expect($situazione['totale_sottoscritto'])->toBe(750.0)
            ->and($situazione['totale_versato'])->toBe(550.0)
            ->and($situazione['capitale_da_versare'])->toBe(200.0)
            ->and($situazione['numero_soci_con_quote'])->toBe(2);
    });

    it('riscatto quote: spesa rimborso + rimozione da situazione capitale', function () {
        $share = $this->capitaleSvc->sottoscriviQuote(
            member:         $this->socio1,
            numeroQuote:    4,
            valoreUnitario: 100.0,
            data:           Carbon::parse('2025-01-10'),
        );
        $this->capitaleSvc->versaQuote($share, 400.0, Carbon::parse('2025-02-01'));
        $share->refresh();

        $this->capitaleSvc->riscattaQuote(
            share:  $share,
            motivo: 'Recesso volontario',
            data:   Carbon::parse('2025-06-30'),
        );

        // Spesa di rimborso creata
        $spesa = Spesa::where('conto_id', $this->conto->id)
            ->where('amount', 400.0)
            ->first();
        expect($spesa)->not->toBeNull();

        // Situazione: nessun socio attivo con quote
        $situazione = $this->capitaleSvc->getSituazioneCapitale();
        expect($situazione['numero_soci_con_quote'])->toBe(0)
            ->and($situazione['totale_sottoscritto'])->toBe(0.0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Flusso Prestito Sociale
// ─────────────────────────────────────────────────────────────────────────────

describe('Flusso Prestito Sociale', function () {

    it('apri → deposita → calcola interessi → preleva: saldo corretto', function () {
        // Apertura libretto con tasso 12% annuo
        $libretto = $this->prestitoSvc->apriLibretto(
            member:     $this->socio1,
            tassoAnnuo: 0.12,
            data:       Carbon::parse('2025-01-01'),
        );

        // Deposita 6.000
        $this->prestitoSvc->deposita($libretto, 6000.0, Carbon::parse('2025-01-01'));
        $libretto->refresh();
        expect((float) $libretto->saldo_attuale)->toBe(6000.0);

        // Calcola interessi per gennaio 2025: 6000 × 12% / 12 = 60 lordi
        $risultati = $this->prestitoSvc->calcolaInteressi(2025, 1);
        expect($risultati)->toHaveCount(1);

        $r = $risultati->first();
        expect($r['interessi_lordi'])->toBe(60.0)
            ->and($r['ritenuta'])->toBe(round(60.0 * 0.26, 2));

        // Preleva 2.000
        $this->prestitoSvc->preleva($libretto, 2000.0, Carbon::parse('2025-02-01'));
        $libretto->refresh();
        expect((float) $libretto->saldo_attuale)->toBe(4000.0);
    });

    it('calcolo interessi è idempotente nel flusso reale', function () {
        $libretto = $this->prestitoSvc->apriLibretto(
            member:     $this->socio2,
            tassoAnnuo: 0.06,
            data:       Carbon::parse('2025-01-01'),
        );
        $this->prestitoSvc->deposita($libretto, 12000.0, Carbon::parse('2025-01-01'));

        // Prima chiamata
        $this->prestitoSvc->calcolaInteressi(2025, 1);
        // Seconda chiamata sullo stesso mese → nessun risultato
        $risultati = $this->prestitoSvc->calcolaInteressi(2025, 1);
        expect($risultati)->toHaveCount(0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Flusso Ristorno
// ─────────────────────────────────────────────────────────────────────────────

describe('Flusso Ristorno', function () {

    it('delibera → markPagato: status e PrimaNotaEntry negativa', function () {
        // Crea ristorno deliberato con entries
        $ristorno = Ristorno::create([
            'anno'                      => 2024,
            'importo_totale_deliberato' => 1200.0,
            'aliquota_ritenuta'         => 0.30,
            'data_delibera_assemblea'   => '2025-04-01',
            'status'                    => Ristorno::STATUS_DELIBERATO,
        ]);

        foreach ([[$this->socio1->id, 800.0], [$this->socio2->id, 400.0]] as [$mid, $lordo]) {
            $calc = RistornoEntry::calcolaFromLordo($lordo, 0.30);
            RistornoEntry::create(array_merge($calc, [
                'ristorno_id' => $ristorno->id,
                'member_id'   => $mid,
                'status'      => RistornoEntry::STATUS_DELIBERATO,
            ]));
        }

        // markPagato via HTTP
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ristorni.mark-pagato', [$this->tenant, $ristorno]), [
                'data_pagamento' => '2025-05-01',
                'conto_id'       => $this->conto->id,
            ]);

        $ristorno->refresh();
        expect($ristorno->status)->toBe(Ristorno::STATUS_PAGATO);

        // PrimaNotaEntry negativa (uscita) creata
        $entry = PrimaNotaEntry::where('conto_id', $this->conto->id)
            ->where('rendiconto_code', 'B07')
            ->first();
        expect($entry)->not->toBeNull()
            ->and((float) $entry->amount)->toBeLessThan(0);
    });

    it('accessor totale_ritenuta e totale_netto su ristorno con entries', function () {
        $ristorno = Ristorno::create([
            'anno'                      => 2024,
            'importo_totale_deliberato' => 500.0,
            'aliquota_ritenuta'         => 0.20,
            'data_delibera_assemblea'   => '2025-04-01',
            'status'                    => Ristorno::STATUS_DELIBERATO,
        ]);

        $calc = RistornoEntry::calcolaFromLordo(500.0, 0.20);
        RistornoEntry::create(array_merge($calc, [
            'ristorno_id' => $ristorno->id,
            'member_id'   => $this->socio1->id,
            'status'      => RistornoEntry::STATUS_DELIBERATO,
        ]));
        $ristorno->load('entries');

        // 500 × 20% = 100 ritenuta, 400 netto
        expect($ristorno->totale_ritenuta)->toBe(100.0)
            ->and($ristorno->totale_netto)->toBe(400.0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Sequenza integrata multi-socio
// ─────────────────────────────────────────────────────────────────────────────

describe('Sequenza integrata: quota + prestito + ristorno in un esercizio', function () {

    it('esercizio completo: sottoscrivi, presta, distribuisci ristorno', function () {
        // ① Quote
        $share1 = $this->capitaleSvc->sottoscriviQuote($this->socio1, 6, 50.0, Carbon::parse('2025-01-01'));
        $share2 = $this->capitaleSvc->sottoscriviQuote($this->socio2, 4, 50.0, Carbon::parse('2025-01-01'));
        $this->capitaleSvc->versaQuote($share1, 300.0, Carbon::parse('2025-02-01'));
        $this->capitaleSvc->versaQuote($share2, 200.0, Carbon::parse('2025-02-01'));

        // ② Prestito sociale
        $libretto1 = $this->prestitoSvc->apriLibretto($this->socio1, 0.03, Carbon::parse('2025-01-10'));
        $this->prestitoSvc->deposita($libretto1, 5000.0, Carbon::parse('2025-01-10'));
        $libretto2 = $this->prestitoSvc->apriLibretto($this->socio2, 0.03, Carbon::parse('2025-01-10'));
        $this->prestitoSvc->deposita($libretto2, 3000.0, Carbon::parse('2025-01-10'));

        // ③ Interessi fine mese
        $interessi = $this->prestitoSvc->calcolaInteressi(2025, 1);
        expect($interessi)->toHaveCount(2); // un risultato per ciascun libretto

        // ④ Ristorno fine anno
        $totale = 1000.0;
        $ristorno = Ristorno::create([
            'anno'                      => 2024,
            'importo_totale_deliberato' => $totale,
            'aliquota_ritenuta'         => 0.30,
            'data_delibera_assemblea'   => '2025-04-30',
            'status'                    => Ristorno::STATUS_DELIBERATO,
        ]);

        foreach ([[$this->socio1->id, 600.0], [$this->socio2->id, 400.0]] as [$mid, $lordo]) {
            $calc = RistornoEntry::calcolaFromLordo($lordo, 0.30);
            RistornoEntry::create(array_merge($calc, [
                'ristorno_id' => $ristorno->id,
                'member_id'   => $mid,
                'status'      => RistornoEntry::STATUS_DELIBERATO,
            ]));
        }
        $ristorno->load('entries');

        // Verifica situazione finale
        $situazione = $this->capitaleSvc->getSituazioneCapitale();
        expect($situazione['numero_soci_con_quote'])->toBe(2)
            ->and($situazione['totale_versato'])->toBe(500.0);

        // Ristorno correttamente calcolato
        expect($ristorno->totale_ritenuta)->toBe(300.0)
            ->and($ristorno->totale_netto)->toBe(700.0);
    });

});
