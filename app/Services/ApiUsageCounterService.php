<?php

namespace App\Services;

use App\Models\ApiUsageDaily;
use App\Models\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApiUsageCounterService
{
    private function resolveLimit(): int
    {
        $fromDb = Settings::get('openapi_company_daily_limit', '');
        return $fromDb !== '' ? (int) $fromDb : (int) config('openapi_company.daily_limit', 100);
    }

    /**
     * Restituisce il costo per chiamata (€) di un endpoint.
     * Priorità: Settings DB > config > default.
     */
    public function getCostPerCall(string $endpoint): float
    {
        $costs = config('openapi_company.costs_per_call', []);

        $fromDb = Settings::get('openapi_company_costs', '');
        if ($fromDb) {
            $decoded = json_decode($fromDb, true);
            if (is_array($decoded)) {
                $costs = array_merge($costs, $decoded);
            }
        }

        return (float) ($costs[$endpoint] ?? $costs['_default'] ?? 0.20);
    }

    public function getUsage(string $provider, string $endpoint): array
    {
        $today = Carbon::now('Europe/Rome')->toDateString();
        $limit = $this->resolveLimit();
        $warning = (int) ($limit * 0.8);

        $record = ApiUsageDaily::where('provider', $provider)
            ->where('endpoint', $endpoint)
            ->where('usage_date', $today)
            ->first();

        $used = $record?->calls_count ?? 0;
        $remaining = max(0, $limit - $used);

        $status = 'ok';
        if ($used >= $limit) {
            $status = 'blocked';
        } elseif ($used >= $warning) {
            $status = 'warning';
        }

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'status' => $status,
        ];
    }

    public function canMakeCall(string $provider, string $endpoint): bool
    {
        return $this->getUsage($provider, $endpoint)['status'] !== 'blocked';
    }

    public function increment(string $provider, string $endpoint): array
    {
        $today = Carbon::now('Europe/Rome')->toDateString();
        $tenantId = app('current_tenant')->id;
        $limit = $this->resolveLimit();

        $record = ApiUsageDaily::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'endpoint' => $endpoint,
                'usage_date' => $today,
            ],
            [
                'calls_count' => 0,
                'limit_count' => $limit,
            ]
        );

        $record->increment('calls_count');

        return $this->getUsage($provider, $endpoint);
    }

    /**
     * Statistiche dettagliate per dashboard contatore.
     * Restituisce: oggi, settimana, mese, breakdown per endpoint, storico 30gg.
     */
    public function getStats(string $provider = 'openapi'): array
    {
        $tz = 'Europe/Rome';
        $today = Carbon::now($tz)->startOfDay();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $monthStart = $today->copy()->startOfMonth();
        $last30Start = $today->copy()->subDays(29);

        $tenantId = app()->bound('current_tenant') ? app('current_tenant')->id : null;

        $query = fn () => ApiUsageDaily::where('provider', $provider)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        // Breakdown oggi per endpoint
        $todayRows = $query()
            ->where('usage_date', $today->toDateString())
            ->get(['endpoint', 'calls_count']);

        $todayByEndpoint = $todayRows
            ->map(fn ($r) => [
                'endpoint' => $r->endpoint,
                'calls'    => (int) $r->calls_count,
                'cost'     => round($r->calls_count * $this->getCostPerCall($r->endpoint), 2),
            ])
            ->sortByDesc('calls')
            ->values()
            ->toArray();

        $todayTotalCalls = array_sum(array_column($todayByEndpoint, 'calls'));
        $todayTotalCost  = round(array_sum(array_column($todayByEndpoint, 'cost')), 2);

        // Totali settimana
        $weekRows = $query()
            ->whereBetween('usage_date', [$weekStart->toDateString(), $today->toDateString()])
            ->get(['endpoint', 'calls_count']);
        $weekTotalCalls = (int) $weekRows->sum('calls_count');
        $weekTotalCost  = round($weekRows->sum(fn ($r) => $r->calls_count * $this->getCostPerCall($r->endpoint)), 2);

        // Totali mese
        $monthRows = $query()
            ->whereBetween('usage_date', [$monthStart->toDateString(), $today->toDateString()])
            ->get(['endpoint', 'calls_count']);
        $monthTotalCalls = (int) $monthRows->sum('calls_count');
        $monthTotalCost  = round($monthRows->sum(fn ($r) => $r->calls_count * $this->getCostPerCall($r->endpoint)), 2);

        // Mese: breakdown per endpoint
        $monthByEndpoint = $monthRows
            ->groupBy('endpoint')
            ->map(fn ($items, $endpoint) => [
                'endpoint' => $endpoint,
                'calls'    => (int) $items->sum('calls_count'),
                'cost'     => round($items->sum('calls_count') * $this->getCostPerCall($endpoint), 2),
            ])
            ->sortByDesc('calls')
            ->values()
            ->toArray();

        // Storico ultimi 30 giorni (serie temporale)
        $historyRows = $query()
            ->whereBetween('usage_date', [$last30Start->toDateString(), $today->toDateString()])
            ->get(['usage_date', 'endpoint', 'calls_count']);

        $history = [];
        for ($i = 0; $i < 30; $i++) {
            $d = $last30Start->copy()->addDays($i)->toDateString();
            $dayCalls = $historyRows->where('usage_date', $d)->sum('calls_count');
            $dayCost = $historyRows->where('usage_date', $d)
                ->sum(fn ($r) => $r->calls_count * $this->getCostPerCall($r->endpoint));
            $history[] = [
                'date'  => $d,
                'calls' => (int) $dayCalls,
                'cost'  => round((float) $dayCost, 2),
            ];
        }

        $limit = $this->resolveLimit();
        $dailyCostWarning = (float) config('openapi_company.daily_cost_warning', 10.0);
        $monthlyCostWarning = (float) config('openapi_company.monthly_cost_warning', 200.0);

        return [
            'today' => [
                'calls'        => $todayTotalCalls,
                'limit'        => $limit,
                'cost'         => $todayTotalCost,
                'warning_cost' => $dailyCostWarning,
                'by_endpoint'  => $todayByEndpoint,
                'pct'          => $limit > 0 ? min(100, round(($todayTotalCalls / $limit) * 100)) : 0,
            ],
            'week' => [
                'calls' => $weekTotalCalls,
                'cost'  => $weekTotalCost,
            ],
            'month' => [
                'calls'        => $monthTotalCalls,
                'cost'         => $monthTotalCost,
                'warning_cost' => $monthlyCostWarning,
                'by_endpoint'  => $monthByEndpoint,
            ],
            'history_30d' => $history,
            'costs_per_call' => collect(config('openapi_company.costs_per_call', []))
                ->reject(fn ($_, $k) => $k === '_default')
                ->toArray(),
            'updated_at' => Carbon::now($tz)->toIso8601String(),
        ];
    }
}
