<?php

/**
 * Test suite per Audit Trail (C2).
 *
 * Copre:
 *  - AuditsChanges trait: created / updated / deleted eventi
 *  - AuditLog::record() factory method
 *  - AuditLog::diff() helper
 *  - AuditController: index, show, export CSV
 *  - Tenant isolation: admin tenant A non vede log tenant B
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Audit Test ETS',
        'slug'              => 'aud-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000008888',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email'     => 'admin-audit@test.local',
    ]);
    $this->admin->roles()->attach($roleAdmin);
});

// ─────────────────────────────────────────────────────────────────────────────
// AuditsChanges trait — created
// ─────────────────────────────────────────────────────────────────────────────

it('AuditsChanges registra evento created al salvataggio del model', function () {
    $member = Member::create([
        'tenant_id'         => $this->tenant->id,
        'nome'              => 'Mario',
        'cognome'           => 'Rossi',
        'data_iscrizione'   => '2024-01-01',
        'stato_membership'  => 'attivo',
    ]);

    $log = AuditLog::where('entity_type', Member::class)
        ->where('entity_id', $member->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values)->toBeArray()
        ->and($log->new_values['nome'])->toBe('Mario');
});

// ─────────────────────────────────────────────────────────────────────────────
// AuditsChanges trait — updated
// ─────────────────────────────────────────────────────────────────────────────

it('AuditsChanges registra evento updated con old e new values', function () {
    $member = Member::create([
        'tenant_id'         => $this->tenant->id,
        'nome'              => 'Mario',
        'cognome'           => 'Rossi',
        'data_iscrizione'   => '2024-01-01',
        'stato_membership'  => 'attivo',
    ]);

    $member->update(['cognome' => 'Bianchi']);

    $log = AuditLog::where('entity_type', Member::class)
        ->where('entity_id', $member->id)
        ->where('action', AuditLog::ACTION_UPDATED)
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['cognome'])->toBe('Rossi')
        ->and($log->new_values['cognome'])->toBe('Bianchi');
});

it('AuditsChanges NON registra updated se nessun campo cambia', function () {
    $member = Member::create([
        'tenant_id'         => $this->tenant->id,
        'nome'              => 'Mario',
        'cognome'           => 'Rossi',
        'data_iscrizione'   => '2024-01-01',
        'stato_membership'  => 'attivo',
    ]);

    $countBefore = AuditLog::where('action', AuditLog::ACTION_UPDATED)->count();

    // Save senza modifiche reali
    $member->save();

    $countAfter = AuditLog::where('action', AuditLog::ACTION_UPDATED)->count();

    expect($countAfter)->toBe($countBefore);
});

// ─────────────────────────────────────────────────────────────────────────────
// AuditsChanges trait — deleted
// ─────────────────────────────────────────────────────────────────────────────

it('AuditsChanges registra evento deleted alla cancellazione', function () {
    $member = Member::create([
        'tenant_id'         => $this->tenant->id,
        'nome'              => 'Luigi',
        'cognome'           => 'Verdi',
        'data_iscrizione'   => '2024-01-01',
        'stato_membership'  => 'attivo',
    ]);

    $memberId = $member->id;
    $member->delete();

    $log = AuditLog::where('entity_type', Member::class)
        ->where('entity_id', $memberId)
        ->where('action', AuditLog::ACTION_DELETED)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['cognome'])->toBe('Verdi');
});

// ─────────────────────────────────────────────────────────────────────────────
// AuditLog::record() factory method
// ─────────────────────────────────────────────────────────────────────────────

it('AuditLog::record crea log con i campi attesi', function () {
    $member = Member::create([
        'tenant_id'         => $this->tenant->id,
        'nome'              => 'Test',
        'cognome'           => 'Record',
        'data_iscrizione'   => '2024-01-01',
        'stato_membership'  => 'attivo',
    ]);

    // Crea log manuale
    $log = AuditLog::record(
        action:    'custom_action',
        entity:    $member,
        newValues: ['stato_membership' => 'sospeso'],
        tenantId:  $this->tenant->id,
    );

    expect($log->entity_type)->toBe(Member::class)
        ->and($log->entity_id)->toBe($member->id)
        ->and($log->action)->toBe('custom_action')
        ->and($log->new_values['stato_membership'])->toBe('sospeso');
});

// ─────────────────────────────────────────────────────────────────────────────
// AuditLog::diff()
// ─────────────────────────────────────────────────────────────────────────────

it('AuditLog::diff restituisce solo i campi effettivamente cambiati', function () {
    $log = AuditLog::create([
        'tenant_id'   => $this->tenant->id,
        'entity_type' => Member::class,
        'entity_id'   => 1,
        'action'      => AuditLog::ACTION_UPDATED,
        'old_values'  => ['nome' => 'Mario', 'cognome' => 'Rossi', 'stato_membership' => 'attivo'],
        'new_values'  => ['nome' => 'Mario', 'cognome' => 'Bianchi', 'stato_membership' => 'sospeso'],
    ]);

    $diff = $log->diff();

    expect($diff)->toHaveKey('cognome')
        ->and($diff)->toHaveKey('stato_membership')
        ->and($diff)->not->toHaveKey('nome')
        ->and($diff['cognome']['old'])->toBe('Rossi')
        ->and($diff['cognome']['new'])->toBe('Bianchi');
});

// ─────────────────────────────────────────────────────────────────────────────
// AuditController — index
// ─────────────────────────────────────────────────────────────────────────────

it('audit index restituisce vista Inertia paginata', function () {
    // Crea qualche log
    AuditLog::create([
        'tenant_id'   => $this->tenant->id,
        'entity_type' => Member::class,
        'entity_id'   => 1,
        'action'      => AuditLog::ACTION_CREATED,
    ]);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('audit.index', $this->tenant))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Admin/Audit/Index')
             ->has('logs')
             ->has('actionLabels')
             ->has('entityTypes')
         );
});

it('audit index filtra per azione', function () {
    AuditLog::create(['tenant_id' => $this->tenant->id, 'entity_type' => Member::class, 'entity_id' => 1, 'action' => AuditLog::ACTION_CREATED]);
    AuditLog::create(['tenant_id' => $this->tenant->id, 'entity_type' => Member::class, 'entity_id' => 2, 'action' => AuditLog::ACTION_DELETED]);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('audit.index', [$this->tenant, 'action' => AuditLog::ACTION_CREATED]))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->where('logs.total', 1)
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// AuditController — show
// ─────────────────────────────────────────────────────────────────────────────

it('audit show restituisce dettaglio con diff', function () {
    $log = AuditLog::create([
        'tenant_id'   => $this->tenant->id,
        'entity_type' => Member::class,
        'entity_id'   => 1,
        'action'      => AuditLog::ACTION_UPDATED,
        'old_values'  => ['cognome' => 'Rossi'],
        'new_values'  => ['cognome' => 'Bianchi'],
    ]);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('audit.show', [$this->tenant, $log]))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Admin/Audit/Show')
             ->has('log')
             ->has('diff')
             ->where('diff.cognome.old', 'Rossi')
             ->where('diff.cognome.new', 'Bianchi')
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Tenant isolation
// ─────────────────────────────────────────────────────────────────────────────

it('admin tenant A non vede log di tenant B', function () {
    // Crea secondo tenant con log
    $tenantB = Tenant::create([
        'name'              => 'Tenant B',
        'slug'              => 'ten-b-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000007777',
    ]);

    AuditLog::create([
        'tenant_id'   => $tenantB->id,
        'entity_type' => Member::class,
        'entity_id'   => 999,
        'action'      => AuditLog::ACTION_CREATED,
    ]);

    // Log del tenant A
    AuditLog::create([
        'tenant_id'   => $this->tenant->id,
        'entity_type' => Member::class,
        'entity_id'   => 1,
        'action'      => AuditLog::ACTION_CREATED,
    ]);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('audit.index', $this->tenant))
         ->assertInertia(fn ($page) => $page
             ->where('logs.total', 1) // solo il log del tenant A
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Export CSV
// ─────────────────────────────────────────────────────────────────────────────

it('export CSV restituisce HTTP 200 con Content-Type CSV', function () {
    AuditLog::create([
        'tenant_id'   => $this->tenant->id,
        'entity_type' => Member::class,
        'entity_id'   => 1,
        'action'      => AuditLog::ACTION_CREATED,
    ]);

    $this->actingAs($this->admin)
         ->get(route('audit.export', $this->tenant))
         ->assertOk()
         ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

// ─────────────────────────────────────────────────────────────────────────────
// Accesso non-admin bloccato
// ─────────────────────────────────────────────────────────────────────────────

it('utente non-admin non può accedere all audit trail', function () {
    $roleContabile = Role::where('name', 'contabile')->first();
    $contabile     = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $contabile->roles()->attach($roleContabile);

    $this->actingAs($contabile)
         ->get(route('audit.index', $this->tenant))
         ->assertStatus(403);
});
