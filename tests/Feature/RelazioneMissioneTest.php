<?php

/**
 * Test suite per Relazione di Missione ETS (G3).
 *
 * Copre:
 *  - RelazioneMissioneService::raccogliVariabili  (struttura array)
 *  - RelazioneMissioneService::crea               (sezioni default, snapshot, unique anno)
 *  - RelazioneMissioneService::aggiorna           (sezioni, stato, variabili refresh)
 *  - RelazioneMissioneService::approva            (stato approvata, blocco doppia)
 *  - RelazioneMissioneService::interpolaVariabili (sostituzione {{placeholder}})
 *  - RelazioneMissioneController: index, create, store, show, edit, update, approva, destroy
 *  - Export PDF (HTTP status)
 *  - Middleware role:admin,contabile
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\RelazioneMissione;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RelazioneMissioneService;
use Database\Seeders\RoleSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'ETS Relazione Test',
        'slug'              => 'ets-rel-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000009999',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $roleAdmin = Role::where('name', 'admin')->first();

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email'     => 'admin-rel@test.local',
    ]);
    $this->admin->roles()->attach($roleAdmin);

    $this->service = app(RelazioneMissioneService::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function relazioneBase(Tenant $tenant): RelazioneMissione
{
    return RelazioneMissione::create([
        'tenant_id'           => $tenant->id,
        'anno'                => 2024,
        'stato'               => RelazioneMissione::STATO_BOZZA,
        'organo_approvante'   => 'Assemblea dei soci',
        'sezioni'             => RelazioneMissione::SEZIONI_DEFAULT,
        'variabili_snapshot'  => ['totale_soci' => 50, 'anno' => 2024],
        'note_interne'        => null,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Service: raccogliVariabili
// ─────────────────────────────────────────────────────────────────────────────

it('raccogliVariabili restituisce le chiavi attese', function () {
    $variabili = $this->service->raccogliVariabili($this->tenant, 2024);

    expect($variabili)->toBeArray()
        ->toHaveKeys([
            'anno', 'nome_ente', 'codice_fiscale_ente', 'indirizzo_ente',
            'totale_soci', 'nuovi_soci', 'soci_cessati',
            'totale_entrate', 'totale_uscite', 'risultato_esercizio',
            'quote_associative', 'donazioni_ricevute',
            'compensi_terzi', 'ritenute_versate', 'numero_eventi',
        ]);

    expect($variabili['anno'])->toBe(2024);
    expect($variabili['nome_ente'])->toBeString()->not->toBeEmpty();
});

it('raccogliVariabili anno è numerico e corretto', function () {
    $v = $this->service->raccogliVariabili($this->tenant, 2023);
    expect($v['anno'])->toBe(2023);
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: crea
// ─────────────────────────────────────────────────────────────────────────────

it('crea crea relazione con 8 sezioni e snapshot variabili', function () {
    $rel = $this->service->crea($this->tenant, 2024);

    expect($rel)->toBeInstanceOf(RelazioneMissione::class);
    expect($rel->tenant_id)->toBe($this->tenant->id);
    expect($rel->anno)->toBe(2024);
    expect($rel->stato)->toBe(RelazioneMissione::STATO_BOZZA);
    expect($rel->sezioni)->toBeArray()->toHaveCount(8);
    expect($rel->variabili_snapshot)->toBeArray()->toHaveKey('anno');
});

it('crea lancia eccezione se anno già presente', function () {
    $this->service->crea($this->tenant, 2024);

    expect(fn () => $this->service->crea($this->tenant, 2024))
        ->toThrow(\InvalidArgumentException::class);
});

it('crea pre-compila i testi delle sezioni con variabili', function () {
    $rel = $this->service->crea($this->tenant, 2024);

    $testo = collect($rel->sezioni)->firstWhere('id', 'presentazione')['testo'] ?? '';
    expect($testo)->not->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: aggiorna
// ─────────────────────────────────────────────────────────────────────────────

it('aggiorna salva sezioni e stato', function () {
    $rel = relazioneBase($this->tenant);

    $sezioni = collect($rel->sezioni)->map(function ($s, $i) {
        return [...$s, 'testo' => "Testo sezione $i aggiornato."];
    })->all();

    $updated = $this->service->aggiorna($rel, [
        'stato'   => RelazioneMissione::STATO_DEFINITIVA,
        'sezioni' => $sezioni,
    ]);

    expect($updated->stato)->toBe(RelazioneMissione::STATO_DEFINITIVA);
    expect($updated->sezioni[0]['testo'])->toBe('Testo sezione 0 aggiornato.');
});

it('aggiorna lancia eccezione se relazione approvata', function () {
    $rel = relazioneBase($this->tenant);
    $rel->update(['stato' => RelazioneMissione::STATO_APPROVATA]);

    expect(fn () => $this->service->aggiorna($rel, ['stato' => 'bozza']))
        ->toThrow(\InvalidArgumentException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: approva
// ─────────────────────────────────────────────────────────────────────────────

it('approva porta la relazione in stato approvata', function () {
    $rel = relazioneBase($this->tenant);

    $approved = $this->service->approva($rel, '2024-06-15', 'Assemblea straordinaria', 'Milano');

    expect($approved->stato)->toBe(RelazioneMissione::STATO_APPROVATA);
    expect($approved->organo_approvante)->toBe('Assemblea straordinaria');
    expect($approved->luogo_approvazione)->toBe('Milano');
});

it('approva lancia eccezione se già approvata', function () {
    $rel = relazioneBase($this->tenant);
    $rel->update(['stato' => RelazioneMissione::STATO_APPROVATA]);

    expect(fn () => $this->service->approva($rel, '2024-06-15', 'Assemblea'))
        ->toThrow(\InvalidArgumentException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Service: interpolaVariabili
// ─────────────────────────────────────────────────────────────────────────────

it('interpolaVariabili sostituisce placeholder con valori', function () {
    $testo     = "L'ente conta {{totale_soci}} soci. Entrate: € {{totale_entrate}}.";
    $variabili = ['totale_soci' => 42, 'totale_entrate' => 15000.50];

    $result = $this->service->interpolaVariabili($testo, $variabili);

    expect($result)->toContain('42')
                   ->toContain('15.000,50')
                   ->not->toContain('{{totale_soci}}')
                   ->not->toContain('{{totale_entrate}}');
});

it('interpolaVariabili lascia intatti placeholder sconosciuti', function () {
    $testo  = 'Ente {{nome_ente}} — {{sconosciuto}}';
    $result = $this->service->interpolaVariabili($testo, ['nome_ente' => 'TestETS']);

    expect($result)->toContain('TestETS')
                   ->toContain('{{sconosciuto}}');
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: index
// ─────────────────────────────────────────────────────────────────────────────

it('index restituisce vista Inertia con relazioni', function () {
    relazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('relazione-missione.index', $this->tenant))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Bilancio/RelazioneMissione/Index')
             ->has('relazioni')
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: create
// ─────────────────────────────────────────────────────────────────────────────

it('create restituisce vista con variabili e sezioniDefault', function () {
    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('relazione-missione.create', $this->tenant))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Bilancio/RelazioneMissione/Create')
             ->has('variabili')
             ->has('sezioniDefault')
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: store
// ─────────────────────────────────────────────────────────────────────────────

it('store crea relazione e reindirizza a edit', function () {
    $this->actingAs($this->admin)
         ->post(route('relazione-missione.store', $this->tenant), [
             'anno'              => 2023,
             'organo_approvante' => 'Assemblea ordinaria',
         ])
         ->assertRedirect();

    $this->assertDatabaseHas('relazioni_missione', [
        'tenant_id' => $this->tenant->id,
        'anno'      => 2023,
    ]);
});

it('store respinge anno duplicato con errore flash', function () {
    relazioneBase($this->tenant); // anno 2024 già presente

    $this->actingAs($this->admin)
         ->post(route('relazione-missione.store', $this->tenant), [
             'anno' => 2024,
         ])
         ->assertRedirect(); // back con errore

    expect(RelazioneMissione::where('tenant_id', $this->tenant->id)->where('anno', 2024)->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: show
// ─────────────────────────────────────────────────────────────────────────────

it('show restituisce vista con sezioniInterpolate', function () {
    $rel = relazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('relazione-missione.show', [$this->tenant, $rel]))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Bilancio/RelazioneMissione/Show')
             ->has('sezioniInterpolate')
             ->has('variabili')
         );
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: edit
// ─────────────────────────────────────────────────────────────────────────────

it('edit restituisce vista con placeholder_list', function () {
    $rel = relazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->withoutMiddleware(HandleInertiaRequests::class)
         ->get(route('relazione-missione.edit', [$this->tenant, $rel]))
         ->assertOk()
         ->assertInertia(fn ($page) => $page
             ->component('Bilancio/RelazioneMissione/Edit')
             ->has('placeholder_list')
         );
});

it('edit reindirizza a show se relazione approvata', function () {
    $rel = relazioneBase($this->tenant);
    $rel->update(['stato' => RelazioneMissione::STATO_APPROVATA]);

    $this->actingAs($this->admin)
         ->get(route('relazione-missione.edit', [$this->tenant, $rel]))
         ->assertRedirect(route('relazione-missione.show', [$this->tenant, $rel]));
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: update
// ─────────────────────────────────────────────────────────────────────────────

it('update salva sezioni aggiornate', function () {
    $rel = relazioneBase($this->tenant);
    $sezioni = collect(RelazioneMissione::SEZIONI_DEFAULT)->map(fn ($s) => [
        ...$s, 'testo' => 'Testo aggiornato per ' . $s['titolo'],
    ])->all();

    $this->actingAs($this->admin)
         ->put(route('relazione-missione.update', [$this->tenant, $rel]), [
             'stato'   => 'definitiva',
             'sezioni' => $sezioni,
         ])
         ->assertRedirect(route('relazione-missione.show', [$this->tenant, $rel]));

    expect(RelazioneMissione::find($rel->id)->stato)->toBe('definitiva');
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: approva
// ─────────────────────────────────────────────────────────────────────────────

it('approva porta relazione ad approvata', function () {
    $rel = relazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->post(route('relazione-missione.approva', [$this->tenant, $rel]), [
             'data_approvazione'  => '2024-06-15',
             'organo_approvante'  => 'Assemblea dei soci',
             'luogo_approvazione' => 'Milano',
         ])
         ->assertRedirect(route('relazione-missione.show', [$this->tenant, $rel]));

    expect(RelazioneMissione::find($rel->id)->stato)->toBe('approvata');
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: destroy
// ─────────────────────────────────────────────────────────────────────────────

it('destroy elimina relazione in bozza', function () {
    $rel = relazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->delete(route('relazione-missione.destroy', [$this->tenant, $rel]))
         ->assertRedirect(route('relazione-missione.index', $this->tenant));

    $this->assertDatabaseMissing('relazioni_missione', ['id' => $rel->id]);
});

it('destroy blocca eliminazione di relazione approvata', function () {
    $rel = relazioneBase($this->tenant);
    $rel->update(['stato' => RelazioneMissione::STATO_APPROVATA]);

    $this->actingAs($this->admin)
         ->delete(route('relazione-missione.destroy', [$this->tenant, $rel]))
         ->assertRedirect();

    $this->assertDatabaseHas('relazioni_missione', ['id' => $rel->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller: exportPdf
// ─────────────────────────────────────────────────────────────────────────────

it('exportPdf restituisce HTTP 200 con Content-Type PDF', function () {
    $rel = relazioneBase($this->tenant);

    $this->actingAs($this->admin)
         ->get(route('relazione-missione.pdf', [$this->tenant, $rel]))
         ->assertOk()
         ->assertHeader('Content-Type', 'application/pdf');
});

// ─────────────────────────────────────────────────────────────────────────────
// Model helpers
// ─────────────────────────────────────────────────────────────────────────────

it('sezioniCompilate conta solo sezioni con testo', function () {
    $rel = relazioneBase($this->tenant);

    // Default: all sezioni have empty testo
    expect($rel->sezioniCompilate())->toBe(0);

    // Update with some compiled
    $sezioni = collect($rel->sezioni)->map(fn ($s, $i) => [
        ...$s, 'testo' => $i < 3 ? 'Testo presente.' : '',
    ])->all();
    $rel->update(['sezioni' => $sezioni]);

    expect($rel->fresh()->sezioniCompilate())->toBe(3);
});

it('isBozza isDefinitiva isApprovata funzionano correttamente', function () {
    $rel = relazioneBase($this->tenant);
    expect($rel->isBozza())->toBeTrue();
    expect($rel->isDefinitiva())->toBeFalse();
    expect($rel->isApprovata())->toBeFalse();

    $rel->update(['stato' => RelazioneMissione::STATO_APPROVATA]);
    expect($rel->fresh()->isApprovata())->toBeTrue();
});
