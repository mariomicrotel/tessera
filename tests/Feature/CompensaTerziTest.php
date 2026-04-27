<?php

/**
 * Test suite per Compensi a Terzi / Ritenute d'Acconto (G1).
 *
 * Copre:
 *  - CompensaTerziService::crea  (calcolo ritenuta, stato iniziale)
 *  - CompensaTerziService::aggiorna
 *  - CompensaTerziService::versaRitenute
 *  - CompensaTerziService::riepilogoAnnuale (CU)
 *  - CompensaTerziController: index, create, store, show, edit, update, destroy, versa, riepilogo, versamenti, versamentoShow
 *  - Middleware role:admin,contabile
 */

use App\Models\CompensaTerzi;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VersamentoRitenuta;
use App\Services\CompensaTerziService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Compensi Test',
        'slug'              => 'ets-compensi-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000009999',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    $this->userSenza = User::factory()->create(['email_verified_at' => now()]);
    $this->userSenza->tenants()->attach($this->tenant);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper: dati base compenso
// ─────────────────────────────────────────────────────────────────────────────

function compensoBase(array $override = []): array
{
    return array_merge([
        'nome_percipiente'    => 'Mario Rossi',
        'codice_fiscale'      => 'RSSMRA80A01H501Z',
        'tipo_rapporto'       => 'occasionale',
        'codice_causale'      => 'A',
        'anno_competenza'     => 2026,
        'data_pagamento'      => '2026-01-15',
        'causale_prestazione' => 'Consulenza informatica',
        'compenso_lordo'      => 1000.00,
        'aliquota_ritenuta'   => 20.00,
    ], $override);
}

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziService::crea
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziService::crea', function () {

    it('calcola correttamente ritenuta e netto con aliquota 20%', function () {
        $svc      = app(CompensaTerziService::class);
        $compenso = $svc->crea($this->tenant, compensoBase());

        expect($compenso->compenso_lordo)->toBe(1000.0);
        expect($compenso->ritenuta)->toBe(200.0);        // 1000 × 20% = 200
        expect($compenso->compenso_netto)->toBe(800.0);  // 1000 − 200 = 800
        expect($compenso->stato_ritenuta)->toBe(CompensaTerzi::STATO_DA_VERSARE);
        expect($compenso->tenant_id)->toBe($this->tenant->id);
    });

    it('usa base imponibile diversa dal lordo quando fornita', function () {
        $svc = app(CompensaTerziService::class);

        // Lordo 1000, ma base imponibile solo 800 (es. con rimborsi)
        $compenso = $svc->crea($this->tenant, compensoBase([
            'compenso_lordo'          => 1000.00,
            'base_imponibile_ritenuta' => 800.00,
        ]));

        expect($compenso->ritenuta)->toBe(160.0);       // 800 × 20% = 160
        expect($compenso->compenso_netto)->toBe(840.0); // 1000 − 160 = 840
    });

    it('usa aliquota personalizzata', function () {
        $svc = app(CompensaTerziService::class);

        $compenso = $svc->crea($this->tenant, compensoBase([
            'compenso_lordo'    => 500.00,
            'aliquota_ritenuta' => 30.00,
        ]));

        expect($compenso->ritenuta)->toBe(150.0);       // 500 × 30%
        expect($compenso->compenso_netto)->toBe(350.0); // 500 − 150
    });

    it('lancia eccezione se codice fiscale mancante', function () {
        $svc  = app(CompensaTerziService::class);
        $data = compensoBase(['codice_fiscale' => '']);

        expect(fn () => $svc->crea($this->tenant, $data))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('lancia eccezione se compenso lordo è zero', function () {
        $svc  = app(CompensaTerziService::class);
        $data = compensoBase(['compenso_lordo' => 0]);

        expect(fn () => $svc->crea($this->tenant, $data))
            ->toThrow(\InvalidArgumentException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziService::aggiorna
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziService::aggiorna', function () {

    it('aggiorna importi e ricalcola ritenuta', function () {
        $svc      = app(CompensaTerziService::class);
        $compenso = $svc->crea($this->tenant, compensoBase());

        $updated = $svc->aggiorna($compenso, ['compenso_lordo' => 2000.00]);

        expect($updated->compenso_lordo)->toBe(2000.0);
        expect($updated->ritenuta)->toBe(400.0);
        expect($updated->compenso_netto)->toBe(1600.0);
    });

    it('blocca aggiornamento se ritenuta già versata', function () {
        $svc      = app(CompensaTerziService::class);
        $compenso = $svc->crea($this->tenant, compensoBase());
        $compenso->update(['stato_ritenuta' => CompensaTerzi::STATO_VERSATA]);

        expect(fn () => $svc->aggiorna($compenso->fresh(), ['compenso_lordo' => 500.00]))
            ->toThrow(\InvalidArgumentException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziService::versaRitenute
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziService::versaRitenute', function () {

    it('crea versamento e marca le ritenute come versate', function () {
        $svc = app(CompensaTerziService::class);

        // Creiamo 2 compensi di gennaio 2026
        $c1 = $svc->crea($this->tenant, compensoBase(['compenso_lordo' => 1000.00, 'data_pagamento' => '2026-01-10']));
        $c2 = $svc->crea($this->tenant, compensoBase(['compenso_lordo' => 500.00,  'data_pagamento' => '2026-01-20']));

        $versamento = $svc->versaRitenute($this->tenant, [
            'mese'            => 1,
            'anno'            => 2026,
            'data_versamento' => '2026-02-16',
            'codice_tributo'  => '1040',
        ]);

        expect($versamento->importo_totale)->toBe(300.0); // 200 + 100
        expect($versamento->mese_riferimento)->toBe(1);
        expect($versamento->anno_riferimento)->toBe(2026);

        expect($c1->fresh()->stato_ritenuta)->toBe(CompensaTerzi::STATO_VERSATA);
        expect($c2->fresh()->stato_ritenuta)->toBe(CompensaTerzi::STATO_VERSATA);
        expect($c1->fresh()->versamento_ritenuta_id)->toBe($versamento->id);
    });

    it('non include ritenute di mesi diversi', function () {
        $svc = app(CompensaTerziService::class);

        // Compenso febbraio — non deve essere incluso nel versamento di gennaio
        $svc->crea($this->tenant, compensoBase(['data_pagamento' => '2026-02-10']));

        // Compenso gennaio — deve essere incluso
        $svc->crea($this->tenant, compensoBase(['data_pagamento' => '2026-01-15']));

        $versamento = $svc->versaRitenute($this->tenant, [
            'mese' => 1, 'anno' => 2026, 'data_versamento' => '2026-02-16',
        ]);

        expect($versamento->compensi->count())->toBe(1);
    });

    it('lancia eccezione se non ci sono ritenute da versare nel mese', function () {
        $svc = app(CompensaTerziService::class);

        expect(fn () => $svc->versaRitenute($this->tenant, [
            'mese' => 3, 'anno' => 2026, 'data_versamento' => '2026-04-16',
        ]))->toThrow(\InvalidArgumentException::class);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziService::riepilogoAnnuale
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziService::riepilogoAnnuale', function () {

    it('aggrega correttamente per codice fiscale', function () {
        $svc = app(CompensaTerziService::class);

        // 2 compensi per lo stesso percipiente
        $svc->crea($this->tenant, compensoBase(['compenso_lordo' => 1000.00]));
        $svc->crea($this->tenant, compensoBase(['compenso_lordo' => 500.00]));

        // 1 compenso per un altro percipiente
        $svc->crea($this->tenant, compensoBase([
            'nome_percipiente' => 'Luigi Bianchi',
            'codice_fiscale'   => 'BNCLGU70B01F205X',
            'compenso_lordo'   => 2000.00,
        ]));

        $riepilogo = $svc->riepilogoAnnuale($this->tenant, 2026);

        expect($riepilogo)->toHaveCount(2);

        $rossi = $riepilogo->firstWhere('codice_fiscale', 'RSSMRA80A01H501Z');
        expect($rossi->totale_compenso_lordo)->toBe(1500.0);
        expect($rossi->totale_ritenuta)->toBe(300.0);       // 200 + 100
        expect($rossi->totale_netto)->toBe(1200.0);         // 800 + 400
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziController: Index
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziController::index', function () {

    it('restituisce la pagina index con KPI', function () {
        $svc = app(CompensaTerziService::class);
        $svc->crea($this->tenant, compensoBase());

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('compensi-terzi.index', $this->tenant));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('CompensaTerzi/Index');
        expect($page['props'])->toHaveKey('compensi');
        expect($page['props'])->toHaveKey('kpi');
        expect($page['props'])->toHaveKey('filters');
        expect($page['props'])->toHaveKey('anni');
        expect($page['props'])->toHaveKey('statiLabel');
    });

    it('blocca utente senza ruolo', function () {
        $response = $this->withoutMiddleware([
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
        ])->actingAs($this->userSenza)
            ->get(route('compensi-terzi.index', $this->tenant));

        $response->assertForbidden();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziController: Create / Store
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziController::create', function () {

    it('restituisce la pagina create', function () {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('compensi-terzi.create', $this->tenant));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('CompensaTerzi/Create');
        expect($page['props'])->toHaveKey('tipiRapporto');
        expect($page['props'])->toHaveKey('causali');
    });

});

describe('CompensaTerziController::store', function () {

    it('crea il compenso e redirige al dettaglio', function () {
        $response = $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
        ])
        ->actingAs($this->user)
        ->post(route('compensi-terzi.store', $this->tenant), compensoBase());

        $response->assertRedirect();

        $this->assertDatabaseHas('compensi_terzi', [
            'tenant_id'        => $this->tenant->id,
            'nome_percipiente' => 'Mario Rossi',
            'codice_fiscale'   => 'RSSMRA80A01H501Z',
        ]);
    });

    it('fallisce la validazione senza codice fiscale', function () {
        $data = compensoBase(['codice_fiscale' => '']);

        $response = $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
        ])
        ->actingAs($this->user)
        ->post(route('compensi-terzi.store', $this->tenant), $data);

        $response->assertSessionHasErrors('codice_fiscale');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziController: Show
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziController::show', function () {

    it('mostra il dettaglio del compenso', function () {
        $svc      = app(CompensaTerziService::class);
        $compenso = $svc->crea($this->tenant, compensoBase());

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('compensi-terzi.show', [$this->tenant, $compenso]));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('CompensaTerzi/Show');
        expect($page['props'])->toHaveKey('compenso');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziController: Update
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziController::update', function () {

    it('aggiorna i dati del compenso', function () {
        $svc      = app(CompensaTerziService::class);
        $compenso = $svc->crea($this->tenant, compensoBase());

        $payload = compensoBase(['compenso_lordo' => 2000.00]);

        $response = $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
        ])
        ->actingAs($this->user)
        ->put(route('compensi-terzi.update', [$this->tenant, $compenso]), $payload);

        $response->assertRedirect();

        expect((float) $compenso->fresh()->compenso_lordo)->toBe(2000.0);
        expect((float) $compenso->fresh()->ritenuta)->toBe(400.0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziController: Destroy
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziController::destroy', function () {

    it('elimina un compenso da versare', function () {
        $svc      = app(CompensaTerziService::class);
        $compenso = $svc->crea($this->tenant, compensoBase());

        $response = $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
        ])
        ->actingAs($this->user)
        ->delete(route('compensi-terzi.destroy', [$this->tenant, $compenso]));

        $response->assertRedirect(route('compensi-terzi.index', $this->tenant));
        $this->assertDatabaseMissing('compensi_terzi', ['id' => $compenso->id]);
    });

    it('blocca eliminazione se ritenuta già versata', function () {
        $svc      = app(CompensaTerziService::class);
        $compenso = $svc->crea($this->tenant, compensoBase());
        $compenso->update(['stato_ritenuta' => CompensaTerzi::STATO_VERSATA]);

        $response = $this->actingAs($this->user)
            ->delete(route('compensi-terzi.destroy', [$this->tenant, $compenso]));

        $response->assertRedirect(); // back
        $this->assertDatabaseHas('compensi_terzi', ['id' => $compenso->id]);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziController: Versa
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziController::versa', function () {

    it('registra versamento F24 e marca ritenute come versate', function () {
        $svc = app(CompensaTerziService::class);
        $svc->crea($this->tenant, compensoBase(['data_pagamento' => '2026-01-15']));

        $response = $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
        ])
        ->actingAs($this->user)
        ->post(route('compensi-terzi.versa', $this->tenant), [
            'mese'            => 1,
            'anno'            => 2026,
            'data_versamento' => '2026-02-16',
            'codice_tributo'  => '1040',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('versamenti_ritenute', [
            'tenant_id'        => $this->tenant->id,
            'mese_riferimento' => 1,
            'anno_riferimento' => 2026,
        ]);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// CompensaTerziController: Riepilogo / Versamenti
// ─────────────────────────────────────────────────────────────────────────────

describe('CompensaTerziController::riepilogo', function () {

    it('restituisce la pagina riepilogo CU', function () {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('compensi-terzi.riepilogo', $this->tenant));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('CompensaTerzi/Riepilogo');
    });

});

describe('CompensaTerziController::versamenti', function () {

    it('restituisce la pagina lista versamenti', function () {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()])
            ->get(route('compensi-terzi.versamenti', $this->tenant));

        $response->assertStatus(200);
        $page = json_decode($response->getContent(), true);
        expect($page['component'])->toBe('CompensaTerzi/Versamenti');
    });

});
