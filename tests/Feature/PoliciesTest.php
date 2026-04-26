<?php

/**
 * Test suite per Model Policies (C1).
 *
 * Verifica che ogni policy applichi correttamente le regole di accesso:
 *  - admin            → bypass via Gate::before (isAdmin())
 *  - contabile        → create/update (con restrizioni di stato)
 *  - segreteria       → sola lettura
 *  - utente generico  → nessun accesso
 *
 * Policies testate:
 *  - FatturaAttivaPolicy
 *  - FatturaPassivaPolicy
 *  - IncassoPolicy
 *  - CooperativeSharePolicy
 *  - MovimentoContabilePolicy
 */

use App\Models\CooperativeShare;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\Incasso;
use App\Models\MovimentoContabile;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Policy Test ETS',
        'slug'              => 'pol-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000009999',
    ]);

    app()->instance('current_tenant', $this->tenant);

    (new RoleSeeder)->run();

    $makeUser = function (string $roleName): User {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);
        return $user;
    };

    $this->admin      = $makeUser('admin');
    $this->contabile  = $makeUser('contabile');
    $this->segreteria = $makeUser('segreteria');
    $this->guest      = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper: crea FatturaAttiva minima
// ─────────────────────────────────────────────────────────────────────────────

function makeFatturaAttiva(array $overrides = []): FatturaAttiva
{
    return FatturaAttiva::create(array_merge([
        'tenant_id'        => app('current_tenant')->id,
        'anno'             => 2024,
        'progressivo'      => rand(1, 9999),
        'numero_fattura'   => 'FA-TEST-' . uniqid(),
        'data_fattura'     => '2024-06-01',
        'tipo_documento'   => 'TD01',
        'esigibilita'      => 'immediata',
        'stato'            => FatturaAttiva::STATO_BOZZA,
        'stato_pagamento'  => FatturaAttiva::STATO_PAG_DA_INCASSARE,
        'imponibile'       => 100.0,
        'iva_totale'       => 22.0,
        'totale_documento' => 122.0,
    ], $overrides));
}

function makeFatturaPassiva(array $overrides = []): FatturaPassiva
{
    return FatturaPassiva::create(array_merge([
        'tenant_id'            => app('current_tenant')->id,
        'numero_fattura'       => 'FP-TEST-' . uniqid(),
        'data_fattura'         => '2024-06-01',
        'data_registrazione'   => '2024-06-01',
        'tipo_documento'       => 'TD01',
        'esigibilita'          => 'immediata',
        'stato_pagamento'      => FatturaPassiva::STATO_DA_PAGARE,
        'imponibile'           => 200.0,
        'iva_totale'           => 44.0,
        'totale_documento'     => 244.0,
        'liquidazione_iva_id'  => null,
    ], $overrides));
}

function makeShare(array $overrides = []): CooperativeShare
{
    return CooperativeShare::create(array_merge([
        'tenant_id'           => app('current_tenant')->id,
        'numero_quote'        => 5,
        'valore_unitario'     => 50,
        'totale_sottoscritto' => 250,
        'totale_versato'      => 0,
        'data_sottoscrizione' => '2024-01-01',
        'status'              => 'sottoscritta',
    ], $overrides));
}

function makeMovimento(array $overrides = []): MovimentoContabile
{
    return MovimentoContabile::create(array_merge([
        'tenant_id'      => app('current_tenant')->id,
        'anno_esercizio' => 2024,
        'data'           => '2024-06-01',
        'causale'        => 'Test policy',
        'stato'          => MovimentoContabile::STATO_BOZZA,
        'numero'         => uniqid(),
    ], $overrides));
}

// ─────────────────────────────────────────────────────────────────────────────
// FatturaAttivaPolicy
// ─────────────────────────────────────────────────────────────────────────────

it('FatturaAttivaPolicy: admin bypassa tutto via Gate::before', function () {
    $fatturaPagata = makeFatturaAttiva(['stato' => FatturaAttiva::STATO_EMESSA]);

    expect(Gate::forUser($this->admin)->allows('viewAny', FatturaAttiva::class))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('create', FatturaAttiva::class))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('update', $fatturaPagata))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('delete', $fatturaPagata))->toBeTrue();
});

it('FatturaAttivaPolicy: contabile può creare e visualizzare', function () {
    $fattura = makeFatturaAttiva();

    expect(Gate::forUser($this->contabile)->allows('viewAny', FatturaAttiva::class))->toBeTrue()
        ->and(Gate::forUser($this->contabile)->allows('create', FatturaAttiva::class))->toBeTrue()
        ->and(Gate::forUser($this->contabile)->allows('view', $fattura))->toBeTrue();
});

it('FatturaAttivaPolicy: contabile non può modificare fattura pagata', function () {
    $fatturaPagata = makeFatturaAttiva(['stato' => 'pagata']);

    expect(Gate::forUser($this->contabile)->denies('update', $fatturaPagata))->toBeTrue();
});

it('FatturaAttivaPolicy: contabile può modificare fattura in bozza', function () {
    $fatturaBozza = makeFatturaAttiva(['stato' => FatturaAttiva::STATO_BOZZA]);

    expect(Gate::forUser($this->contabile)->allows('update', $fatturaBozza))->toBeTrue();
});

it('FatturaAttivaPolicy: segreteria ha sola lettura', function () {
    $fattura = makeFatturaAttiva();

    expect(Gate::forUser($this->segreteria)->allows('view', $fattura))->toBeTrue()
        ->and(Gate::forUser($this->segreteria)->denies('create', FatturaAttiva::class))->toBeTrue()
        ->and(Gate::forUser($this->segreteria)->denies('update', $fattura))->toBeTrue()
        ->and(Gate::forUser($this->segreteria)->denies('delete', $fattura))->toBeTrue();
});

it('FatturaAttivaPolicy: guest non ha accesso', function () {
    $fattura = makeFatturaAttiva();

    expect(Gate::forUser($this->guest)->denies('viewAny', FatturaAttiva::class))->toBeTrue()
        ->and(Gate::forUser($this->guest)->denies('view', $fattura))->toBeTrue();
});

it('FatturaAttivaPolicy: contabile non può eliminare fattura trasmessa', function () {
    $fatturaTrasmessa = makeFatturaAttiva(['stato' => 'trasmessa']);

    expect(Gate::forUser($this->contabile)->denies('delete', $fatturaTrasmessa))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// FatturaPassivaPolicy
// ─────────────────────────────────────────────────────────────────────────────

it('FatturaPassivaPolicy: contabile può creare e modificare fattura senza liquidazione', function () {
    $fattura = makeFatturaPassiva();

    expect(Gate::forUser($this->contabile)->allows('create', FatturaPassiva::class))->toBeTrue()
        ->and(Gate::forUser($this->contabile)->allows('update', $fattura))->toBeTrue();
});

it('FatturaPassivaPolicy: contabile non può modificare fattura con liquidazione', function () {
    $fattura = makeFatturaPassiva(['liquidazione_iva_id' => 999]);

    expect(Gate::forUser($this->contabile)->denies('update', $fattura))->toBeTrue();
});

it('FatturaPassivaPolicy: non-admin non può eliminare', function () {
    $fattura = makeFatturaPassiva();

    expect(Gate::forUser($this->contabile)->denies('delete', $fattura))->toBeTrue()
        ->and(Gate::forUser($this->segreteria)->denies('delete', $fattura))->toBeTrue();
});

it('FatturaPassivaPolicy: admin può eliminare fattura senza liquidazione', function () {
    $fattura = makeFatturaPassiva();

    expect(Gate::forUser($this->admin)->allows('delete', $fattura))->toBeTrue();
});

it('FatturaPassivaPolicy: segreteria ha sola lettura', function () {
    $fattura = makeFatturaPassiva();

    expect(Gate::forUser($this->segreteria)->allows('view', $fattura))->toBeTrue()
        ->and(Gate::forUser($this->segreteria)->denies('create', FatturaPassiva::class))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// IncassoPolicy
// ─────────────────────────────────────────────────────────────────────────────

it('IncassoPolicy: segreteria può creare incassi', function () {
    expect(Gate::forUser($this->segreteria)->allows('create', Incasso::class))->toBeTrue();
});

it('IncassoPolicy: segreteria non può aggiornare né eliminare', function () {
    $incasso = Incasso::create([
        'tenant_id'  => $this->tenant->id,
        'type'       => Incasso::TYPE_QUOTA,
        'amount'     => 50.0,
        'paid_at'    => now(),
    ]);

    expect(Gate::forUser($this->segreteria)->denies('update', $incasso))->toBeTrue()
        ->and(Gate::forUser($this->segreteria)->denies('delete', $incasso))->toBeTrue();
});

it('IncassoPolicy: contabile può aggiornare', function () {
    $incasso = Incasso::create([
        'tenant_id'  => $this->tenant->id,
        'type'       => Incasso::TYPE_QUOTA,
        'amount'     => 50.0,
        'paid_at'    => now(),
    ]);

    expect(Gate::forUser($this->contabile)->allows('update', $incasso))->toBeTrue();
});

it('IncassoPolicy: solo admin può eliminare', function () {
    $incasso = Incasso::create([
        'tenant_id'  => $this->tenant->id,
        'type'       => Incasso::TYPE_QUOTA,
        'amount'     => 50.0,
        'paid_at'    => now(),
    ]);

    expect(Gate::forUser($this->contabile)->denies('delete', $incasso))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('delete', $incasso))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// CooperativeSharePolicy
// ─────────────────────────────────────────────────────────────────────────────

it('CooperativeSharePolicy: contabile può creare e visualizzare', function () {
    $share = makeShare();

    expect(Gate::forUser($this->contabile)->allows('create', CooperativeShare::class))->toBeTrue()
        ->and(Gate::forUser($this->contabile)->allows('view', $share))->toBeTrue();
});

it('CooperativeSharePolicy: contabile non può modificare quota riscattata', function () {
    $shareRiscattata = makeShare(['status' => 'riscattata']);

    expect(Gate::forUser($this->contabile)->denies('update', $shareRiscattata))->toBeTrue();
});

it('CooperativeSharePolicy: contabile può versare quota sottoscritta', function () {
    $share = makeShare(['status' => 'sottoscritta']);

    expect(Gate::forUser($this->contabile)->allows('versa', $share))->toBeTrue();
});

it('CooperativeSharePolicy: contabile non può versare quota già riscattata', function () {
    $share = makeShare(['status' => 'riscattata']);

    expect(Gate::forUser($this->contabile)->denies('versa', $share))->toBeTrue();
});

it('CooperativeSharePolicy: contabile può riscattare quota versata', function () {
    $share = makeShare(['status' => 'versata']);

    expect(Gate::forUser($this->contabile)->allows('riscatta', $share))->toBeTrue();
});

it('CooperativeSharePolicy: segreteria non può creare', function () {
    expect(Gate::forUser($this->segreteria)->denies('create', CooperativeShare::class))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// MovimentoContabilePolicy
// ─────────────────────────────────────────────────────────────────────────────

it('MovimentoContabilePolicy: contabile può creare e visualizzare', function () {
    $movimento = makeMovimento();

    expect(Gate::forUser($this->contabile)->allows('create', MovimentoContabile::class))->toBeTrue()
        ->and(Gate::forUser($this->contabile)->allows('view', $movimento))->toBeTrue();
});

it('MovimentoContabilePolicy: contabile può modificare bozza', function () {
    $bozza = makeMovimento(['stato' => MovimentoContabile::STATO_BOZZA]);

    expect(Gate::forUser($this->contabile)->allows('update', $bozza))->toBeTrue();
});

it('MovimentoContabilePolicy: contabile non può modificare definitivo', function () {
    $definitivo = makeMovimento(['stato' => MovimentoContabile::STATO_DEFINITIVO]);

    expect(Gate::forUser($this->contabile)->denies('update', $definitivo))->toBeTrue();
});

it('MovimentoContabilePolicy: contabile può confermare bozza', function () {
    $bozza = makeMovimento(['stato' => MovimentoContabile::STATO_BOZZA]);

    expect(Gate::forUser($this->contabile)->allows('conferma', $bozza))->toBeTrue();
});

it('MovimentoContabilePolicy: contabile può stornare definitivo', function () {
    $definitivo = makeMovimento(['stato' => MovimentoContabile::STATO_DEFINITIVO]);

    expect(Gate::forUser($this->contabile)->allows('storna', $definitivo))->toBeTrue();
});

it('MovimentoContabilePolicy: contabile non può stornare bozza', function () {
    $bozza = makeMovimento(['stato' => MovimentoContabile::STATO_BOZZA]);

    expect(Gate::forUser($this->contabile)->denies('storna', $bozza))->toBeTrue();
});

it('MovimentoContabilePolicy: solo admin può eliminare bozza', function () {
    $bozza = makeMovimento(['stato' => MovimentoContabile::STATO_BOZZA]);

    expect(Gate::forUser($this->contabile)->denies('delete', $bozza))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('delete', $bozza))->toBeTrue();
});

it('MovimentoContabilePolicy: segreteria ha sola lettura', function () {
    $movimento = makeMovimento();

    expect(Gate::forUser($this->segreteria)->allows('view', $movimento))->toBeTrue()
        ->and(Gate::forUser($this->segreteria)->denies('create', MovimentoContabile::class))->toBeTrue()
        ->and(Gate::forUser($this->segreteria)->denies('update', $movimento))->toBeTrue();
});
