<?php

/**
 * Test suite per Ristorno, RistornoEntry e RistorniController.
 *
 * Copre:
 *  - RistornoEntry::calcolaFromLordo (ritenuta, netto)
 *  - Ristorno model: accessor totale_ritenuta, totale_netto, scopes
 *  - RistorniController::store (creazione con entries, validazione somma, soci duplicati)
 *  - RistorniController::markPagato (status, PrimaNotaEntry negativa)
 *  - RistorniController::annulla (deliberato → annullato, pagato → errore)
 */

use App\Models\Conto;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\PrimaNotaEntry;
use App\Models\Ristorno;
use App\Models\RistornoEntry;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Ristorni Test',
        'slug'              => 'coop-ristorni-test-' . uniqid(),
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    // Conto per markPagato
    $this->conto = Conto::create([
        'name'   => 'Cassa',
        'code'   => 'CASSA',
        'type'   => 'bank',
        'ordine' => 1,
        'attivo' => true,
    ]);

    // Tipo socio obbligatorio
    $memberType = MemberType::firstOrCreate(
        ['name' => 'socio'],
        ['display_name' => 'Socio'],
    );

    // Soci
    $this->socio1 = Member::create([
        'member_type_id'  => $memberType->id,
        'nome'            => 'Luigi',
        'cognome'         => 'Ferrari',
        'email'           => 'luigi@test.it',
        'numero_tessera'  => 1,
        'data_iscrizione' => '2024-01-01',
        'stato'           => 'attivo',
        'tipo_persona'    => 'fisica',
        'numero_quote_capitale' => 0,
    ]);

    $this->socio2 = Member::create([
        'member_type_id'  => $memberType->id,
        'nome'            => 'Maria',
        'cognome'         => 'Conti',
        'email'           => 'maria@test.it',
        'numero_tessera'  => 2,
        'data_iscrizione' => '2024-01-01',
        'stato'           => 'attivo',
        'tipo_persona'    => 'fisica',
        'numero_quote_capitale' => 0,
    ]);

    // Utente admin per i test HTTP
    (new RoleSeeder)->run();
    $roleAdmin = Role::where('name', 'admin')->first();

    $this->user = User::factory()->create([
        'email'             => 'admin@test.it',
        'email_verified_at' => now(),
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// RistornoEntry → calcolaFromLordo
// ─────────────────────────────────────────────────────────────────────────────

describe('RistornoEntry → calcolaFromLordo', function () {

    it('calcola ritenuta 30% correttamente', function () {
        $result = RistornoEntry::calcolaFromLordo(1000.0, 0.30);

        expect($result['importo_ritenuta'])->toBe(300.0)
            ->and($result['importo_netto'])->toBe(700.0)
            ->and($result['importo_lordo'])->toBe(1000.0);
    });

    it('calcola ritenuta con aliquota personalizzata', function () {
        $result = RistornoEntry::calcolaFromLordo(500.0, 0.20);

        expect($result['importo_ritenuta'])->toBe(100.0)
            ->and($result['importo_netto'])->toBe(400.0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// Ristorno model — accessor e scopes
// ─────────────────────────────────────────────────────────────────────────────

describe('Ristorno model → accessor e scopes', function () {

    beforeEach(function () {
        $this->ristorno = Ristorno::create([
            'anno'                      => 2024,
            'importo_totale_deliberato' => 1000.0,
            'aliquota_ritenuta'         => 0.30,
            'data_delibera_assemblea'   => '2025-04-01',
            'status'                    => Ristorno::STATUS_DELIBERATO,
        ]);

        foreach ([[$this->socio1->id, 600.0], [$this->socio2->id, 400.0]] as [$mid, $lordo]) {
            $calc = RistornoEntry::calcolaFromLordo($lordo, 0.30);
            RistornoEntry::create(array_merge($calc, [
                'ristorno_id' => $this->ristorno->id,
                'member_id'   => $mid,
                'status'      => RistornoEntry::STATUS_DELIBERATO,
            ]));
        }
        $this->ristorno->load('entries');
    });

    it('getTotaleRitenutaAttribute somma le ritenute delle entries', function () {
        // 600×0.30 = 180  +  400×0.30 = 120  →  300
        expect($this->ristorno->totale_ritenuta)->toBe(300.0);
    });

    it('getTotaleNettoAttribute somma i netti delle entries', function () {
        // 420 + 280 = 700
        expect($this->ristorno->totale_netto)->toBe(700.0);
    });

    it('scope deliberati restituisce solo ristorni in stato deliberato', function () {
        $deliberati = Ristorno::deliberati()->get();
        expect($deliberati->count())->toBe(1);
    });

    it('scope perAnno filtra per anno', function () {
        Ristorno::create([
            'anno'                      => 2023,
            'importo_totale_deliberato' => 500.0,
            'aliquota_ritenuta'         => 0.30,
            'data_delibera_assemblea'   => '2024-04-01',
            'status'                    => Ristorno::STATUS_DELIBERATO,
        ]);

        $ristorni2024 = Ristorno::perAnno(2024)->get();
        expect($ristorni2024->count())->toBe(1)
            ->and($ristorni2024->first()->anno)->toBe(2024);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// RistorniController → store (HTTP)
// ─────────────────────────────────────────────────────────────────────────────

describe('RistorniController → store', function () {

    it('crea un ristorno con entries e status deliberato', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ristorni.store', $this->tenant), [
                'anno'                      => 2024,
                'importo_totale_deliberato' => 1000.00,
                'aliquota_ritenuta'         => 0.30,
                'data_delibera_assemblea'   => '2025-04-01',
                'entries'                   => [
                    ['member_id' => $this->socio1->id, 'importo_lordo' => 600.00],
                    ['member_id' => $this->socio2->id, 'importo_lordo' => 400.00],
                ],
            ]);

        $response->assertRedirect();

        $ristorno = Ristorno::first();
        expect($ristorno)->not->toBeNull()
            ->and($ristorno->status)->toBe(Ristorno::STATUS_DELIBERATO)
            ->and($ristorno->entries()->count())->toBe(2);
    });

    it('non crea il ristorno se la somma delle entries non coincide col totale', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ristorni.store', $this->tenant), [
                'anno'                      => 2024,
                'importo_totale_deliberato' => 1000.00,
                'aliquota_ritenuta'         => 0.30,
                'data_delibera_assemblea'   => '2025-04-01',
                'entries'                   => [
                    ['member_id' => $this->socio1->id, 'importo_lordo' => 500.00], // 500 ≠ 1000
                ],
            ]);

        // Il ristorno non deve essere creato se la somma non corrisponde
        expect(Ristorno::count())->toBe(0);
    });

    it('non crea il ristorno se lo stesso socio compare due volte', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ristorni.store', $this->tenant), [
                'anno'                      => 2024,
                'importo_totale_deliberato' => 1000.00,
                'aliquota_ritenuta'         => 0.30,
                'data_delibera_assemblea'   => '2025-04-01',
                'entries'                   => [
                    ['member_id' => $this->socio1->id, 'importo_lordo' => 600.00],
                    ['member_id' => $this->socio1->id, 'importo_lordo' => 400.00], // duplicato
                ],
            ]);

        // Il ristorno non deve essere creato se ci sono soci duplicati
        expect(Ristorno::count())->toBe(0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// RistorniController → markPagato e annulla (HTTP)
// ─────────────────────────────────────────────────────────────────────────────

describe('RistorniController → markPagato e annulla', function () {

    beforeEach(function () {
        $this->ristorno = Ristorno::create([
            'anno'                      => 2024,
            'importo_totale_deliberato' => 800.0,
            'aliquota_ritenuta'         => 0.30,
            'data_delibera_assemblea'   => '2025-04-01',
            'status'                    => Ristorno::STATUS_DELIBERATO,
        ]);

        foreach ([[$this->socio1->id, 500.0], [$this->socio2->id, 300.0]] as [$mid, $lordo]) {
            $calc = RistornoEntry::calcolaFromLordo($lordo, 0.30);
            RistornoEntry::create(array_merge($calc, [
                'ristorno_id' => $this->ristorno->id,
                'member_id'   => $mid,
                'status'      => RistornoEntry::STATUS_DELIBERATO,
            ]));
        }
    });

    it('markPagato porta il ristorno a status pagato', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ristorni.mark-pagato', [$this->tenant, $this->ristorno]), [
                'data_pagamento' => '2025-05-01',
                'conto_id'       => $this->conto->id,
            ]);

        $response->assertRedirect();

        $this->ristorno->refresh();
        expect($this->ristorno->status)->toBe(Ristorno::STATUS_PAGATO)
            ->and($this->ristorno->data_pagamento->format('Y-m-d'))->toBe('2025-05-01');
    });

    it('markPagato crea una PrimaNotaEntry negativa (uscita)', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ristorni.mark-pagato', [$this->tenant, $this->ristorno]), [
                'data_pagamento' => '2025-05-01',
                'conto_id'       => $this->conto->id,
            ]);

        $entry = PrimaNotaEntry::where('conto_id', $this->conto->id)
            ->where('rendiconto_code', 'B07')
            ->first();

        expect($entry)->not->toBeNull()
            ->and((float) $entry->amount)->toBeLessThan(0);
    });

    it('annulla porta il ristorno deliberato a status annullato', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ristorni.annulla', [$this->tenant, $this->ristorno]));

        $response->assertRedirect();

        $this->ristorno->refresh();
        expect($this->ristorno->status)->toBe(Ristorno::STATUS_ANNULLATO);
    });

    it('non può annullare un ristorno già pagato', function () {
        $this->ristorno->update(['status' => Ristorno::STATUS_PAGATO]);

        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('ristorni.annulla', [$this->tenant, $this->ristorno]));

        // Viene reindirizzato con messaggio di errore
        $response->assertRedirect();
        $this->ristorno->refresh();
        expect($this->ristorno->status)->toBe(Ristorno::STATUS_PAGATO); // status invariato
    });

});
