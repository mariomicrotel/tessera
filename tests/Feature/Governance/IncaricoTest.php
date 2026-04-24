<?php

/**
 * Test suite per Incarico e IncaricoController.
 *
 * Copre:
 *  - Assegnazione incarico (creazione, validazione, redirect)
 *  - Rimozione incarico (eliminazione, redirect, autorizzazione)
 *  - Middleware role:admin,segreteria
 *  - Member policy authorization (update)
 */

use App\Models\CaricaSociale;
use App\Models\Incarico;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Organo;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Incarichi Test',
        'slug'              => 'coop-incarichi-test-' . uniqid(),
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    // Setup roles
    (new RoleSeeder)->run();

    // Organo
    $this->organo = Organo::create([
        'slug'        => 'consiglio',
        'nome'        => 'Consiglio Direttivo',
        'descrizione' => 'Organo di governo',
    ]);

    // Cariche sociali
    $this->caricaPresidente = CaricaSociale::create([
        'organo_id' => $this->organo->id,
        'nome'      => 'Presidente',
        'ordine'    => 1,
        'multiplo'  => false,
    ]);

    $this->caricaSegretario = CaricaSociale::create([
        'organo_id' => $this->organo->id,
        'nome'      => 'Segretario',
        'ordine'    => 2,
        'multiplo'  => false,
    ]);

    $this->caricaMembro = CaricaSociale::create([
        'organo_id' => $this->organo->id,
        'nome'      => 'Membro',
        'ordine'    => 3,
        'multiplo'  => true,
    ]);

    // MemberType
    $memberType = MemberType::firstOrCreate(
        ['name' => 'socio'],
        ['display_name' => 'Socio'],
    );

    // Soci
    $this->socio1 = Member::create([
        'member_type_id'    => $memberType->id,
        'nome'              => 'Luigi',
        'cognome'           => 'Ferrari',
        'email'             => 'luigi@test.it',
        'numero_tessera'    => 1,
        'data_iscrizione'   => '2024-01-01',
        'stato'             => 'attivo',
        'tipo_persona'      => 'fisica',
        'numero_quote_capitale' => 0,
    ]);

    $this->socio2 = Member::create([
        'member_type_id'    => $memberType->id,
        'nome'              => 'Maria',
        'cognome'           => 'Conti',
        'email'             => 'maria@test.it',
        'numero_tessera'    => 2,
        'data_iscrizione'   => '2024-01-01',
        'stato'             => 'attivo',
        'tipo_persona'      => 'fisica',
        'numero_quote_capitale' => 0,
    ]);

    // Admin user with role:admin
    $roleAdmin = Role::where('name', 'admin')->first();
    $this->userAdmin = User::factory()->create([
        'email'             => 'admin@test.it',
        'email_verified_at' => now(),
    ]);
    $this->userAdmin->roles()->attach($roleAdmin);
    $this->userAdmin->tenants()->attach($this->tenant);

    // Segreteria user with role:segreteria
    $roleSegreteria = Role::firstOrCreate(
        ['name' => 'segreteria'],
        ['display_name' => 'Segreteria', 'description' => 'Staff di segreteria'],
    );
    $this->userSegreteria = User::factory()->create([
        'email'             => 'segreteria@test.it',
        'email_verified_at' => now(),
    ]);
    $this->userSegreteria->roles()->attach($roleSegreteria);
    $this->userSegreteria->tenants()->attach($this->tenant);

    // Regular user without required role
    $this->userMember = User::factory()->create([
        'email'             => 'membro@test.it',
        'email_verified_at' => now(),
    ]);
    $this->userMember->tenants()->attach($this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// IncaricoController → store
// ─────────────────────────────────────────────────────────────────────────────

describe('IncaricoController → store', function () {

    it('assegna incarico a un socio (admin)', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userAdmin)
            ->post(route('members.incarichi.store', [$this->tenant->slug, $this->socio1]), [
                'carica_sociale_id' => $this->caricaPresidente->id,
            ]);

        $response->assertRedirect();

        $incarico = Incarico::where('member_id', $this->socio1->id)
            ->where('carica_sociale_id', $this->caricaPresidente->id)
            ->first();

        expect($incarico)->not->toBeNull();
    });

    it('assegna incarico a un socio (segreteria)', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSegreteria)
            ->post(route('members.incarichi.store', [$this->tenant->slug, $this->socio2]), [
                'carica_sociale_id' => $this->caricaMembro->id,
            ]);

        $response->assertRedirect();

        $incarico = Incarico::where('member_id', $this->socio2->id)
            ->where('carica_sociale_id', $this->caricaMembro->id)
            ->first();

        expect($incarico)->not->toBeNull();
    });

    it('valida che carica_sociale_id esista', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userAdmin)
            ->post(route('members.incarichi.store', [$this->tenant->slug, $this->socio1]), [
                'carica_sociale_id' => 9999, // non esiste
            ]);

        $response->assertSessionHasErrors('carica_sociale_id');
    });

    it('rifiuta assegnazione senza ruolo admin o segreteria', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userMember)
            ->post(route('members.incarichi.store', [$this->tenant->slug, $this->socio1]), [
                'carica_sociale_id' => $this->caricaPresidente->id,
            ])
            ->assertForbidden();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// IncaricoController → destroy
// ─────────────────────────────────────────────────────────────────────────────

describe('IncaricoController → destroy', function () {

    beforeEach(function () {
        $this->incarico = Incarico::create([
            'member_id'         => $this->socio1->id,
            'carica_sociale_id' => $this->caricaPresidente->id,
        ]);
    });

    it('rimuove un incarico (admin)', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userAdmin)
            ->delete(route('incarichi.destroy', [$this->tenant->slug, $this->incarico]));

        $response->assertRedirect();

        expect(Incarico::find($this->incarico->id))->toBeNull();
    });

    it('rimuove un incarico (segreteria)', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSegreteria)
            ->delete(route('incarichi.destroy', [$this->tenant->slug, $this->incarico]));

        $response->assertRedirect();

        expect(Incarico::find($this->incarico->id))->toBeNull();
    });

    it('rifiuta rimozione incarico senza ruolo admin o segreteria', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userMember)
            ->delete(route('incarichi.destroy', [$this->tenant->slug, $this->incarico]))
            ->assertForbidden();

        // Incarico ancora presente
        expect(Incarico::find($this->incarico->id))->not->toBeNull();
    });

});
