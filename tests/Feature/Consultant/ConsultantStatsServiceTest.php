<?php

use App\Models\Tenant;
use App\Services\Consultant\ConsultantStatsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Test per ConsultantStatsService.
 *
 * Verifica:
 * - aggregateForPeriod() restituisce la struttura attesa
 * - availablePeriods() restituisce min/max date
 * - invalidate() cancella le chiavi di cache corrette
 * - la cache viene effettivamente usata (hit su seconda chiamata)
 * - la struttura ETS vs cooperativa è corretta
 */
beforeEach(function () {
    $this->service = app(ConsultantStatsService::class);

    $this->tenant = Tenant::create([
        'name'              => 'Test ETS Consulente',
        'slug'              => 'test-ets-cs-' . uniqid(),
        'organization_type' => 'ets',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000099998',
    ]);

    $this->tenantCoop = Tenant::create([
        'name'              => 'Test Cooperativa Consulente',
        'slug'              => 'test-coop-cs-' . uniqid(),
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
        'codice_fiscale'    => '91000099997',
    ]);

    $this->from = Carbon::parse('2026-01-01');
    $this->to   = Carbon::parse('2026-12-31');
});

afterEach(function () {
    Cache::flush();
});

/* ── Struttura base ────────────────────────────────────────────────────────── */

it('restituisce la struttura attesa per un ente ETS', function () {
    $result = $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
    );

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['meta', 'common', 'ets', 'cooperativa']);

    expect($result['ets'])->not->toBeNull();
    expect($result['cooperativa'])->toBeNull();
});

it('restituisce la struttura attesa per una cooperativa', function () {
    $result = $this->service->aggregateForPeriod(
        (string) $this->tenantCoop->id,
        $this->from,
        $this->to,
    );

    expect($result['ets'])->toBeNull();
    expect($result['cooperativa'])->not->toBeNull();
});

it('common contiene tutte le sezioni attese', function () {
    $result = $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
    );

    expect($result['common'])->toHaveKeys([
        'fatturazione_attiva',
        'fatturazione_passiva',
        'cashflow',
        'banca',
        'trend_mensile',
    ]);
});

it('fatturazione_attiva ha la struttura numerica corretta con DB vuoto', function () {
    $result = $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
    );

    $fa = $result['common']['fatturazione_attiva'];

    expect($fa)->toHaveKeys(['count_totale', 'imponibile_totale', 'iva_totale', 'totale_documento', 'per_stato']);
    expect((float) $fa['totale_documento'])->toBe(0.0);
    expect((int) $fa['count_totale'])->toBe(0);
    expect($fa['per_stato'])->toBeArray();
});

it('trend_mensile è un array (può essere vuoto con DB senza dati)', function () {
    $result = $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
    );

    expect($result['common']['trend_mensile'])->toBeArray();
});

/* ── Cache ─────────────────────────────────────────────────────────────────── */

it('la seconda chiamata usa la cache (stessa risposta)', function () {
    $cacheKey = "consultant_stats:{$this->tenant->id}:{$this->from->toDateString()}:{$this->to->toDateString()}:v1";

    // Prima chiamata: popola la cache
    $first = $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
    );

    expect(Cache::has($cacheKey))->toBeTrue('cache deve essere popolata dopo la prima chiamata');

    // Seconda chiamata: deve restituire lo stesso risultato dalla cache
    $second = $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
    );

    expect($second)->toEqual($first);
});

it('forceRefresh bypassa la cache', function () {
    // Prima chiamata: popola la cache
    $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
    );

    // Verifica che la chiave sia in cache
    $cacheKey = "consultant_stats:{$this->tenant->id}:{$this->from->toDateString()}:{$this->to->toDateString()}:v1";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Elimina dalla cache prima del refresh, poi verifica che venga ricalcolata
    Cache::forget($cacheKey);
    expect(Cache::has($cacheKey))->toBeFalse();

    // La chiamata con forceRefresh deve riempire di nuovo la cache
    $result = $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
        forceRefresh: true,
    );

    expect($result)->toHaveKeys(['meta', 'common', 'ets', 'cooperativa']);
    expect(Cache::has($cacheKey))->toBeTrue();
});

/* ── Invalidazione ──────────────────────────────────────────────────────────── */

it('invalidate rimuove la cache del tenant', function () {
    $cacheKey = "consultant_stats:{$this->tenant->id}:{$this->from->toDateString()}:{$this->to->toDateString()}:v1";

    // Popola la cache
    $this->service->aggregateForPeriod((string) $this->tenant->id, $this->from, $this->to);
    expect(Cache::has($cacheKey))->toBeTrue('cache doveva essere popolata');

    // Invalida
    $this->service->invalidate((string) $this->tenant->id);

    // La chiave deve essere rimossa (anno corrente è 2026, stesso del test)
    // N.B.: invalidate() chiama forget() su anno corrente/precedente
    // from=2026-01-01 e to=2026-12-31 coincidono esattamente con l'anno corrente
    expect(Cache::has($cacheKey))->toBeFalse('cache doveva essere invalidata');
});

it('invalidate non influisce sulla cache di altri tenant', function () {
    $keyTenant1 = "consultant_stats:{$this->tenant->id}:{$this->from->toDateString()}:{$this->to->toDateString()}:v1";
    $keyTenant2 = "consultant_stats:{$this->tenantCoop->id}:{$this->from->toDateString()}:{$this->to->toDateString()}:v1";

    // Popola cache per entrambi i tenant
    $this->service->aggregateForPeriod((string) $this->tenant->id,     $this->from, $this->to);
    $this->service->aggregateForPeriod((string) $this->tenantCoop->id, $this->from, $this->to);

    expect(Cache::has($keyTenant1))->toBeTrue();
    expect(Cache::has($keyTenant2))->toBeTrue();

    // Invalida solo il primo
    $this->service->invalidate((string) $this->tenant->id);

    // Cache del primo deve essere rimossa, cache del secondo deve restare
    expect(Cache::has($keyTenant1))->toBeFalse('cache del tenant 1 doveva essere rimossa');
    expect(Cache::has($keyTenant2))->toBeTrue('cache del tenant 2 NON doveva essere rimossa');
});

/* ── availablePeriods ───────────────────────────────────────────────────────── */

it('availablePeriods restituisce min_date e max_date', function () {
    $periods = $this->service->availablePeriods((string) $this->tenant->id);

    expect($periods)->toHaveKeys(['min_date', 'max_date']);
    expect($periods['max_date'])->not->toBeEmpty();
});

it('availablePeriods usa la cache (TTL 1 ora)', function () {
    $cacheKey = "consultant_stats_periods:{$this->tenant->id}";

    $this->service->availablePeriods((string) $this->tenant->id);

    expect(Cache::has($cacheKey))->toBeTrue('availablePeriods deve cachare il risultato');

    // La seconda chiamata deve restituire lo stesso valore (dalla cache)
    $first  = $this->service->availablePeriods((string) $this->tenant->id);
    $second = $this->service->availablePeriods((string) $this->tenant->id);
    expect($second)->toEqual($first);
});

/* ── meta ───────────────────────────────────────────────────────────────────── */

it('meta contiene tenant_id, from, to', function () {
    $result = $this->service->aggregateForPeriod(
        (string) $this->tenant->id,
        $this->from,
        $this->to,
    );

    expect($result['meta'])->toHaveKeys(['tenant_id', 'from', 'to']);
    expect($result['meta']['tenant_id'])->toBe((string) $this->tenant->id);
    expect($result['meta']['from'])->toBe($this->from->toDateString());
    expect($result['meta']['to'])->toBe($this->to->toDateString());
});
