<?php

/**
 * Test suite per Event, EventRegistration e EventController.
 *
 * Copre:
 *  - Event model: CRUD e posterAttachment
 *  - EventController::store (creazione, validazione)
 *  - EventController::update (modifica)
 *  - EventController::destroy (eliminazione)
 *  - EventController::register (socio, ospite, solo_soci, idempotenza, errori)
 *  - EventController::unregister (rimozione iscrizione)
 *  - Middleware role:admin,segreteria
 */

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Models\MemberType;
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
        'name'              => 'Ente Events Test',
        'slug'              => 'ente-events-test-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $memberType = MemberType::firstOrCreate(
        ['name' => 'socio'],
        ['display_name' => 'Socio'],
    );

    // Socio di test
    $this->member = Member::create([
        'member_type_id'        => $memberType->id,
        'nome'                  => 'Laura',
        'cognome'               => 'Bianchi',
        'email'                 => 'laura@test.it',
        'numero_tessera'        => 1,
        'data_iscrizione'       => '2024-01-01',
        'stato'                 => 'attivo',
        'tipo_persona'          => 'fisica',
        'numero_quote_capitale' => 0,
    ]);

    // Admin
    $roleAdmin = Role::where('name', 'admin')->first();
    $this->user = User::factory()->create([
        'email'             => 'admin@test.it',
        'email_verified_at' => now(),
    ]);
    $this->user->roles()->attach($roleAdmin);
    $this->user->tenants()->attach($this->tenant);

    // Utente senza ruolo abilitato
    $this->userSenza = User::factory()->create([
        'email'             => 'nessun@test.it',
        'email_verified_at' => now(),
    ]);
    $this->userSenza->tenants()->attach($this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// Event model
// ─────────────────────────────────────────────────────────────────────────────

describe('Event model', function () {

    it('crea evento con attributi corretti', function () {
        $event = Event::create([
            'title'            => 'Assemblea Annuale',
            'start_at'         => Carbon::parse('2025-06-01 10:00'),
            'end_at'           => Carbon::parse('2025-06-01 12:00'),
            'max_participants' => 50,
            'description'      => 'Assemblea dei soci',
            'solo_soci'        => true,
        ]);

        expect($event)->toBeInstanceOf(Event::class)
            ->and($event->title)->toBe('Assemblea Annuale')
            ->and($event->solo_soci)->toBeTrue()
            ->and($event->max_participants)->toBe(50);
    });

    it('posterAttachment restituisce null se non c\'è locandina', function () {
        $event = Event::create([
            'title'    => 'Evento senza poster',
            'start_at' => Carbon::parse('2025-07-01 09:00'),
        ]);

        expect($event->posterAttachment())->toBeNull();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// EventController → store
// ─────────────────────────────────────────────────────────────────────────────

describe('EventController → store', function () {

    it('crea evento e reindirizza a index', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.store', $this->tenant), [
                'title'    => 'Workshop Laravel',
                'start_at' => '2025-09-10 14:00',
                'end_at'   => '2025-09-10 18:00',
                'solo_soci' => false,
            ]);

        $response->assertRedirect(route('events.index', $this->tenant));

        $event = Event::where('title', 'Workshop Laravel')->first();
        expect($event)->not->toBeNull()
            ->and($event->solo_soci)->toBeFalse();
    });

    it('store fallisce se title mancante', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.store', $this->tenant), [
                'start_at' => '2025-09-10 14:00',
            ]);

        $response->assertSessionHasErrors('title');
        expect(Event::count())->toBe(0);
    });

    it('store fallisce se end_at precede start_at', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.store', $this->tenant), [
                'title'    => 'Evento Errato',
                'start_at' => '2025-09-10 18:00',
                'end_at'   => '2025-09-10 14:00', // precede start_at
            ]);

        $response->assertSessionHasErrors('end_at');
    });

    it('utente senza ruolo admin/segreteria non può creare eventi', function () {
        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->userSenza)
            ->post(route('events.store', $this->tenant), [
                'title'    => 'Evento non autorizzato',
                'start_at' => '2025-09-10 14:00',
            ])
            ->assertForbidden();

        expect(Event::count())->toBe(0);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// EventController → update e destroy
// ─────────────────────────────────────────────────────────────────────────────

describe('EventController → update e destroy', function () {

    beforeEach(function () {
        $this->event = Event::create([
            'title'    => 'Evento Originale',
            'start_at' => Carbon::parse('2025-08-01 10:00'),
            'solo_soci' => false,
        ]);
    });

    it('update modifica titolo e reindirizza a show', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->put(route('events.update', [$this->tenant, $this->event]), [
                'title'    => 'Evento Aggiornato',
                'start_at' => '2025-08-01 10:00',
            ]);

        $response->assertRedirect(route('events.show', [$this->tenant, $this->event]));

        $this->event->refresh();
        expect($this->event->title)->toBe('Evento Aggiornato');
    });

    it('destroy elimina evento e reindirizza a index', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->delete(route('events.destroy', [$this->tenant, $this->event]));

        $response->assertRedirect(route('events.index', $this->tenant));
        expect(Event::find($this->event->id))->toBeNull();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// EventController → register
// ─────────────────────────────────────────────────────────────────────────────

describe('EventController → register', function () {

    beforeEach(function () {
        $this->eventAperto = Event::create([
            'title'    => 'Evento Aperto',
            'start_at' => Carbon::parse('2025-09-01 10:00'),
            'solo_soci' => false,
        ]);

        $this->eventSoloSoci = Event::create([
            'title'    => 'Evento Solo Soci',
            'start_at' => Carbon::parse('2025-09-02 10:00'),
            'solo_soci' => true,
        ]);
    });

    it('registra un socio (solo_soci=false, member_id)', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.register', [$this->tenant, $this->eventAperto]), [
                'member_id' => $this->member->id,
            ]);

        $response->assertRedirect();

        $reg = EventRegistration::where('event_id', $this->eventAperto->id)
            ->where('member_id', $this->member->id)
            ->first();

        expect($reg)->not->toBeNull()
            ->and($reg->status)->toBe('confermata');
    });

    it('registra un ospite (solo_soci=false, guest_name)', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.register', [$this->tenant, $this->eventAperto]), [
                'guest_name' => 'Mario Ospite',
            ]);

        $response->assertRedirect();

        $reg = EventRegistration::where('event_id', $this->eventAperto->id)
            ->where('guest_name', 'Mario Ospite')
            ->first();

        expect($reg)->not->toBeNull()
            ->and($reg->member_id)->toBeNull();
    });

    it('errore se vengono forniti sia member_id sia guest_name', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.register', [$this->tenant, $this->eventAperto]), [
                'member_id'  => $this->member->id,
                'guest_name' => 'Ospite Ambiguo',
            ]);

        // Deve tornare con errore di validazione
        $response->assertRedirect();
        $response->assertSessionHasErrors('member_id');
    });

    it('registra socio per evento solo_soci=true', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.register', [$this->tenant, $this->eventSoloSoci]), [
                'member_id' => $this->member->id,
            ]);

        $response->assertRedirect();
        expect(EventRegistration::where('event_id', $this->eventSoloSoci->id)->count())->toBe(1);
    });

    it('doppia registrazione è idempotente (firstOrCreate)', function () {
        $payload = ['member_id' => $this->member->id];

        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.register', [$this->tenant, $this->eventAperto]), $payload);

        $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->post(route('events.register', [$this->tenant, $this->eventAperto]), $payload);

        expect(
            EventRegistration::where('event_id', $this->eventAperto->id)
                ->where('member_id', $this->member->id)
                ->count()
        )->toBe(1);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// EventController → unregister
// ─────────────────────────────────────────────────────────────────────────────

describe('EventController → unregister', function () {

    beforeEach(function () {
        $this->event = Event::create([
            'title'    => 'Evento Unregister',
            'start_at' => Carbon::parse('2025-10-01 10:00'),
            'solo_soci' => false,
        ]);

        $this->registration = EventRegistration::create([
            'event_id'      => $this->event->id,
            'member_id'     => $this->member->id,
            'registered_at' => now(),
            'status'        => 'confermata',
        ]);
    });

    it('annulla iscrizione e reindirizza', function () {
        $response = $this
            ->withoutMiddleware([
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                \Laravel\Jetstream\Http\Middleware\AuthenticateSession::class,
            ])
            ->actingAs($this->user)
            ->delete(route('events.registrations.destroy', [
                $this->tenant,
                $this->event,
                $this->registration,
            ]));

        $response->assertRedirect();
        expect(EventRegistration::find($this->registration->id))->toBeNull();
    });

});
