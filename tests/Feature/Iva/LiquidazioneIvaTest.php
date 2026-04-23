<?php

use App\Models\LiquidazioneIva;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop Test',
        'slug'              => 'coop-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);
    app()->instance('current_tenant', $this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

test('non si può avere più di una liquidazione per lo stesso periodo', function () {
    LiquidazioneIva::create([
        'anno'         => 2026,
        'periodo'      => 1,
        'tipo_periodo' => LiquidazioneIva::TIPO_TRIMESTRALE,
        'data_inizio'  => '2026-01-01',
        'data_fine'    => '2026-03-31',
    ]);

    expect(fn () => LiquidazioneIva::create([
        'anno'         => 2026,
        'periodo'      => 1,
        'tipo_periodo' => LiquidazioneIva::TIPO_TRIMESTRALE,
        'data_inizio'  => '2026-01-01',
        'data_fine'    => '2026-03-31',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('una liquidazione nasce in bozza', function () {
    $liq = LiquidazioneIva::create([
        'anno'         => 2026,
        'periodo'      => 2,
        'tipo_periodo' => LiquidazioneIva::TIPO_TRIMESTRALE,
        'data_inizio'  => '2026-04-01',
        'data_fine'    => '2026-06-30',
    ]);

    expect($liq->isBozza())->toBeTrue();
    expect($liq->status)->toBe(LiquidazioneIva::STATUS_BOZZA);
});

test('la label del periodo è formattata correttamente per trimestrale e mensile', function () {
    $trim = new LiquidazioneIva([
        'anno' => 2026, 'periodo' => 2, 'tipo_periodo' => LiquidazioneIva::TIPO_TRIMESTRALE,
    ]);
    expect($trim->periodo_label)->toBe('Q2 2026');

    $mens = new LiquidazioneIva([
        'anno' => 2026, 'periodo' => 4, 'tipo_periodo' => LiquidazioneIva::TIPO_MENSILE,
    ]);
    expect($mens->periodo_label)->toBe('Apr 2026');
});
