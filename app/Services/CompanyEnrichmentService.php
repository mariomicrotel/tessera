<?php

namespace App\Services;

use App\Exceptions\OpenApiCompanyException;
use App\Models\CompanyEnrichmentCache;
use Carbon\Carbon;

class CompanyEnrichmentService
{
    private const PROVIDER = 'openapi';

    public function __construct(
        private OpenApiCompanyClient $client,
        private ApiUsageCounterService $counter,
    ) {}

    public function enrichFromItStart(string $identifier, bool $useCache = true, bool $forceRefresh = false): array
    {
        $endpoint = 'IT-start';
        $lookupKey = $this->normalizeLookupKey($identifier);

        if ($useCache && ! $forceRefresh) {
            $cached = $this->getCached($lookupKey, $endpoint);
            if ($cached) {
                return [
                    'success' => true,
                    'source' => 'cache',
                    'usage' => $this->counter->getUsage(self::PROVIDER, $endpoint),
                    'data' => $cached->normalized_json,
                    'cached_at' => $cached->fetched_at->toIso8601String(),
                ];
            }
        }

        if (! $this->counter->canMakeCall(self::PROVIDER, $endpoint)) {
            return [
                'success' => false,
                'error' => 'Limite giornaliero di chiamate API raggiunto.',
                'usage' => $this->counter->getUsage(self::PROVIDER, $endpoint),
            ];
        }

        $raw = $this->client->getItalianCompanyStart($identifier);
        $usage = $this->counter->increment(self::PROVIDER, $endpoint);
        $mapped = self::mapItStartToAnagrafica($raw);

        $this->storeCache($lookupKey, $endpoint, $raw, $mapped);

        return [
            'success' => true,
            'source' => 'api',
            'usage' => $usage,
            'data' => $mapped,
        ];
    }

    public function getUsage(string $endpoint = 'IT-start'): array
    {
        return $this->counter->getUsage(self::PROVIDER, $endpoint);
    }

    /**
     * Esegue una ricerca aziendale via endpoint IT-search.
     *
     * Per la ricerca NON si usa la cache (i criteri possono variare ad ogni chiamata).
     * Il counter API viene comunque incrementato di 1 per ogni chiamata effettuata.
     */
    public function searchItalianCompanies(
        array $criteria,
        int $skip = 0,
        int $limit = 10,
        bool $dryRun = false,
        array $enrichWith = []
    ): array {
        $endpoint = 'IT-search';

        if (! $this->counter->canMakeCall(self::PROVIDER, $endpoint)) {
            return [
                'success' => false,
                'error'   => 'Limite giornaliero di chiamate API raggiunto.',
                'usage'   => $this->counter->getUsage(self::PROVIDER, $endpoint),
            ];
        }

        $raw = $this->client->searchItalianCompanies($criteria, $skip, $limit, $dryRun, $enrichWith);
        $usage = $this->counter->increment(self::PROVIDER, $endpoint);

        $items   = $raw['data'] ?? [];
        $mapped  = array_map(fn (array $c) => self::mapItStartToAnagrafica(['data' => [$c]]), $items);

        return [
            'success' => true,
            'source'  => 'api',
            'usage'   => $usage,
            'data'    => $mapped,
            'raw'     => $items,
            'meta'    => [
                'skip'    => $skip,
                'limit'   => $limit,
                'count'   => count($items),
                'dryRun'  => $dryRun,
                'total'   => $raw['totalCount'] ?? $raw['total'] ?? null,
            ],
        ];
    }

    public static function mapItStartToAnagrafica(array $response): array
    {
        $company = $response['data'][0] ?? $response;

        $address = $company['address'] ?? [];
        $registeredOffice = $address['registeredOffice'] ?? [];
        $gps = $registeredOffice['gps'] ?? [];

        return [
            'provider_company_id' => $company['id'] ?? null,
            'ragione_sociale' => $company['companyName'] ?? null,
            'partita_iva' => $company['vatCode'] ?? null,
            'codice_fiscale' => $company['taxCode'] ?? null,
            'stato_attivita' => $company['activityStatus'] ?? null,
            'data_registrazione' => $company['registrationDate'] ?? null,
            'codice_sdi' => $company['sdiCode'] ?? null,
            'indirizzo' => self::buildIndirizzo($registeredOffice),
            'comune' => $registeredOffice['town'] ?? null,
            'provincia' => $registeredOffice['province'] ?? null,
            'cap' => $registeredOffice['zipCode'] ?? null,
            'nazione' => $registeredOffice['country'] ?? $address['country'] ?? 'IT',
            'latitudine' => $gps['lat'] ?? $gps['latitude'] ?? null,
            'longitudine' => $gps['lng'] ?? $gps['longitude'] ?? null,
            'data_ultimo_aggiornamento_fonte' => $company['lastUpdateTimestamp'] ?? null,
        ];
    }

    private static function buildIndirizzo(array $office): ?string
    {
        $parts = array_filter([
            $office['street'] ?? $office['streetName'] ?? null,
            $office['streetNumber'] ?? null,
        ]);

        return $parts ? implode(' ', $parts) : ($office['fullAddress'] ?? null);
    }

    private function normalizeLookupKey(string $identifier): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim($identifier)));
    }

    private function getCached(string $lookupKey, string $endpoint): ?CompanyEnrichmentCache
    {
        $record = CompanyEnrichmentCache::where('lookup_key', $lookupKey)
            ->where('endpoint', $endpoint)
            ->first();

        if (! $record || $record->isExpired()) {
            return null;
        }

        return $record;
    }

    private function storeCache(string $lookupKey, string $endpoint, array $raw, array $mapped): void
    {
        $ttlDays = config('openapi_company.cache_ttl_days', 30);
        $now = Carbon::now();

        CompanyEnrichmentCache::updateOrCreate(
            [
                'tenant_id' => app('current_tenant')->id,
                'lookup_key' => $lookupKey,
                'endpoint' => $endpoint,
            ],
            [
                'response_json' => $raw,
                'normalized_json' => $mapped,
                'fetched_at' => $now,
                'expires_at' => $now->copy()->addDays($ttlDays),
                'source_provider' => self::PROVIDER,
                'response_hash' => hash('sha256', json_encode($raw)),
            ]
        );
    }
}
