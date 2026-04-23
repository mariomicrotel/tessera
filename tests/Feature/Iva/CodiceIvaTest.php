<?php

use App\Models\CodiceIva;
use App\Models\Tenant;
use Database\Seeders\CodiciIvaDiSistemaSeeder;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Test',
        'slug'              => 'coop-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    // Il TenantObserver precarica automaticamente i codici IVA di sistema
    // per i tenant cooperativa creati via Eloquent.

    app()->instance('current_tenant', $this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

test('i codici IVA di sistema vengono precaricati per un nuovo tenant cooperativa', function () {
    $codici = CodiceIva::diSistema()->get();

    expect($codici)->toHaveCount(count(CodiciIvaDiSistemaSeeder::CODICI_SISTEMA));

    // Aliquota 22% presente e marcata come tipo normale
    $venti_due = CodiceIva::where('codice', '22')->first();
    expect($venti_due)->not->toBeNull();
    expect((float) $venti_due->percentuale)->toBe(22.0);
    expect($venti_due->tipo)->toBe(CodiceIva::TIPO_NORMALE);
    expect($venti_due->di_sistema)->toBeTrue();

    // Reverse charge ha natura N6.1
    $rc = CodiceIva::where('codice', 'RC')->first();
    expect($rc->natura_sdi)->toBe('N6.1');
    expect($rc->tipo)->toBe(CodiceIva::TIPO_REVERSE_CHARGE);
});

test('tenant_id viene popolato automaticamente dal trait BelongsToTenant', function () {
    $codice = CodiceIva::create([
        'codice'      => 'CUSTOM',
        'descrizione' => 'Codice custom di test',
        'percentuale' => 22,
    ]);

    expect($codice->tenant_id)->toBe($this->tenant->id);
    expect($codice->di_sistema)->toBeFalse();
});

test('il seeder è idempotente: chiamate multiple non duplicano i codici', function () {
    $iniziali = CodiceIva::count();

    (new CodiciIvaDiSistemaSeeder)->perTenant($this->tenant);
    (new CodiciIvaDiSistemaSeeder)->perTenant($this->tenant);

    expect(CodiceIva::count())->toBe($iniziali);
});

test('un tenant non può avere due codici con lo stesso codice', function () {
    CodiceIva::create([
        'codice'      => 'DUP',
        'descrizione' => 'Primo',
        'percentuale' => 10,
    ]);

    expect(fn () => CodiceIva::create([
        'codice'      => 'DUP',
        'descrizione' => 'Secondo',
        'percentuale' => 22,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
