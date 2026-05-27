<?php

/**
 * Test del flusso richieste consulente ↔ associazione.
 *
 * Copre:
 *  - Il consulente crea una richiesta e l'ente (admin/segreteria) viene notificato
 *  - L'ente carica una risposta e il consulente viene notificato
 *  - Il consulente può scaricare il documento di risposta (signed URL)
 */

use App\Models\ConsultantAssignment;
use App\Models\ConsultantRequest;
use App\Models\ConsultantRequestDocument;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ConsultantRequestCreatedNotification;
use App\Notifications\ConsultantRequestResponseReceivedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    (new RoleSeeder)->run();

    $this->tenant = Tenant::create([
        'name'              => 'Ente Richieste Test',
        'slug'              => 'ente-req-test-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    // Consulente assegnato attivo al tenant
    $this->consultant = User::factory()->create(['email_verified_at' => now()]);
    $this->consultant->roles()->attach(Role::where('name', 'consultant')->first());
    ConsultantAssignment::create([
        'consultant_user_id' => $this->consultant->id,
        'tenant_id'          => $this->tenant->id,
        'ruolo'              => 'primario',
        'active'             => true,
        'started_at'         => now(),
    ]);

    // Admin dell'ente (riceve le notifiche)
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->roles()->attach(Role::where('name', 'admin')->first());
    $this->admin->tenants()->attach($this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

it('mostra il form di creazione richiesta (no pagina bianca)', function () {
    $this->actingAs($this->consultant)
        ->get(route('consultant.requests.create', $this->tenant->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Consultant/Requests/Create')
            ->where('entity.slug', $this->tenant->slug)
            ->where('entity.name', $this->tenant->name)
            // Il default tenant DEVE essere presente, altrimenti AppLayout
            // crasha su route() dei link tenant (es. movimenti-amministrativi.index)
            ->where('ziggy.defaults.tenant', $this->tenant->slug)
        );
});

it('il consulente crea una richiesta e l\'ente viene notificato', function () {
    Notification::fake();

    $this->actingAs($this->consultant)
        ->post(route('consultant.requests.store', $this->tenant->slug), [
            'titolo'      => 'Servono i registri IVA Q1',
            'descrizione' => 'Carica i registri del primo trimestre.',
            'priorita'    => 'alta',
        ])
        ->assertRedirect();

    // Richiesta creata e legata al tenant
    $richiesta = ConsultantRequest::withoutGlobalScope('tenant')
        ->where('tenant_id', $this->tenant->id)
        ->where('titolo', 'Servono i registri IVA Q1')
        ->first();
    expect($richiesta)->not->toBeNull();
    expect($richiesta->stato)->toBe(ConsultantRequest::STATO_APERTA);
    expect($richiesta->consultant_user_id)->toBe($this->consultant->id);

    // L'admin dell'ente è stato notificato
    Notification::assertSentTo($this->admin, ConsultantRequestCreatedNotification::class);
});

it('un utente non assegnato non può creare richieste per il tenant', function () {
    $estraneo = User::factory()->create(['email_verified_at' => now()]);
    $estraneo->roles()->attach(Role::where('name', 'consultant')->first());

    $this->actingAs($estraneo)
        ->post(route('consultant.requests.store', $this->tenant->slug), [
            'titolo'   => 'X',
            'priorita' => 'normale',
        ])
        ->assertForbidden();
});

it('l\'ente carica una risposta e il consulente viene notificato', function () {
    Notification::fake();
    Storage::fake('consultant_exchange');

    app()->instance('current_tenant', $this->tenant);
    $richiesta = ConsultantRequest::create([
        'consultant_user_id' => $this->consultant->id,
        'titolo'             => 'Documenti',
        'priorita'           => 'normale',
        'stato'              => ConsultantRequest::STATO_APERTA,
    ]);

    $this->actingAs($this->admin)
        ->post(route('tenant.consultant-inbox.requests.respond', [$this->tenant, $richiesta->id]), [
            'file' => UploadedFile::fake()->create('registro.pdf', 100, 'application/pdf'),
            'note' => 'Ecco il registro.',
        ])
        ->assertRedirect();

    $richiesta->refresh();
    expect($richiesta->stato)->toBe(ConsultantRequest::STATO_RISPOSTA_RICEVUTA);

    $doc = ConsultantRequestDocument::withoutGlobalScope('tenant')
        ->where('consultant_request_id', $richiesta->id)->first();
    expect($doc)->not->toBeNull();
    expect($doc->uploaded_as_response)->toBeTrue(); // flag ora persistito

    Notification::assertSentTo($this->consultant, ConsultantRequestResponseReceivedNotification::class);
});

it('il consulente può scaricare il documento di risposta', function () {
    Storage::fake('consultant_exchange');

    app()->instance('current_tenant', $this->tenant);
    $richiesta = ConsultantRequest::create([
        'consultant_user_id' => $this->consultant->id,
        'titolo'             => 'Documenti',
        'priorita'           => 'normale',
        'stato'              => ConsultantRequest::STATO_RISPOSTA_RICEVUTA,
    ]);
    Storage::disk('consultant_exchange')->put('requests/x/registro.pdf', 'contenuto pdf');
    $doc = ConsultantRequestDocument::create([
        'consultant_request_id' => $richiesta->id,
        'tenant_id'             => $this->tenant->id,
        'uploaded_by_user_id'   => $this->admin->id,
        'filename_originale'    => 'registro.pdf',
        'disk'                  => 'consultant_exchange',
        'path'                  => 'requests/x/registro.pdf',
        'size'                  => 13,
        'mime_type'             => 'application/pdf',
        'uploaded_as_response'  => true,
    ]);

    $url = URL::temporarySignedRoute(
        'consultant.requests.documents.download',
        now()->addHour(),
        ['tenantSlug' => $this->tenant->slug, 'requestId' => $richiesta->id, 'documentId' => $doc->id],
    );

    $this->actingAs($this->consultant)->get($url)->assertOk();
});
